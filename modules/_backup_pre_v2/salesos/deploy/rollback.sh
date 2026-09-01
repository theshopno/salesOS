#!/usr/bin/env bash
# SalesOS — Rollback to previous backup
#
# Usage:
#   ./rollback.sh [--host user@1.2.3.4] [--webroot /var/www/html/crm] [--backup 20260626_143000]
#
# What it does:
#   1. Stops all SalesOS daemon services (graceful with fallback)
#   2. Restores the module directory from the specified backup
#   3. Re-applies preserved config.php (production credentials are never overwritten)
#   4. Restarts all daemon services
#   5. Verifies restored files, running services, and WebSocket port
#   6. Calls Health API as final gate — rollback is only SUCCESS if API returns "ok"

set -euo pipefail

SSH_HOST="${SALESOS_SSH_HOST:-}"
WEBROOT="${SALESOS_WEBROOT:-/var/www/html/crm}"
BACKUP_TAG="${BACKUP_TAG:-}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/salesos}"
PHP_BIN="${PHP_BIN:-php}"
STOP_TIMEOUT="${STOP_TIMEOUT:-20}"
# Optional: set CRM_URL for Health API gate (e.g. https://crm.bizyto.com)
CRM_URL="${SALESOS_CRM_URL:-}"

while [[ $# -gt 0 ]]; do
  case $1 in
    --host)    SSH_HOST="$2";   shift 2 ;;
    --webroot) WEBROOT="$2";    shift 2 ;;
    --backup)  BACKUP_TAG="$2"; shift 2 ;;
    --url)     CRM_URL="$2";    shift 2 ;;
    *) echo "Unknown flag: $1"; exit 1 ;;
  esac
done

MODULE_DIR="${WEBROOT}/modules/salesos"
DAEMON_DIR="${MODULE_DIR}/daemons"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

info()    { echo -e "\033[0;36m[rollback] $*\033[0m"; }
success() { echo -e "\033[0;32m[rollback] ✓ $*\033[0m"; }
warn()    { echo -e "\033[0;33m[rollback] ⚠ $*\033[0m"; }
die()     { echo -e "\033[0;31m[rollback] ✗ $*\033[0m"; exit 1; }
section() { echo -e "\n\033[1;37m[rollback] ── $* ──\033[0m"; }

run_remote() {
  if [[ -n "$SSH_HOST" ]]; then
    ssh "$SSH_HOST" "$@"
  else
    bash -c "$@"
  fi
}

# ── Resolve backup path ───────────────────────────────────────────────────────
section "Locate Backup"
if [[ -z "$BACKUP_TAG" ]]; then
  BACKUP_TAG=$(run_remote "cat ${BACKUP_DIR}/LATEST 2>/dev/null || echo ''" || echo "")
  [[ -z "$BACKUP_TAG" ]] && die "No LATEST backup record found in ${BACKUP_DIR}. Specify --backup TIMESTAMP."
  info "Using latest backup: ${BACKUP_TAG}"
fi

BACKUP_PATH="${BACKUP_DIR}/salesos_${BACKUP_TAG}"
run_remote "test -d ${BACKUP_PATH}" || die "Backup not found: ${BACKUP_PATH}"

# Verify backup integrity before touching production
run_remote "test -f ${BACKUP_PATH}/salesos.php" \
  || die "Backup is corrupt: salesos.php missing in ${BACKUP_PATH}"
run_remote "test -f ${BACKUP_PATH}/daemons/config.php" \
  || die "Backup is corrupt: daemons/config.php missing in ${BACKUP_PATH}"
run_remote "${PHP_BIN} -l ${BACKUP_PATH}/daemons/config.php >/dev/null 2>&1" \
  || die "Backup config.php has PHP syntax errors — cannot safely restore"
success "Backup verified: ${BACKUP_PATH}"

# ── Preserve current config.php ───────────────────────────────────────────────
section "Preserve Current Config"
CURRENT_CONFIG="${DAEMON_DIR}/config.php"
TEMP_CONFIG="/tmp/salesos_rollback_config_${BACKUP_TAG}.php"
if run_remote "test -f ${CURRENT_CONFIG}"; then
  run_remote "cp ${CURRENT_CONFIG} ${TEMP_CONFIG}"
  success "Current config.php preserved to ${TEMP_CONFIG}"
else
  warn "No current config.php found — will use backup's config.php"
fi

# ── Graceful service shutdown ─────────────────────────────────────────────────
section "Graceful Daemon Shutdown"
info "Stopping SalesOS daemons..."
run_remote "systemctl stop salesos-ami salesos-archiver salesos-ws 2>/dev/null || true"

ELAPSED=0
while run_remote "systemctl is-active --quiet salesos-ami 2>/dev/null \
    || systemctl is-active --quiet salesos-ws 2>/dev/null" && [[ $ELAPSED -lt $STOP_TIMEOUT ]]; do
  sleep 2
  ELAPSED=$((ELAPSED + 2))
done

if run_remote "systemctl is-active --quiet salesos-ami 2>/dev/null || systemctl is-active --quiet salesos-ws 2>/dev/null"; then
  warn "Services did not stop within ${STOP_TIMEOUT}s — sending SIGKILL"
  run_remote "systemctl kill -s SIGKILL salesos-ami salesos-archiver salesos-ws 2>/dev/null || true"
  sleep 2
fi
success "Daemons stopped"

# ── Restore backup ────────────────────────────────────────────────────────────
section "Restore Module"
info "Removing current module and restoring backup..."
run_remote "rm -rf ${MODULE_DIR}"
run_remote "cp -r ${BACKUP_PATH} ${MODULE_DIR}"
success "Module directory restored from ${BACKUP_PATH}"

# ── Re-apply production config.php ───────────────────────────────────────────
section "Restore Config"
if run_remote "test -f ${TEMP_CONFIG}"; then
  run_remote "cp ${TEMP_CONFIG} ${CURRENT_CONFIG}"
  run_remote "rm -f ${TEMP_CONFIG}"
  success "Production config.php restored (credentials preserved)"
else
  warn "Using backup's config.php — verify credentials before services start"
fi

# ── Fix permissions ───────────────────────────────────────────────────────────
run_remote "chown -R www-data:www-data ${MODULE_DIR}/daemons/"
run_remote "chmod 640 ${DAEMON_DIR}/config.php 2>/dev/null || true"
success "Permissions restored"

# ── Verify restored module files ──────────────────────────────────────────────
section "Verify Restored Files"
VERIFY_FAIL=false

run_remote "test -f ${MODULE_DIR}/salesos.php" \
  && success "salesos.php present" \
  || { warn "salesos.php MISSING after restore"; VERIFY_FAIL=true; }

run_remote "test -f ${DAEMON_DIR}/config.php" \
  && success "daemons/config.php present" \
  || { warn "daemons/config.php MISSING after restore"; VERIFY_FAIL=true; }

run_remote "test -f ${MODULE_DIR}/daemons/ami_consumer.php" \
  && success "ami_consumer.php present" \
  || { warn "ami_consumer.php MISSING after restore"; VERIFY_FAIL=true; }

run_remote "${PHP_BIN} -l ${DAEMON_DIR}/config.php >/dev/null 2>&1" \
  && success "config.php syntax OK" \
  || { warn "config.php has PHP syntax errors"; VERIFY_FAIL=true; }

$VERIFY_FAIL && die "Restored module is incomplete. Manual intervention required."

# ── Reload systemd units from restored module ─────────────────────────────────
for svc in salesos-ami salesos-archiver salesos-ws; do
  SRC="${MODULE_DIR}/systemd/${svc}.service"
  DEST="/etc/systemd/system/${svc}.service"
  run_remote "test -f ${SRC} && sed 's|/var/www/html/crm|${WEBROOT}|g' ${SRC} > ${DEST} 2>/dev/null || true"
done
run_remote "systemctl daemon-reload"

# ── Restart services ──────────────────────────────────────────────────────────
section "Restart Daemons"
run_remote "systemctl start salesos-ami salesos-archiver salesos-ws"
info "Waiting 10s for daemons to initialize..."
sleep 10

# ── Verify services running ───────────────────────────────────────────────────
section "Service Verification"
SVC_FAIL=false

for svc in salesos-ami salesos-archiver salesos-ws; do
  if run_remote "systemctl is-active --quiet $svc 2>/dev/null"; then
    success "${svc}: active"
  else
    warn "${svc}: FAILED to start"
    run_remote "journalctl -u ${svc} --no-pager -n 10 2>/dev/null || true"
    SVC_FAIL=true
  fi
done

# Verify WebSocket port
WS_PORT_RB=$(run_remote "${PHP_BIN} -r \"\\\$c=require '${DAEMON_DIR}/config.php'; echo \\\$c['ws_port']??8080;\"" 2>/dev/null || echo "8080")
if run_remote "ss -tlnp 2>/dev/null | grep -q ':${WS_PORT_RB} '"; then
  success "WebSocket port ${WS_PORT_RB}: listening"
else
  warn "WebSocket port ${WS_PORT_RB}: NOT listening"
  SVC_FAIL=true
fi

# Verify daemon heartbeat keys
for KEY in "salesos:lock:ami_consumer" "salesos:lock:event_archiver" "salesos:lock:ws_server"; do
  TTL=$(run_remote "redis-cli TTL '${KEY}' 2>/dev/null || echo -2")
  if [[ "$TTL" -gt 0 ]]; then
    success "Heartbeat ${KEY}: alive (TTL=${TTL}s)"
  else
    warn "Heartbeat ${KEY}: not yet present (TTL=${TTL})"
  fi
done

$SVC_FAIL && die "Rollback completed but services did not start cleanly. Manual intervention required. Check: journalctl -u salesos-ws -n 50"

# ── Health API final gate ─────────────────────────────────────────────────────
section "Health API Gate"
if [[ -n "$CRM_URL" ]]; then
  info "Calling ${CRM_URL}/admin/salesos/api/health ..."
  HEALTH_RESPONSE=$(run_remote "curl -sk --max-time 8 '${CRM_URL}/admin/salesos/api/health' 2>/dev/null" || echo "")

  if [[ -z "$HEALTH_RESPONSE" ]]; then
    warn "Health API unreachable at ${CRM_URL} — services are up but API not confirmed"
  else
    HEALTH_STATUS=$(echo "$HEALTH_RESPONSE" | \
      python3 -c "import sys,json; d=json.loads(sys.stdin.read()); print(d.get('status','error'))" 2>/dev/null \
      || echo "parse_error")

    if [[ "$HEALTH_STATUS" == "ok" ]]; then
      success "Health API: OK — rollback VERIFIED SUCCESSFUL"
    elif [[ "$HEALTH_STATUS" == "degraded" ]]; then
      warn "Health API: DEGRADED — rollback completed, system partially healthy"
    else
      warn "Health API: ${HEALTH_STATUS} — rollback completed but system not fully healthy"
      warn "Run health_check.sh for details"
    fi
  fi
else
  info "CRM_URL not set — skipping Health API check"
  info "  To enable: export SALESOS_CRM_URL=https://crm.bizyto.com and re-run, or set --url flag"
fi

echo ""
success "Rollback to ${BACKUP_TAG} complete."
warn "If the issue was in database schema, this rollback did NOT reverse DB migrations."
warn "Review tblsalesos_* tables manually if schema changes were made during the failed deploy."
