#!/usr/bin/env bash
# SalesOS — Post-Install Verification
#
# Runs directly on the server (not via HTTP). Checks every layer of the stack
# without requiring a browser session. Designed to be called from deploy.sh
# and to be run manually after any deployment or update.
#
# Usage:
#   ./verify_install.sh [--webroot /var/www/html/crm] [--ws-port 8080]
#
# Exit code: 0 = all pass, 1 = one or more checks failed

set -uo pipefail

WEBROOT="${SALESOS_WEBROOT:-/var/www/html/crm}"
WS_PORT="${SALESOS_WS_PORT:-8080}"
PHP_BIN="${PHP_BIN:-php}"
MYSQL_BIN="${MYSQL_BIN:-mysql}"

while [[ $# -gt 0 ]]; do
  case $1 in
    --webroot)  WEBROOT="$2";  shift 2 ;;
    --ws-port)  WS_PORT="$2";  shift 2 ;;
    *) echo "Unknown flag: $1"; shift ;;
  esac
done

MODULE_DIR="${WEBROOT}/modules/salesos"
DAEMON_DIR="${MODULE_DIR}/daemons"
PASS=0
FAIL=0

pass() { echo -e "\033[0;32m  ✓  $*\033[0m"; ((PASS++)) || true; }
fail() { echo -e "\033[0;31m  ✗  $*\033[0m"; ((FAIL++)) || true; }
warn() { echo -e "\033[0;33m  ⚠  $*\033[0m"; }
info() { echo -e "\033[0;36m      $*\033[0m"; }

echo ""
echo "╔══════════════════════════════════════════════════════╗"
echo "║        SalesOS Install Verification                  ║"
echo "╚══════════════════════════════════════════════════════╝"

# ── 1. Webroot ────────────────────────────────────────────────────────────────
echo ""
echo "── Filesystem ─────────────────────────────────────────"
[[ -d "$WEBROOT" ]]                     && pass "Webroot exists: $WEBROOT"              || fail "Webroot not found: $WEBROOT"
[[ -f "$WEBROOT/index.php" ]]           && pass "Perfex index.php present"             || fail "No index.php in webroot"
[[ -d "$MODULE_DIR" ]]                  && pass "salesos module directory present"      || fail "Module directory missing: $MODULE_DIR"
[[ -f "$DAEMON_DIR/config.php" ]]       && pass "Daemon config.php present"            || fail "config.php missing — run Settings → Save to generate"
[[ -f "$DAEMON_DIR/config.sample.php" ]]&& pass "config.sample.php present"            || fail "config.sample.php missing"
[[ -f "$WEBROOT/vendor/autoload.php" ]] && pass "Composer autoload present"            || fail "vendor/autoload.php missing — run: composer install --no-dev"
[[ -f "$WEBROOT/vendor/cboden/ratchet/src/Ratchet/Server/IoServer.php" ]] \
                                        && pass "Ratchet library present"              || fail "Ratchet not installed"

# ── 2. PHP extensions ─────────────────────────────────────────────────────────
echo ""
echo "── PHP Extensions ─────────────────────────────────────"
for ext in redis pdo pdo_mysql pcntl json; do
  "$PHP_BIN" -r "exit(extension_loaded('${ext}') ? 0 : 1);" \
    && pass "php ext: ${ext}"  || fail "php ext missing: ${ext}"
done
PHP_VER=$("$PHP_BIN" -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
pass "PHP version: $PHP_VER"
[[ "${PHP_VER%%.*}" -ge 8 ]] || warn "PHP 8.0+ recommended (found $PHP_VER)"

# ── 3. Redis ──────────────────────────────────────────────────────────────────
echo ""
echo "── Redis ──────────────────────────────────────────────"
if command -v redis-cli &>/dev/null; then
  redis-cli ping 2>/dev/null | grep -q PONG \
    && pass "redis-cli ping: PONG"  || fail "redis-cli ping failed"
else
  warn "redis-cli not in PATH — skipping Redis CLI check"
fi

"$PHP_BIN" -r "
\$r = new Redis();
try {
  \$r->connect('127.0.0.1', 6379, 2.0);
  echo \$r->ping() === '+PONG' || \$r->ping() === true ? 'ok' : 'fail';
} catch (Exception \$e) {
  echo 'fail: ' . \$e->getMessage();
}
" | grep -q '^ok' && pass "PHP Redis connect+ping" || fail "PHP Redis connect failed"

# ── 4. Database ───────────────────────────────────────────────────────────────
echo ""
echo "── Database ───────────────────────────────────────────"
if [[ -f "$WEBROOT/application/config/app-config.php" ]]; then
  # Use PHP to safely extract define() values (handles special chars in passwords)
  DB_HOST=$("$PHP_BIN" -r "define('BASEPATH','x'); require '${WEBROOT}/application/config/app-config.php'; echo APP_DB_HOSTNAME;" 2>/dev/null)
  DB_NAME=$("$PHP_BIN" -r "define('BASEPATH','x'); require '${WEBROOT}/application/config/app-config.php'; echo APP_DB_NAME;"     2>/dev/null)
  DB_USER=$("$PHP_BIN" -r "define('BASEPATH','x'); require '${WEBROOT}/application/config/app-config.php'; echo APP_DB_USERNAME;"  2>/dev/null)
  DB_PASS=$("$PHP_BIN" -r "define('BASEPATH','x'); require '${WEBROOT}/application/config/app-config.php'; echo APP_DB_PASSWORD;"  2>/dev/null)

  TABLES=(
    tblsalesos_agents tblsalesos_calls tblsalesos_call_events
    tblsalesos_recordings tblsalesos_active_calls tblsalesos_phone_index
    tblsalesos_settings tblsalesos_conversations tblsalesos_events
  )

  for tbl in "${TABLES[@]}"; do
    RESULT=$("$MYSQL_BIN" -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
      -se "SELECT COUNT(*) FROM $tbl;" 2>/dev/null || echo "ERROR")
    if [[ "$RESULT" == "ERROR" ]]; then
      fail "Table missing or inaccessible: $tbl"
    else
      pass "Table exists: $tbl ($RESULT rows)"
    fi
  done

  # Module activation
  ACTIVE=$("$MYSQL_BIN" -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
    -se "SELECT active FROM tblmodules WHERE module_name='salesos';" 2>/dev/null || echo "")
  [[ "$ACTIVE" == "1" ]] && pass "Module active in tblmodules" || fail "Module NOT active (active=${ACTIVE:-missing})"
else
  warn "app-config.php not found — skipping DB checks"
fi

# ── 5. AMI connectivity ───────────────────────────────────────────────────────
echo ""
echo "── AMI Connectivity ───────────────────────────────────"
if [[ -f "$DAEMON_DIR/config.php" ]]; then
  AMI_HOST=$("$PHP_BIN" -r "\$c=require '${DAEMON_DIR}/config.php'; echo \$c['ami_host']??'';" 2>/dev/null)
  AMI_PORT=$("$PHP_BIN" -r "\$c=require '${DAEMON_DIR}/config.php'; echo \$c['ami_port']??5038;" 2>/dev/null)
  AMI_USER=$("$PHP_BIN" -r "\$c=require '${DAEMON_DIR}/config.php'; echo \$c['ami_user']??'';" 2>/dev/null)
  AMI_PASS=$("$PHP_BIN" -r "\$c=require '${DAEMON_DIR}/config.php'; echo \$c['ami_secret']??'';" 2>/dev/null)

  if [[ -n "$AMI_HOST" ]]; then
    BANNER=$(timeout 3 bash -c "exec 3<>/dev/tcp/${AMI_HOST}/${AMI_PORT} && head -1 <&3" 2>/dev/null || echo "")
    echo "$BANNER" | grep -q "Asterisk" \
      && pass "AMI TCP connection: ${AMI_HOST}:${AMI_PORT}" \
      || fail "AMI unreachable: ${AMI_HOST}:${AMI_PORT}"
  else
    warn "AMI host not set in config.php"
  fi
else
  warn "config.php missing — skipping AMI check"
fi

# ── 6. WebSocket port ─────────────────────────────────────────────────────────
echo ""
echo "── WebSocket ──────────────────────────────────────────"
WS_ACTUAL=$(ss -tlnp 2>/dev/null | grep ":${WS_PORT} " | head -1 || true)
if [[ -n "$WS_ACTUAL" ]]; then
  pass "Something is listening on port ${WS_PORT}"
else
  fail "Nothing listening on port ${WS_PORT} — is salesos-ws running?"
fi

# ── 7. systemd services ───────────────────────────────────────────────────────
echo ""
echo "── systemd Services ───────────────────────────────────"
for svc in salesos-ami salesos-archiver salesos-ws; do
  if systemctl is-active --quiet "$svc" 2>/dev/null; then
    pass "${svc}: active"
  elif systemctl list-unit-files "$svc.service" &>/dev/null; then
    fail "${svc}: inactive (exists but not running)"
  else
    warn "${svc}: not installed (acceptable on local dev)"
  fi
done

# ── 8. Daemon config sanity ───────────────────────────────────────────────────
echo ""
echo "── Daemon Config ──────────────────────────────────────"
if [[ -f "$DAEMON_DIR/config.php" ]]; then
  "$PHP_BIN" -l "$DAEMON_DIR/config.php" >/dev/null 2>&1 \
    && pass "config.php syntax OK" || fail "config.php has PHP syntax errors"

  DB_PREFIX=$("$PHP_BIN" -r "\$c=require '${DAEMON_DIR}/config.php'; echo \$c['db_prefix']??'?';" 2>/dev/null)
  [[ "$DB_PREFIX" == "tbl" ]] \
    && pass "db_prefix='tbl' (correct)" \
    || fail "db_prefix='${DB_PREFIX}' — expected 'tbl'"
fi

# ── Summary ───────────────────────────────────────────────────────────────────
echo ""
echo "══════════════════════════════════════════════════════"
TOTAL=$((PASS + FAIL))
echo "  Passed: ${PASS}/${TOTAL}"
[[ $FAIL -gt 0 ]] && echo -e "  \033[0;31mFailed: ${FAIL}/${TOTAL}\033[0m"
echo ""
[[ $FAIL -eq 0 ]] \
  && echo -e "\033[0;32m  All checks passed — ready for production.\033[0m" \
  || echo -e "\033[0;31m  ${FAIL} check(s) failed — address before going live.\033[0m"
echo ""

exit $([[ $FAIL -eq 0 ]] && echo 0 || echo 1)
