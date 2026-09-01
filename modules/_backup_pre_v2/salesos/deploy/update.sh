#!/usr/bin/env bash
# SalesOS — Update Existing Deployment
#
# Usage:
#   ./update.sh [--host user@1.2.3.4] [--webroot /var/www/html/crm]
#
# What it does:
#   0. Pre-update health check (warns but does not block)
#   1. Drain active calls (waits up to DRAIN_TIMEOUT seconds)
#   2. Backup current module with integrity verification
#   3. Preserve config.php
#   4. WebSocket port synchronization check (BLOCKS on mismatch)
#   5. Graceful daemon shutdown with forced kill fallback
#   6. Deploy updated files via rsync (config.php excluded)
#   7. Restore config.php + fix permissions
#   8. Update systemd units (idempotent)
#   9. Start daemons
#  10. Post-update health check — auto-rollback on failure

set -euo pipefail

SSH_HOST="${SALESOS_SSH_HOST:-}"
WEBROOT="${SALESOS_WEBROOT:-/var/www/html/crm}"
BRANCH="${SALESOS_BRANCH:-main}"
PHP_BIN="${PHP_BIN:-php}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/salesos}"
DRAIN_TIMEOUT="${DRAIN_TIMEOUT:-30}"
STOP_TIMEOUT="${STOP_TIMEOUT:-20}"

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
section() { echo -e "\n\033[1;37m[update] ── $* ──\033[0m"; }

run_remote() {
  if [[ -n "$SSH_HOST" ]]; then
    ssh "$SSH_HOST" "$@"
  else
    bash -c "$@"
  fi
}

# ── 0. Pre-flight: module must already be deployed ────────────────────────────
section "Pre-flight"
run_remote "test -d ${MODULE_DIR}"       || die "Module directory not found at ${MODULE_DIR}. Run deploy.sh first."
run_remote "test -f ${DAEMON_DIR}/config.php" || die "config.php not found at ${DAEMON_DIR}. Run deploy.sh first."
run_remote "test -f ${WEBROOT}/index.php"    || die "Perfex CRM not found at ${WEBROOT}."
success "Module and CRM installation found"

# ── 0. Pre-update health snapshot ────────────────────────────────────────────
section "Pre-update Health Snapshot"
PRE_HEALTHY=true
for svc in salesos-ami salesos-archiver salesos-ws; do
  if run_remote "systemctl is-active --quiet ${svc} 2>/dev/null"; then
    success "PRE: ${svc} active"
  else
    warn "PRE: ${svc} was ALREADY DOWN before update — will attempt to fix"
    PRE_HEALTHY=false
  fi
done
if ! run_remote "redis-cli ping 2>/dev/null | grep -q PONG"; then
  die "Redis is DOWN — cannot safely update. Fix Redis first."
fi
success "PRE: Redis OK"
$PRE_HEALTHY || warn "Some services were unhealthy before update — proceeding to heal via update"

# ── 1. WebSocket port synchronization check ───────────────────────────────────
section "WebSocket Port Synchronization Check"
WS_PORT_CFG=$(run_remote "${PHP_BIN} -r \"\\\$c=require '${DAEMON_DIR}/config.php'; echo \\\$c['ws_port']??8080;\"" 2>/dev/null || echo "8080")

# Extract ws port from nginx proxy_pass targeting localhost
WS_PORT_NGINX=$(run_remote \
  "grep -hP -o '(?<=proxy_pass\s{0,4}http://127\.0\.0\.1:)\d+' /etc/nginx/sites-enabled/* 2>/dev/null | head -1 || \
   grep -hP -o '(?<=proxy_pass\s{0,4}http://localhost:)\d+' /etc/nginx/sites-enabled/* 2>/dev/null | head -1 || \
   echo ''" 2>/dev/null || echo "")

info "config.php ws_port : ${WS_PORT_CFG}"
info "nginx proxy_pass   : ${WS_PORT_NGINX:-not detected}"

if [[ -n "$WS_PORT_NGINX" && "$WS_PORT_CFG" != "$WS_PORT_NGINX" ]]; then
  echo ""
  echo -e "\033[0;31m╔══════════════════════════════════════════════════════════╗\033[0m"
  echo -e "\033[0;31m║  FATAL: WebSocket port mismatch detected.                ║\033[0m"
  echo -e "\033[0;31m╚══════════════════════════════════════════════════════════╝\033[0m"
  echo ""
  echo "  config.php ws_port : ${WS_PORT_CFG}"
  echo "  nginx proxy_pass   : ${WS_PORT_NGINX}"
  echo ""
  echo "  After restarting the daemon it would bind to port ${WS_PORT_CFG},"
  echo "  but nginx proxies /salesos-ws to port ${WS_PORT_NGINX}."
  echo "  WebSocket will be BROKEN after this update."
  echo ""
  echo "  Fix one of the following before re-running update.sh:"
  echo "  Option A (recommended): Regenerate config.php via"
  echo "    Admin → SalesOS → Settings → set WS Internal Port to ${WS_PORT_NGINX} → Save"
  echo "  Option B: Update nginx proxy_pass to port ${WS_PORT_CFG} and reload nginx."
  echo ""
  exit 1
fi
success "WebSocket port consistent: ${WS_PORT_CFG}"

# ── 2. Drain active calls ─────────────────────────────────────────────────────
section "Call Drain"
ELAPSED=0
while true; do
  ACTIVE=$(run_remote "redis-cli HLEN salesos:calls 2>/dev/null || echo 0")
  [[ "${ACTIVE}" -eq 0 ]] && break
  if [[ $ELAPSED -ge $DRAIN_TIMEOUT ]]; then
    warn "Active calls still present after ${DRAIN_TIMEOUT}s (count: ${ACTIVE}) — proceeding anyway"
    break
  fi
  info "Waiting for ${ACTIVE} active call(s) to end... (${ELAPSED}s / ${DRAIN_TIMEOUT}s)"
  sleep 5
  ELAPSED=$((ELAPSED + 5))
done
[[ "${ACTIVE:-0}" -eq 0 ]] && success "No active calls — safe to proceed" || true

# ── 3. Backup current module ──────────────────────────────────────────────────
section "Backup"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_PATH="${BACKUP_DIR}/salesos_${TIMESTAMP}"

info "Creating backup: ${BACKUP_PATH}..."
run_remote "mkdir -p ${BACKUP_DIR}"
run_remote "cp -r ${MODULE_DIR} ${BACKUP_PATH}"
run_remote "echo ${TIMESTAMP} > ${BACKUP_DIR}/LATEST"

# Verify backup integrity: must contain salesos.php and daemons/config.php
run_remote "test -f ${BACKUP_PATH}/salesos.php" \
  || die "Backup incomplete: salesos.php missing in ${BACKUP_PATH}"
run_remote "test -f ${BACKUP_PATH}/daemons/config.php" \
  || die "Backup incomplete: daemons/config.php missing in ${BACKUP_PATH}"
run_remote "${PHP_BIN} -l ${BACKUP_PATH}/daemons/config.php >/dev/null 2>&1" \
  || die "Backup config.php has PHP syntax errors — aborting before touching production"
success "Backup verified: ${BACKUP_PATH}"

# ── 4. Preserve config.php ────────────────────────────────────────────────────
section "Preserve Production Config"
TEMP_CONFIG="/tmp/salesos_config_update_${TIMESTAMP}.php"
run_remote "cp ${DAEMON_DIR}/config.php ${TEMP_CONFIG}"
success "config.php preserved"

# ── 5. Graceful daemon shutdown ───────────────────────────────────────────────
section "Graceful Daemon Shutdown"
info "Sending SIGTERM to SalesOS daemons..."
run_remote "systemctl stop salesos-ami salesos-archiver salesos-ws 2>/dev/null || true"

# Wait for processes to actually exit — systemd stop sends SIGTERM then SIGKILL after TimeoutStopSec
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

# ── 6. Deploy updated files ───────────────────────────────────────────────────
section "Deploy Files"
if [[ -n "$SSH_HOST" ]]; then
  SCRIPT_MODULE_DIR="$(dirname "$SCRIPT_DIR")"
  rsync -az --delete \
    --exclude='daemons/config.php' \
    --exclude='deploy/' \
    --exclude='.git' \
    "${SCRIPT_MODULE_DIR}/" "${SSH_HOST}:${MODULE_DIR}/"
else
  info "Local update — module files already in place"
fi
success "Files deployed"

# ── 7. Restore config.php + fix permissions ───────────────────────────────────
section "Restore Config & Permissions"
run_remote "cp ${TEMP_CONFIG} ${DAEMON_DIR}/config.php"
run_remote "rm -f ${TEMP_CONFIG}"
success "config.php restored"

run_remote "chown -R www-data:www-data ${MODULE_DIR}/daemons/"
run_remote "chmod 640 ${DAEMON_DIR}/config.php"
success "Permissions set"

# ── 8. Update systemd units (idempotent) ─────────────────────────────────────
section "Systemd Units"
for svc in salesos-ami salesos-archiver salesos-ws; do
  SRC="${MODULE_DIR}/systemd/${svc}.service"
  DEST="/etc/systemd/system/${svc}.service"
  run_remote "test -f ${SRC} && sed 's|/var/www/html/crm|${WEBROOT}|g' ${SRC} > ${DEST} 2>/dev/null || true"
done
run_remote "systemctl daemon-reload"
success "systemd units reloaded"

# ── 9. Start daemons ──────────────────────────────────────────────────────────
section "Start Daemons"
run_remote "systemctl start salesos-ami salesos-archiver salesos-ws"
info "Waiting 10s for daemons to initialize..."
sleep 10

# ── 10. Post-update health check — auto-rollback on failure ──────────────────
section "Post-update Health Verification"
HEALTH_FAIL=false
HEALTH_FAILURES=()

# Check all 3 services
for svc in salesos-ami salesos-archiver salesos-ws; do
  if run_remote "systemctl is-active --quiet ${svc} 2>/dev/null"; then
    success "POST: ${svc} active"
  else
    warn "POST: ${svc} NOT running"
    HEALTH_FAILURES+=("${svc} is not running")
    HEALTH_FAIL=true
  fi
done

# Check Redis still reachable
if run_remote "redis-cli ping 2>/dev/null | grep -q PONG"; then
  success "POST: Redis OK"
else
  warn "POST: Redis not responding"
  HEALTH_FAILURES+=("Redis unreachable")
  HEALTH_FAIL=true
fi

# Check AMI reachable
AMI_HOST=$(run_remote "${PHP_BIN} -r \"\\\$c=require '${DAEMON_DIR}/config.php'; echo \\\$c['ami_host']??'';\"" 2>/dev/null || echo "")
AMI_PORT=$(run_remote "${PHP_BIN} -r \"\\\$c=require '${DAEMON_DIR}/config.php'; echo \\\$c['ami_port']??5038;\"" 2>/dev/null || echo "5038")
if [[ -n "$AMI_HOST" ]]; then
  if run_remote "timeout 3 bash -c \"exec 3<>/dev/tcp/${AMI_HOST}/${AMI_PORT} && head -1 <&3\" 2>/dev/null | grep -q Asterisk"; then
    success "POST: AMI ${AMI_HOST}:${AMI_PORT} OK"
  else
    warn "POST: AMI unreachable at ${AMI_HOST}:${AMI_PORT}"
    HEALTH_FAILURES+=("AMI unreachable at ${AMI_HOST}:${AMI_PORT}")
    HEALTH_FAIL=true
  fi
fi

# Check WebSocket port listening
WS_PORT_POST=$(run_remote "${PHP_BIN} -r \"\\\$c=require '${DAEMON_DIR}/config.php'; echo \\\$c['ws_port']??8080;\"" 2>/dev/null || echo "8080")
if run_remote "ss -tlnp 2>/dev/null | grep -q ':${WS_PORT_POST} '"; then
  success "POST: WebSocket port ${WS_PORT_POST} listening"
else
  warn "POST: WebSocket port ${WS_PORT_POST} NOT listening"
  HEALTH_FAILURES+=("ws_server not bound to port ${WS_PORT_POST}")
  HEALTH_FAIL=true
fi

# Check daemon heartbeat keys in Redis
for KEY_PREFIX in "salesos:lock:ami_consumer" "salesos:lock:event_archiver" "salesos:lock:ws_server"; do
  TTL=$(run_remote "redis-cli TTL '${KEY_PREFIX}' 2>/dev/null || echo -2")
  if [[ "$TTL" -gt 0 ]]; then
    success "POST: ${KEY_PREFIX} heartbeat alive (TTL=${TTL}s)"
  else
    warn "POST: ${KEY_PREFIX} heartbeat missing (TTL=${TTL})"
    HEALTH_FAILURES+=("${KEY_PREFIX} heartbeat key absent — daemon may not have started cleanly")
    HEALTH_FAIL=true
  fi
done

# ── Auto-rollback on failure ──────────────────────────────────────────────────
if $HEALTH_FAIL; then
  echo ""
  echo -e "\033[0;31m╔══════════════════════════════════════════════════════════╗\033[0m"
  echo -e "\033[0;31m║  POST-UPDATE HEALTH CHECK FAILED — INITIATING ROLLBACK  ║\033[0m"
  echo -e "\033[0;31m╚══════════════════════════════════════════════════════════╝\033[0m"
  echo ""
  echo "  Failures detected:"
  for f in "${HEALTH_FAILURES[@]}"; do echo "    ✗ ${f}"; done
  echo ""
  echo "  Rolling back to backup: ${BACKUP_PATH} (tag: ${TIMESTAMP})"
  echo ""

  ROLLBACK_SCRIPT="${SCRIPT_DIR}/rollback.sh"
  ROLLBACK_FLAGS="--webroot ${WEBROOT} --backup ${TIMESTAMP}"

  if [[ ! -f "$ROLLBACK_SCRIPT" ]]; then
    die "rollback.sh not found at ${ROLLBACK_SCRIPT}. Manual intervention required. Backup: ${BACKUP_PATH}"
  fi

  if [[ -n "$SSH_HOST" ]]; then
    BACKUP_DIR="$BACKUP_DIR" ssh "$SSH_HOST" \
      "BACKUP_DIR=${BACKUP_DIR} bash ${MODULE_DIR}/deploy/rollback.sh ${ROLLBACK_FLAGS}" \
        && warn "Auto-rollback complete. Verify service health." \
        || die "AUTO-ROLLBACK ALSO FAILED. Manual intervention required. Backup: ${BACKUP_PATH}"
  else
    BACKUP_DIR="$BACKUP_DIR" bash "$ROLLBACK_SCRIPT" $ROLLBACK_FLAGS \
        && warn "Auto-rollback complete. Verify service health." \
        || die "AUTO-ROLLBACK ALSO FAILED. Manual intervention required. Backup: ${BACKUP_PATH}"
  fi

  echo ""
  echo -e "\033[0;33m[update] Update failed and was rolled back.\033[0m"
  echo -e "\033[0;33m[update] Backup preserved at: ${BACKUP_PATH}\033[0m"
  echo -e "\033[0;33m[update] Diagnose and re-run when ready.\033[0m"
  exit 1
fi

echo ""
success "Update complete — ${TIMESTAMP}"
info "If daemon config changed: Admin → SalesOS → Settings → Save Settings & Regenerate Daemon Config"
