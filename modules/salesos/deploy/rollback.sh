#!/usr/bin/env bash
# SalesOS — Rollback to previous backup
#
# Usage:
#   ./rollback.sh [--host user@1.2.3.4] [--webroot /var/www/html/crm] [--backup 20260626_143000]
#
# If --backup is not specified, uses the most recent backup recorded in LATEST.
# The rollback:
#   1. Stops all SalesOS daemon services
#   2. Restores the module directory from the backup
#   3. Restarts daemon services
#   4. Runs health_check.sh to confirm recovery

set -euo pipefail

SSH_HOST="${SALESOS_SSH_HOST:-}"
WEBROOT="${SALESOS_WEBROOT:-/var/www/html/crm}"
BACKUP_TAG="${BACKUP_TAG:-}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/salesos}"

while [[ $# -gt 0 ]]; do
  case $1 in
    --host)    SSH_HOST="$2";   shift 2 ;;
    --webroot) WEBROOT="$2";    shift 2 ;;
    --backup)  BACKUP_TAG="$2"; shift 2 ;;
    *) echo "Unknown flag: $1"; exit 1 ;;
  esac
done

MODULE_DIR="${WEBROOT}/modules/salesos"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

info()    { echo -e "\033[0;36m[rollback] $*\033[0m"; }
success() { echo -e "\033[0;32m[rollback] ✓ $*\033[0m"; }
warn()    { echo -e "\033[0;33m[rollback] ⚠ $*\033[0m"; }
die()     { echo -e "\033[0;31m[rollback] ✗ $*\033[0m"; exit 1; }

run_remote() {
  if [[ -n "$SSH_HOST" ]]; then
    ssh "$SSH_HOST" "$@"
  else
    bash -c "$@"
  fi
}

# ── Resolve backup path ───────────────────────────────────────────────────────
if [[ -z "$BACKUP_TAG" ]]; then
  BACKUP_TAG=$(run_remote "cat ${BACKUP_DIR}/LATEST 2>/dev/null || echo ''")
  [[ -z "$BACKUP_TAG" ]] && die "No LATEST backup record found in ${BACKUP_DIR}. Specify --backup TIMESTAMP."
  info "Using latest backup: ${BACKUP_TAG}"
fi

BACKUP_PATH="${BACKUP_DIR}/salesos_${BACKUP_TAG}"
run_remote "test -d ${BACKUP_PATH}" || die "Backup not found: ${BACKUP_PATH}"

# ── Stop services ─────────────────────────────────────────────────────────────
info "Stopping SalesOS daemon services..."
run_remote "systemctl stop salesos-ami salesos-archiver salesos-ws 2>/dev/null || true"
sleep 3
success "Services stopped"

# ── Preserve current config.php (contains production credentials) ─────────────
info "Preserving current daemon config.php..."
CURRENT_CONFIG="${MODULE_DIR}/daemons/config.php"
TEMP_CONFIG="/tmp/salesos_config_${BACKUP_TAG}.php"
run_remote "test -f ${CURRENT_CONFIG} && cp ${CURRENT_CONFIG} ${TEMP_CONFIG} || true"

# ── Restore backup ────────────────────────────────────────────────────────────
info "Restoring from backup ${BACKUP_PATH}..."
run_remote "rm -rf ${MODULE_DIR}"
run_remote "cp -r ${BACKUP_PATH} ${MODULE_DIR}"
success "Module directory restored"

# ── Re-apply preserved config.php ────────────────────────────────────────────
info "Re-applying daemon config.php..."
run_remote "test -f ${TEMP_CONFIG} && cp ${TEMP_CONFIG} ${CURRENT_CONFIG} || warn 'config.php not restored — check manually'"
run_remote "rm -f ${TEMP_CONFIG}"

# ── Fix permissions ───────────────────────────────────────────────────────────
run_remote "chown -R www-data:www-data ${MODULE_DIR}/daemons/"
run_remote "chmod 640 ${MODULE_DIR}/daemons/config.php 2>/dev/null || true"

# ── Restart services ──────────────────────────────────────────────────────────
info "Restarting SalesOS daemon services..."
run_remote "systemctl start salesos-ami salesos-archiver salesos-ws"
sleep 5
run_remote "systemctl is-active --quiet salesos-ami"      && success "salesos-ami running"      || warn "salesos-ami NOT running"
run_remote "systemctl is-active --quiet salesos-archiver" && success "salesos-archiver running" || warn "salesos-archiver NOT running"
run_remote "systemctl is-active --quiet salesos-ws"       && success "salesos-ws running"       || warn "salesos-ws NOT running"

# ── Post-rollback health check ────────────────────────────────────────────────
HEALTH_SCRIPT="${SCRIPT_DIR}/health_check.sh"
if [[ -f "$HEALTH_SCRIPT" ]]; then
  info "Running health check after rollback..."
  if [[ -n "$SSH_HOST" ]]; then
    ssh "$SSH_HOST" "bash ${MODULE_DIR}/deploy/health_check.sh --webroot ${WEBROOT}" || true
  else
    bash "$HEALTH_SCRIPT" --webroot "$WEBROOT" || true
  fi
fi

echo ""
success "Rollback to ${BACKUP_TAG} complete."
warn "If the issue was in database schema, the rollback did NOT reverse DB migrations."
warn "Review tblsalesos_* tables manually if schema changes were made during the failed deploy."
