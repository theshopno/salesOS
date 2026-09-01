#!/usr/bin/env bash
# SalesOS Phase 2B — Initial Production Deployment
#
# Usage:
#   ./deploy.sh [--host user@1.2.3.4] [--webroot /var/www/html/crm] [--branch main]
#
# What it does:
#   1. Pushes the module to the VPS via git or rsync
#   2. Installs Composer dependencies
#   3. Copies systemd unit files and enables services
#   4. Installs Nginx WebSocket proxy snippet
#   5. Reloads Nginx
#   6. Starts all three SalesOS daemons
#   7. Runs verify_install.sh to confirm everything is healthy
#
# The script does NOT touch the CRM itself — only the salesos module directory
# and the two system config locations (systemd + nginx snippets).
#
# Run from your local machine or the VPS directly.

set -euo pipefail

# ── Defaults (override via flags or environment) ─────────────────────────────
SSH_HOST="${SALESOS_SSH_HOST:-}"
WEBROOT="${SALESOS_WEBROOT:-/var/www/html/crm}"
BRANCH="${SALESOS_BRANCH:-main}"
REPO_URL="${SALESOS_REPO:-https://github.com/ecaresolutions/salesos}"
WS_PORT="${SALESOS_WS_PORT:-8080}"
PHP_BIN="${PHP_BIN:-php}"
NGINX_SNIPPET_DIR="${NGINX_SNIPPET_DIR:-/etc/nginx/snippets}"
SYSTEMD_DIR="${SYSTEMD_DIR:-/etc/systemd/system}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/salesos}"

# ── Argument parsing ─────────────────────────────────────────────────────────
while [[ $# -gt 0 ]]; do
  case $1 in
    --host)    SSH_HOST="$2";  shift 2 ;;
    --webroot) WEBROOT="$2";   shift 2 ;;
    --branch)  BRANCH="$2";    shift 2 ;;
    --ws-port) WS_PORT="$2";   shift 2 ;;
    *) echo "Unknown flag: $1"; exit 1 ;;
  esac
done

MODULE_DIR="${WEBROOT}/modules/salesos"
DAEMON_DIR="${MODULE_DIR}/daemons"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ── Helpers ───────────────────────────────────────────────────────────────────
info()    { echo -e "\033[0;36m[deploy] $*\033[0m"; }
success() { echo -e "\033[0;32m[deploy] ✓ $*\033[0m"; }
warn()    { echo -e "\033[0;33m[deploy] ⚠ $*\033[0m"; }
die()     { echo -e "\033[0;31m[deploy] ✗ $*\033[0m"; exit 1; }

run_remote() {
  if [[ -n "$SSH_HOST" ]]; then
    ssh "$SSH_HOST" "$@"
  else
    bash -c "$@"
  fi
}

# ── Pre-flight checks ─────────────────────────────────────────────────────────
info "Pre-flight checks..."
[[ -z "$WEBROOT" ]]    && die "--webroot is required (or set SALESOS_WEBROOT)"

run_remote "test -d ${WEBROOT}" || die "Webroot ${WEBROOT} does not exist on target"
run_remote "test -f ${WEBROOT}/index.php" || die "No index.php found — is this a Perfex CRM installation?"

# ── Backup current module ─────────────────────────────────────────────────────
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_PATH="${BACKUP_DIR}/salesos_${TIMESTAMP}"
info "Backing up current module to ${BACKUP_PATH}..."
run_remote "mkdir -p ${BACKUP_DIR}"
run_remote "cp -r ${MODULE_DIR} ${BACKUP_PATH} 2>/dev/null || true"
run_remote "echo ${TIMESTAMP} > ${BACKUP_DIR}/LATEST"
success "Backup created: ${BACKUP_PATH}"

# ── Deploy module files ───────────────────────────────────────────────────────
info "Deploying module files to ${MODULE_DIR}..."

if [[ -n "$SSH_HOST" ]]; then
  # Rsync local module directory to remote
  SCRIPT_MODULE_DIR="$(dirname "$SCRIPT_DIR")"
  rsync -az --delete \
    --exclude='daemons/config.php' \
    --exclude='deploy/' \
    --exclude='.git' \
    "${SCRIPT_MODULE_DIR}/" "${SSH_HOST}:${MODULE_DIR}/"
else
  # Local deploy: module is already in place (CI/CD context)
  info "Local deployment — module files already in place"
fi
success "Module files deployed"

# ── Composer dependencies ─────────────────────────────────────────────────────
info "Installing Composer dependencies..."
run_remote "cd ${WEBROOT} && ${PHP_BIN} -r \"exit(is_file('vendor/autoload.php') ? 0 : 1);\"" \
  || run_remote "cd ${WEBROOT} && composer install --no-dev --no-interaction --quiet"
success "Composer dependencies ready"

# ── Generate daemon config.php ────────────────────────────────────────────────
info "Generating daemons/config.php..."
run_remote "test -f ${DAEMON_DIR}/config.php" \
  && warn "config.php already exists — it will be preserved. Regenerate via Admin → SalesOS → Settings → Save." \
  || run_remote "cp ${DAEMON_DIR}/config.sample.php ${DAEMON_DIR}/config.php && echo 'Copied from config.sample.php — EDIT BEFORE STARTING DAEMONS'"

# ── systemd unit files ────────────────────────────────────────────────────────
info "Installing systemd unit files..."
for svc in salesos-ami salesos-archiver salesos-ws; do
  SRC="${MODULE_DIR}/systemd/${svc}.service"
  DEST="${SYSTEMD_DIR}/${svc}.service"
  run_remote "sed 's|/var/www/html/crm|${WEBROOT}|g' ${SRC} > ${DEST}"
  success "Installed ${svc}.service"
done
run_remote "systemctl daemon-reload"
run_remote "systemctl enable salesos-ami salesos-archiver salesos-ws"
success "systemd units enabled"

# ── WebSocket proxy snippet (Nginx or Apache2) ────────────────────────────────
info "Checking WebSocket proxy configuration..."
if run_remote "command -v nginx >/dev/null 2>&1 && systemctl is-active --quiet nginx"; then

  # Detect whether nginx already has /salesos-ws proxied (in server blocks or snippets)
  NGINX_WS_EXISTING=$(run_remote \
    "grep -rh 'location.*salesos-ws' /etc/nginx/sites-enabled/ /etc/nginx/sites-available/ /etc/nginx/conf.d/ \
     /etc/nginx/snippets/ 2>/dev/null | head -1 || echo ''" 2>/dev/null || echo "")

  if [[ -n "$NGINX_WS_EXISTING" ]]; then
    # Already configured — detect which port nginx is using
    NGINX_WS_PORT=$(run_remote \
      "grep -hP -o '(?<=proxy_pass\s{0,4}http://127\.0\.0\.1:)\d+' \
       /etc/nginx/sites-enabled/* /etc/nginx/sites-available/* /etc/nginx/conf.d/* \
       /etc/nginx/snippets/* 2>/dev/null | head -1 || echo ''" 2>/dev/null || echo "")

    info "WebSocket proxy already configured in nginx (port ${NGINX_WS_PORT:-unknown})"

    if [[ -n "$NGINX_WS_PORT" && "$NGINX_WS_PORT" != "$WS_PORT" ]]; then
      warn "nginx WS port (${NGINX_WS_PORT}) differs from WS_PORT (${WS_PORT})."
      warn "Update nginx proxy_pass or config.php ws_port to make them match before starting daemons."
    else
      success "Nginx WS proxy already configured on port ${NGINX_WS_PORT:-${WS_PORT}} — no changes needed"
    fi
  else
    # Not yet configured — install the snippet and tell the operator what to do
    run_remote "mkdir -p ${NGINX_SNIPPET_DIR}"
    run_remote "sed 's|SALESOS_WS_PORT|${WS_PORT}|g' ${MODULE_DIR}/nginx/salesos_ws.conf > ${NGINX_SNIPPET_DIR}/salesos_ws.conf"
    run_remote "nginx -t && systemctl reload nginx" || warn "Nginx config test failed — check ${NGINX_SNIPPET_DIR}/salesos_ws.conf"
    warn "WebSocket proxy NOT yet in nginx. Add this line inside your server {} block and reload nginx:"
    warn "  include ${NGINX_SNIPPET_DIR}/salesos_ws.conf;"
    success "Nginx WS snippet written to ${NGINX_SNIPPET_DIR}/salesos_ws.conf"
  fi

elif run_remote "command -v apache2ctl >/dev/null 2>&1 && systemctl is-active --quiet apache2"; then
  APACHE_CONF_DIR="${APACHE_CONF_DIR:-/etc/apache2/conf-available}"
  run_remote "mkdir -p ${APACHE_CONF_DIR}"
  run_remote "sed 's|SALESOS_WS_PORT|${WS_PORT}|g' ${MODULE_DIR}/apache/salesos_ws.conf > ${APACHE_CONF_DIR}/salesos_ws.conf"
  run_remote "a2enmod proxy proxy_http proxy_wstunnel 2>/dev/null || true"
  run_remote "a2enconf salesos_ws 2>/dev/null || true"
  run_remote "apache2ctl configtest && systemctl reload apache2" || warn "Apache config test failed — check ${APACHE_CONF_DIR}/salesos_ws.conf"
  success "Apache2 WS proxy config installed"
else
  warn "Neither Nginx nor Apache2 detected as active — WS proxy NOT configured. Install manually."
fi

# ── Fix file permissions ──────────────────────────────────────────────────────
info "Setting file permissions..."
run_remote "chown -R www-data:www-data ${MODULE_DIR}/daemons/"
run_remote "chmod 750 ${DAEMON_DIR}/ami_consumer.php ${DAEMON_DIR}/event_archiver.php ${DAEMON_DIR}/ws_server.php"
run_remote "chmod 640 ${DAEMON_DIR}/config.php 2>/dev/null || true"
success "Permissions set"

# ── Start services ────────────────────────────────────────────────────────────
info "Starting SalesOS daemon services..."
run_remote "systemctl start salesos-ami salesos-archiver salesos-ws"
sleep 5
run_remote "systemctl is-active --quiet salesos-ami"      && success "salesos-ami running"      || warn "salesos-ami NOT running — check: journalctl -u salesos-ami -n 50"
run_remote "systemctl is-active --quiet salesos-archiver" && success "salesos-archiver running" || warn "salesos-archiver NOT running"
run_remote "systemctl is-active --quiet salesos-ws"       && success "salesos-ws running"       || warn "salesos-ws NOT running"

# ── Post-install verification ─────────────────────────────────────────────────
info "Running post-install verification..."
VERIFY_SCRIPT="${SCRIPT_DIR}/verify_install.sh"
if [[ -f "$VERIFY_SCRIPT" ]]; then
  if [[ -n "$SSH_HOST" ]]; then
    ssh "$SSH_HOST" "bash ${MODULE_DIR}/deploy/verify_install.sh --webroot ${WEBROOT}"
  else
    bash "$VERIFY_SCRIPT" --webroot "$WEBROOT"
  fi
else
  warn "verify_install.sh not found — skipping automated verification"
fi

echo ""
success "Deployment complete — ${TIMESTAMP}"
info "Next steps:"
info "  1. Open Admin → SalesOS → Settings, verify all fields, click Save (regenerates config.php)"
info "  2. Run health_check.sh to confirm all systems are green"
info "  3. Place a test call and verify the call appears in the dashboard"
