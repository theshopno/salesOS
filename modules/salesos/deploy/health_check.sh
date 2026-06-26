#!/usr/bin/env bash
# SalesOS — Health Check
#
# Usage:
#   ./health_check.sh [--url https://your-crm.com] [--token ADMIN_SESSION_TOKEN]
#
# Calls GET /admin/salesos/api/health and reports each subsystem's status.
# Exit code:
#   0 = all ok
#   1 = degraded (some checks failed)
#   2 = down (critical failure)
#   3 = health endpoint unreachable

set -uo pipefail

CRM_URL="${SALESOS_CRM_URL:-http://localhost}"
COOKIE_FILE="${SALESOS_COOKIE_FILE:-/tmp/salesos_health_cookie.txt}"
CRM_EMAIL="${SALESOS_EMAIL:-}"
CRM_PASSWORD="${SALESOS_PASSWORD:-}"
TIMEOUT=10

while [[ $# -gt 0 ]]; do
  case $1 in
    --url)      CRM_URL="$2";       shift 2 ;;
    --email)    CRM_EMAIL="$2";     shift 2 ;;
    --password) CRM_PASSWORD="$2";  shift 2 ;;
    --webroot)  shift 2 ;;  # accepted but not used (compatibility with verify_install.sh)
    *) echo "Unknown flag: $1"; exit 1 ;;
  esac
done

info()    { echo -e "\033[0;36m[health] $*\033[0m"; }
ok()      { echo -e "\033[0;32m[health] ✓ $*\033[0m"; }
warn()    { echo -e "\033[0;33m[health] ⚠ $*\033[0m"; }
fail()    { echo -e "\033[0;31m[health] ✗ $*\033[0m"; }

# ── Authenticate if credentials provided ─────────────────────────────────────
if [[ -n "$CRM_EMAIL" && -n "$CRM_PASSWORD" ]]; then
  info "Authenticating as ${CRM_EMAIL}..."
  CSRF=$(curl -s -c "$COOKIE_FILE" "${CRM_URL}/admin/authentication" \
    | grep -oP 'name="csrf_token_name" value="\K[^"]+' || true)
  if [[ -z "$CSRF" ]]; then
    warn "Could not retrieve CSRF token — health check may fail if auth is required"
  else
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
      -c "$COOKIE_FILE" -b "$COOKIE_FILE" \
      -X POST "${CRM_URL}/admin/authentication" \
      --data-urlencode "email=${CRM_EMAIL}" \
      --data-urlencode "password=${CRM_PASSWORD}" \
      --data-urlencode "csrf_token_name=${CSRF}")
    [[ "$HTTP_CODE" == "303" || "$HTTP_CODE" == "200" ]] \
      && ok "Authenticated" || warn "Auth returned HTTP ${HTTP_CODE}"
  fi
fi

# ── Call health endpoint ──────────────────────────────────────────────────────
info "Calling ${CRM_URL}/admin/salesos/api/health ..."

RESPONSE=$(curl -s -b "$COOKIE_FILE" \
  --max-time "$TIMEOUT" \
  --write-out "\nHTTP_CODE:%{http_code}" \
  "${CRM_URL}/admin/salesos/api/health" 2>/dev/null)

HTTP_CODE=$(echo "$RESPONSE" | grep "HTTP_CODE:" | cut -d: -f2)
BODY=$(echo "$RESPONSE" | sed '/^HTTP_CODE:/d')

if [[ "$HTTP_CODE" != "200" ]]; then
  fail "Health endpoint returned HTTP ${HTTP_CODE:-unreachable}"
  exit 3
fi

# ── Parse with python3 ────────────────────────────────────────────────────────
python3 - "$BODY" <<'PYEOF'
import sys, json

body = sys.argv[1]
try:
    d = json.loads(body)
except Exception as e:
    print(f"\033[0;31m[health] ✗ Invalid JSON from health endpoint: {e}\033[0m")
    sys.exit(3)

STATUS_ICONS = {'ok': '✓', 'degraded': '⚠', 'down': '✗', 'unknown': '?', 'error': '✗', 'stale': '⚠'}
STATUS_COLORS = {'ok': '\033[0;32m', 'degraded': '\033[0;33m', 'down': '\033[0;31m',
                 'unknown': '\033[0;33m', 'error': '\033[0;31m', 'stale': '\033[0;33m'}
RESET = '\033[0m'

def color(s, st):
    return STATUS_COLORS.get(st, '') + s + RESET

overall = d.get('status', 'unknown')
ts      = d.get('timestamp', 0)
ms      = d.get('response_ms', 0)

print(f"\n  SalesOS Health — {color(overall.upper(), overall)}  (response: {ms}ms)")
print(f"  Timestamp: {ts}")
print()

checks = d.get('checks', {})

def row(name, st, detail=''):
    icon = STATUS_ICONS.get(st, '?')
    pad  = '  ' + name.ljust(20)
    print(color(f"{pad} {icon}  {st:<10} {detail}", st))

# Redis
r = checks.get('redis', {})
row('redis', r.get('status','?'), f"latency={r.get('latency_ms','?')}ms")

# Daemons
for d_name in ['ami_consumer', 'event_archiver', 'ws_server']:
    dd = checks.get(d_name, {})
    detail = ''
    if dd.get('pid_host'): detail = f"pid_host={dd['pid_host']} ttl={dd.get('ttl_remaining','?')}s"
    if dd.get('reason'):   detail = f"reason={dd['reason']}"
    row(d_name, dd.get('status','?'), detail)

# WS port
wp = checks.get('ws_port', {})
row('ws_port', wp.get('status','?'), f"port={wp.get('port','?')}")

# DB
db = checks.get('db', {})
row('db', db.get('status','?'), f"agents={db.get('agents','?')}")

# Active calls
print(f"\n  Active calls:    {checks.get('active_calls', 0)}")

# Stream lag
lag = checks.get('stream_lag', {})
if lag:
    print("  Stream lag (archiver):")
    for stream, groups in lag.items():
        archiver_lag = groups.get('archiver', 0)
        ws_lag       = groups.get('ws-delivery', 0)
        flag = ' ⚠ BEHIND' if archiver_lag > 100 else ''
        print(f"    {stream:<12} archiver={archiver_lag}  ws-delivery={ws_lag}{flag}")

# Last event
le = checks.get('last_event')
if le:
    print(f"\n  Last event: {le.get('event_type','')} ({le.get('seconds_ago',0)}s ago) [{le.get('stream_id','')}]")
else:
    print("\n  Last event: none")

print()

exit_code = {'ok': 0, 'degraded': 1, 'down': 2}.get(overall, 3)
sys.exit(exit_code)
PYEOF
EXIT_CODE=$?
exit $EXIT_CODE
