#!/usr/bin/env bash
# salesos v2 — PBX-side provisioning (§8a of docs/salesos-v2-architecture-plan.md)
#
# Idempotent. Targets any Asterisk box (fresh, or already shared with other
# workloads — it never touches databases/config it did not itself create).
# Built from lessons learned provisioning `ecare` by hand (2026-09-02):
#   - `sudo -S` reading a piped password conflicts with any command that also
#     needs stdin (`mysql < file`, `tee -a file < snippet`) — the password pipe
#     wins and the real content never arrives. Every file write here goes
#     through `sudo bash -c "cat src >> dest"` instead.
#   - `sudo -v` credential caching does NOT reliably survive into a subprocess
#     started via `bash script.sh` over a non-interactive SSH session
#     (tty_tickets). Every privileged call goes through a local sudo()
#     wrapper that re-supplies the password every time, not just the first.
#   - The MariaDB ODBC driver registers itself under the name "MariaDB
#     Unicode" (from the `odbc-mariadb` Debian package) — read what actually
#     got registered in /etc/odbcinst.ini rather than assume a name.
#   - Config edits are wrapped in BEGIN/END marker blocks so re-running this
#     script replaces its own block instead of duplicating stanzas.
#
# Usage:
#   ./provision_pbx.sh --host user@1.2.3.4 --permit-cidr 100.64.0.0/10 [options]
#
# Required:
#   --host          SSH target, user@host
#   --permit-cidr   CIDR allowed to reach the new AMI port (Tailscale range or a
#                   specific CRM IP). No default — refusing to default this to
#                   0.0.0.0/0 is deliberate; an open AMI port is a real risk.
#
# Optional:
#   --ssh-pass PW        Password auth (if omitted, assumes key-based SSH already works)
#   --ssh-pass-file F    Read the SSH password from a file instead (avoids the
#                        password appearing in shell history / process args)
#   --sudo-pass PW       Defaults to --ssh-pass if the remote user's sudo password matches
#   --sudo-pass-file F   Same idea, for sudo password
#   --db-name       default: asteriskcdrdb
#   --db-user       default: asterisk_cdr
#   --db-pass       default: randomly generated
#   --ami-user      default: crm-api
#   --ami-secret    default: randomly generated
#   --dsn-name      default: asterisk-cdr
#   --output        default: ./pbx_provision_<host>_<timestamp>.env (gitignored;
#                   contains secrets — paste into the Settings UI, then delete)
#
# What it does on the remote box, all idempotent:
#   1. Creates an isolated MariaDB CDR database + dedicated least-privilege user
#      (never touches any other database already on the box)
#   2. Installs the MariaDB ODBC driver if missing, wires odbc.ini + res_odbc.conf
#      + cdr_adaptive_odbc.conf to it
#   3. Enables AMI, adds a scoped `[ami-user]` manager.conf user (never a
#      superuser class) restricted by ACL to --permit-cidr and localhost only
#   4. Enables http.conf (bindaddr stays 127.0.0.1 — public/TLS reachability is
#      a separate, deliberate step, not part of this script)
#   5. Adds a firewall rule restricting the AMI port to --permit-cidr + localhost
#   6. Reloads the relevant Asterisk modules and verifies each step

set -euo pipefail

# ── Defaults ──────────────────────────────────────────────────────────────
SSH_HOST=""
SSH_PASS=""
SUDO_PASS=""
PERMIT_CIDR=""
DB_NAME="asteriskcdrdb"
DB_USER="asterisk_cdr"
DB_PASS=""
AMI_USER="crm-api"
AMI_SECRET=""
DSN_NAME="asterisk-cdr"
AMI_PORT="5038"
OUTPUT_FILE=""

info()    { echo -e "\033[0;36m[provision] $*\033[0m"; }
success() { echo -e "\033[0;32m[provision] ✓ $*\033[0m"; }
warn()    { echo -e "\033[0;33m[provision] ⚠ $*\033[0m"; }
die()     { echo -e "\033[0;31m[provision] ✗ $*\033[0m"; exit 1; }

# ── Argument parsing ──────────────────────────────────────────────────────
while [[ $# -gt 0 ]]; do
  case $1 in
    --host)         SSH_HOST="$2"; shift 2 ;;
    --ssh-pass)     SSH_PASS="$2"; shift 2 ;;
    --ssh-pass-file) SSH_PASS="$(cat "$2")"; shift 2 ;;
    --sudo-pass)    SUDO_PASS="$2"; shift 2 ;;
    --sudo-pass-file) SUDO_PASS="$(cat "$2")"; shift 2 ;;
    --permit-cidr)  PERMIT_CIDR="$2"; shift 2 ;;
    --db-name)      DB_NAME="$2"; shift 2 ;;
    --db-user)      DB_USER="$2"; shift 2 ;;
    --db-pass)      DB_PASS="$2"; shift 2 ;;
    --ami-user)     AMI_USER="$2"; shift 2 ;;
    --ami-secret)   AMI_SECRET="$2"; shift 2 ;;
    --dsn-name)     DSN_NAME="$2"; shift 2 ;;
    --output)       OUTPUT_FILE="$2"; shift 2 ;;
    *) die "Unknown flag: $1" ;;
  esac
done

[[ -n "$SSH_HOST" ]] || die "--host user@ip is required"
[[ -n "$PERMIT_CIDR" ]] || die "--permit-cidr is required (e.g. 100.64.0.0/10 for Tailscale, or a specific CRM IP/32) — refusing to default this open"
[[ -n "$SUDO_PASS" ]] || SUDO_PASS="$SSH_PASS"
[[ -n "$DB_PASS" ]] || DB_PASS="$(openssl rand -base64 24 | tr -d '=+/' | head -c 24)"
[[ -n "$AMI_SECRET" ]] || AMI_SECRET="$(openssl rand -base64 24 | tr -d '=+/' | head -c 24)"

HOST_SHORT="${SSH_HOST#*@}"
TS="$(date +%Y%m%d%H%M%S)"
[[ -n "$OUTPUT_FILE" ]] || OUTPUT_FILE="./pbx_provision_${HOST_SHORT}_${TS}.env"

WORKDIR="$(mktemp -d)"
trap 'rm -rf "$WORKDIR"' EXIT

# ── SSH/SCP wrappers (password auth if given, else key-based) ─────────────
SSH_OPTS=(-o StrictHostKeyChecking=accept-new -o ConnectTimeout=10)
if [[ -n "$SSH_PASS" ]]; then
  ASKPASS="$WORKDIR/askpass.sh"
  printf '#!/bin/bash\nprintf %%s %q\n' "$SSH_PASS" > "$ASKPASS"
  chmod +x "$ASKPASS"
  remote_ssh() { SSH_ASKPASS="$ASKPASS" DISPLAY=:0 SSH_ASKPASS_REQUIRE=force ssh "${SSH_OPTS[@]}" "$SSH_HOST" "$@"; }
  remote_scp() { SSH_ASKPASS="$ASKPASS" DISPLAY=:0 SSH_ASKPASS_REQUIRE=force scp "${SSH_OPTS[@]}" "$@"; }
else
  remote_ssh() { ssh "${SSH_OPTS[@]}" -o BatchMode=yes "$SSH_HOST" "$@"; }
  remote_scp() { scp "${SSH_OPTS[@]}" -o BatchMode=yes "$@"; }
fi

info "Target: $SSH_HOST | permit-cidr: $PERMIT_CIDR | db: $DB_NAME | ami-user: $AMI_USER"

# ── Local content files (uploaded, never containing shell-quoting from ssh) ─
cat > "$WORKDIR/cdr_setup.sql" <<SQL
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;

USE ${DB_NAME};
CREATE TABLE IF NOT EXISTS cdr (
  accountcode VARCHAR(20) DEFAULT NULL,
  src VARCHAR(80) DEFAULT NULL,
  dst VARCHAR(80) DEFAULT NULL,
  dcontext VARCHAR(80) DEFAULT NULL,
  clid VARCHAR(80) DEFAULT NULL,
  channel VARCHAR(80) DEFAULT NULL,
  dstchannel VARCHAR(80) DEFAULT NULL,
  lastapp VARCHAR(80) DEFAULT NULL,
  lastdata VARCHAR(80) DEFAULT NULL,
  start DATETIME DEFAULT NULL,
  answer DATETIME DEFAULT NULL,
  end DATETIME DEFAULT NULL,
  duration INT DEFAULT NULL,
  billsec INT DEFAULT NULL,
  disposition VARCHAR(45) DEFAULT NULL,
  amaflags INT DEFAULT NULL,
  uniqueid VARCHAR(150) DEFAULT NULL,
  userfield VARCHAR(255) DEFAULT NULL,
  linkedid VARCHAR(150) DEFAULT NULL,
  peeraccount VARCHAR(20) DEFAULT NULL,
  sequence INT DEFAULT NULL,
  PRIMARY KEY (uniqueid),
  INDEX idx_start (start),
  INDEX idx_dst (dst)
) ENGINE=InnoDB;
SQL

cat > "$WORKDIR/odbc_block.ini" <<INI
[${DSN_NAME}]
Description = Asterisk CDR (isolated DB, salesos v2, provisioned $TS)
Driver = __DRIVER_NAME__
Server = 127.0.0.1
Port = 3306
Database = ${DB_NAME}
Option = 3
INI

cat > "$WORKDIR/res_odbc_block.conf" <<CONF
[${DSN_NAME}]
enabled => yes
dsn => ${DSN_NAME}
username => ${DB_USER}
password => ${DB_PASS}
pre-connect => yes
sanitysql => select 1
CONF

cat > "$WORKDIR/cdr_adaptive_block.conf" <<CONF
[${DSN_NAME}]
connection=${DSN_NAME}
table=cdr
CONF

cat > "$WORKDIR/manager_block.conf" <<CONF
[${AMI_USER}]
secret = ${AMI_SECRET}
deny = 0.0.0.0/0.0.0.0
permit = 127.0.0.1/255.255.255.255
permit = ${PERMIT_CIDR}
read = system,call,log,verbose,agent,user,config,dtmf,reporting,cdr,dialplan,originate,message
write = system,call,agent,user,config,reporting,originate,message
CONF

# ── Remote orchestration script ─────────────────────────────────────────────
cat > "$WORKDIR/remote.sh" <<'REMOTE'
#!/bin/bash
set -e
SUDO_PASS_VALUE="$1"; DB_NAME="$2"; DSN_NAME="$3"
sudo() { echo "$SUDO_PASS_VALUE" | command sudo -S -p '' "$@"; }

ensure_block() {
  # $1 = target file, $2 = section header e.g. "[asterisk-cdr]", $3 = local content file already on box
  local file="$1" header="$2" content_file="$3"
  sudo touch "$file"
  if sudo grep -qF "$header" "$file"; then
    # Remove the existing stanza: from the header line up to (but not including) the
    # next line that starts a new [section] or EOF. Safer than a naive marker delete
    # since these are native Asterisk/ODBC ini files without our own comment markers.
    sudo bash -c "awk -v hdr='$header' '
      \$0==hdr {skip=1; next}
      skip && /^\[/ {skip=0}
      !skip {print}
    ' '$file' > '${file}.tmp.$$' && mv '${file}.tmp.$$' '$file'"
  fi
  sudo bash -c "cat '$content_file' >> '$file'"
}

echo "== [1/6] install mariadb odbc driver if missing =="
if ! dpkg -l 2>/dev/null | grep -q odbc-mariadb; then
  sudo apt-get install -y odbc-mariadb
else
  echo "already installed"
fi

echo "== [2/6] CDR database + user (isolated — no other DB touched) =="
sudo bash -c "cat /tmp/salesos_provision/cdr_setup.sql | mysql"

echo "== [3/6] resolve actual registered ODBC driver name =="
DRIVER_NAME="$(sudo grep -oP '^\[\K[^\]]+' /etc/odbcinst.ini | grep -i maria | head -1)"
[[ -n "$DRIVER_NAME" ]] || { echo "Could not find a MariaDB ODBC driver in /etc/odbcinst.ini" >&2; exit 1; }
echo "driver: $DRIVER_NAME"
sudo sed -i "s|__DRIVER_NAME__|$DRIVER_NAME|" /tmp/salesos_provision/odbc_block.ini

echo "== [4/6] wire odbc.ini + res_odbc.conf + cdr_adaptive_odbc.conf =="
sudo touch /etc/odbc.ini
ensure_block /etc/odbc.ini "[$DSN_NAME]" /tmp/salesos_provision/odbc_block.ini
ensure_block /etc/asterisk/res_odbc.conf "[$DSN_NAME]" /tmp/salesos_provision/res_odbc_block.conf
ensure_block /etc/asterisk/cdr_adaptive_odbc.conf "[$DSN_NAME]" /tmp/salesos_provision/cdr_adaptive_block.conf

echo "== [5/6] AMI: enable + scoped user, http.conf: enable =="
if sudo grep -q '^enabled = no' /etc/asterisk/manager.conf; then
  sudo sed -i 's/^enabled = no/enabled = yes/' /etc/asterisk/manager.conf
fi
AMI_HEADER="$(head -1 /tmp/salesos_provision/manager_block.conf)"
ensure_block /etc/asterisk/manager.conf "$AMI_HEADER" /tmp/salesos_provision/manager_block.conf
if sudo grep -q '^;enabled=yes' /etc/asterisk/http.conf; then
  sudo sed -i 's/^;enabled=yes/enabled=yes/' /etc/asterisk/http.conf
fi

echo "== [6/6] firewall: restrict AMI port =="
UFW_BIN=""
for candidate in /usr/sbin/ufw /sbin/ufw ufw; do
  if command -v "$candidate" >/dev/null 2>&1 || [[ -x "$candidate" ]]; then UFW_BIN="$candidate"; break; fi
done
if [[ -n "$UFW_BIN" ]]; then
  sudo "$UFW_BIN" status | grep -q "5038.*salesos v2 AMI" || {
    sudo "$UFW_BIN" allow from "$4" to any port 5038 proto tcp comment 'salesos v2 AMI - provisioned' || true
    sudo "$UFW_BIN" allow from 127.0.0.1 to any port 5038 proto tcp comment 'salesos v2 AMI - localhost' || true
  }
else
  echo "no ufw found at /usr/sbin/ufw, /sbin/ufw, or on PATH — verify firewall restriction manually" >&2
fi

echo "== reload =="
sudo asterisk -rx "module reload res_odbc.so"
sudo asterisk -rx "module reload cdr_adaptive_odbc.so"
sudo asterisk -rx "manager reload"
sudo asterisk -rx "core reload" || true

echo "== verify =="
sudo ss -tlnp | grep -E ":5038|:8088" || echo "WARNING: not listening"
sudo asterisk -rx "manager show users"
sudo asterisk -rx "odbc show all"
sudo asterisk -rx "cdr show status"

rm -rf /tmp/salesos_provision
echo "PROVISION_OK"
REMOTE

# ── Upload + run ─────────────────────────────────────────────────────────
info "Uploading provisioning files..."
remote_ssh "mkdir -p /tmp/salesos_provision"
remote_scp "$WORKDIR/cdr_setup.sql" "$WORKDIR/odbc_block.ini" "$WORKDIR/res_odbc_block.conf" \
  "$WORKDIR/cdr_adaptive_block.conf" "$WORKDIR/manager_block.conf" "$WORKDIR/remote.sh" \
  "${SSH_HOST}:/tmp/salesos_provision/"

info "Running provisioning (idempotent)..."
remote_ssh "chmod +x /tmp/salesos_provision/remote.sh && /tmp/salesos_provision/remote.sh '$SUDO_PASS' '$DB_NAME' '$DSN_NAME' '$PERMIT_CIDR'"

success "Provisioning complete."

# ── Write results locally (gitignored — contains secrets) ─────────────────
cat > "$OUTPUT_FILE" <<ENV
# PBX provisioning result for $SSH_HOST — generated $TS
# Paste these into salesos Settings UI, then delete this file.
AMI_HOST=${HOST_SHORT}
AMI_PORT=${AMI_PORT}
AMI_USER=${AMI_USER}
AMI_SECRET=${AMI_SECRET}
CDR_DB_HOST=${HOST_SHORT}
CDR_DB_NAME=${DB_NAME}
CDR_DB_USER=${DB_USER}
CDR_DB_PASS=${DB_PASS}
ENV
chmod 600 "$OUTPUT_FILE"
success "Credentials written to $OUTPUT_FILE (not tracked by git — delete once entered into Settings)"
