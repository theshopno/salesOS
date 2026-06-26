#!/usr/bin/env bash
# SalesOS — Update Existing Deployment
#
# Used for incremental updates after the initial deploy.sh was run.
# Safer than deploy.sh: drains active calls before stopping services,
# always creates a backup, preserves config.php.
#
# Usage:
#   ./update.sh [--host user@1.2.3.4] [--webroot /var/www/html/crm] [--branch main]

set -euo pipefail

SSH_HOST="${SALESOS_SSH_HOST:-}"
WEBROOT="${SALESOS_WEBROOT:-/var/www/html/crm}"
BRANCH="${SALESOS_BRANCH:-main}"
PHP_BIN="${PHP_BIN:-php}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/salesos}"
DRAIN_TIMEOUT="${DRAIN_TIMEOUT:-30}"

while [[ $# -gt 0 ]]; do
  case $1 in
    --host)    SSH_HOST="$2";  shift 2 ;;
    --webroot) WEBROOT="$2";   shift 2 ;;
    --branch)  BRANCH="$2";    shift 2 ;;
    *) echo "Unknown flag: $1"; exit 1 ;;
  esac
done

MODULE_DIR="${WEBROOT}/modules/salesos"
DAEMON_DIR="${MODULE_DIR}/daemons"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

info()    { echo -e "\033[0;36m[update] $*\033[0m"; }
success() { echo -e "\033[0;32m[update] ✓ $*\033[0m"; }
warn()    { echo -e "\033[0;33m[update] ⚠ $*\033[0m"; }
die()     { echo -e "\033[0;31m[update] ✗ $*\033[0m"; exit 1; }

run_remote() {
  if [[ -n "$SSH_HOST" ]]; then
    ssh "$SSH_HOST" "$@"
  else
    bash -c "$@"
  fi
}

# ── Pre-flight ────────────────────────────────────────────────────────────────
info "Pre-flight checks..."
run_remote "test -d ${MODULE_DIR}" || die "Module directory not found. Run deploy.sh first."
run_remote "test -f ${DAEMON_DIR}/config.php" || die "config.php missing. Run deploy.sh first."

# ── Wait for active calls to end (drain) ─────────────────────────────────────
info "Checking for active calls (drain window: ${DRAIN_TIMEOUT}s)..."
ELAPSED=0
while true; do
  ACTIVE=$(run_remote "redis-cli HLEN salesos:calls 2>/dev/null || echo 0")
  [[ "$ACTIVE" -eq 0 ]] && break
  if [[ $ELAPSED -ge $DRAIN_TIMEOUT ]]; then
    warn "Active calls still present after ${DRAIN_TIMEOUT}s (count: ${ACTIVE}) — proceeding anyway"
    break
  fi
  info "Waiting for ${ACTIVE} active call(s) to end... (${ELAPSED}s/${DRAIN_TIMEOUT}s)"
  sleep 5
  ELAPSED=$((ELAPSED + 5))
done
[[ "${ACTIVE:-0}" -eq 0 ]] && success "No active calls — safe to proceed"

# ── Backup ────────────────────────────────────────────────────────────────────
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_PATH="${BACKUP_DIR}/salesos_${TIMESTAMP}"
info "Creating backup: ${BACKUP_PATH}..."
run_remote "mkdir -p ${BACKUP_DIR}"
run_remote "cp -r ${MODULE_DIR} ${BACKUP_PATH}"
run_remote "echo ${TIMESTAMP} > ${BACKUP_DIR}/LATEST"
success "Backup created"

# ── Preserve config.php ───────────────────────────────────────────────────────
TEMP_CONFIG="/tmp/salesos_config_update_${TIMESTAMP}.php"
run_remote "cp ${DAEMON_DIR}/config.php ${TEMP_CONFIG}"
info "config.php preserved"

# ── Stop services ─────────────────────────────────────────────────────────────
info "Stopping services..."
run_remote "systemctl stop salesos-ami salesos-archiver salesos-ws 2>/dev/null || true"
sleep 2
success "Services stopped"

# ── Deploy updated files ──────────────────────────────────────────────────────
info "Deploying updated module files..."

if [[ -n "$SSH_HOST" ]]; then
  SCRIPT_MODULE_DIR="$(dirname "$SCRIPT_DIR")"
  rsync -az --delete \
    --exclude='daemons/config.php' \
    --exclude='deploy/' \
    --exclude='.git' \
    "${SCRIPT_MODULE_DIR}/" "${SSH_HOST}:${MODULE_DIR}/"
else
  info "Local update — module files in place"
fi
success "Files deployed"

# ── Restore config.php ────────────────────────────────────────────────────────
run_remote "cp ${TEMP_CONFIG} ${DAEMON_DIR}/config.php"
run_remote "rm -f ${TEMP_CONFIG}"
success "config.php restored"

# ── Composer dependencies ─────────────────────────────────────────────────────
info "Updating Composer dependencies..."
run_remote "cd ${WEBROOT} && ${PHP_BIN} -r \"exit(is_file('vendor/autoload.php') ? 0 : 1);\"" \
  || run_remote "cd ${WEBROOT} && composer install --no-dev --no-interaction --quiet"
success "Composer ready"

# ── Update systemd units if changed ──────────────────────────────────────────
info "Reloading systemd unit files..."
for svc in salesos-ami salesos-archiver salesos-ws; do
  SRC="${MODULE_DIR}/systemd/${svc}.service"
  DEST="/etc/systemd/system/${svc}.service"
  run_remote "sed 's|/var/www/html/crm|${WEBROOT}|g' ${SRC} > ${DEST} 2>/dev/null || true"
done
run_remote "systemctl daemon-reload"

# ── Fix permissions ───────────────────────────────────────────────────────────
run_remote "chown -R www-data:www-data ${MODULE_DIR}/daemons/"
run_remote "chmod 640 ${DAEMON_DIR}/config.php"

# ── Restart services ──────────────────────────────────────────────────────────
info "Starting services..."
run_remote "systemctl start salesos-ami salesos-archiver salesos-ws"
sleep 6
run_remote "systemctl is-active --quiet salesos-ami"      && success "salesos-ami running"      || warn "salesos-ami NOT running"
run_remote "systemctl is-active --quiet salesos-archiver" && success "salesos-archiver running" || warn "salesos-archiver NOT running"
run_remote "systemctl is-active --quiet salesos-ws"       && success "salesos-ws running"       || warn "salesos-ws NOT running"

# ── Verify ────────────────────────────────────────────────────────────────────
VERIFY="${SCRIPT_DIR}/verify_install.sh"
if [[ -f "$VERIFY" ]]; then
  info "Running verify_install.sh..."
  if [[ -n "$SSH_HOST" ]]; then
    ssh "$SSH_HOST" "bash ${MODULE_DIR}/deploy/verify_install.sh --webroot ${WEBROOT}" || warn "Some verifications failed — review output above"
  else
    bash "$VERIFY" --webroot "$WEBROOT" || warn "Some verifications failed"
  fi
fi

echo ""
success "Update complete — ${TIMESTAMP}"
info "If daemon config changed: Admin → SalesOS → Settings → Save Settings & Regenerate Daemon Config"
