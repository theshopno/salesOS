# SalesOS — Post-Deployment Verification Checklist

Complete immediately after deployment. Do not hand off to users until all **Required** items pass.

---

## LAYER 1: Infrastructure

| Check | Command / Action | Required | Result |
|-------|-----------------|----------|--------|
| Redis reachable | `redis-cli ping` → PONG | ✅ | |
| AMI reachable | Admin → SalesOS → Test AMI Connection | ✅ | |
| WS port listening | `ss -tlnp \| grep 8080` | ✅ | |
| Nginx WS proxy | `curl -i https://crm.example.com/salesos-ws` → 101 Switching Protocols | ✅ | |
| All systemd services active | `systemctl is-active salesos-ami salesos-archiver salesos-ws` | ✅ | |
| health_check.sh → ok | `./health_check.sh --url https://...` | ✅ | |

## LAYER 2: Database

| Check | Command / Action | Required | Result |
|-------|-----------------|----------|--------|
| Module active | `SELECT active FROM tblmodules WHERE module_name='salesos'` = 1 | ✅ | |
| All 15 tables exist | verify_install.sh DB section passes | ✅ | |
| Agent configured | `SELECT * FROM tblsalesos_agents` shows ≥ 1 active agent | ✅ | |
| Daemon config regenerated | Admin → SalesOS → Settings → mtime recent | ✅ | |

## LAYER 3: CRM Web UI

| Check | Action | Required | Result |
|-------|--------|----------|--------|
| Dashboard loads | Navigate to Admin → SalesOS | ✅ | |
| Settings page loads | Navigate to Admin → SalesOS → Settings | ✅ | |
| No JS console errors on dashboard | Browser DevTools → Console (no red errors) | ✅ | |
| Agents page loads | Navigate to Admin → SalesOS → Agents | ✅ | |

## LAYER 4: Call Flow (live test)

| Check | Action | Required | Result |
|-------|--------|----------|--------|
| Outbound call originates | Click any phone number → call rings on ext 1001 | ✅ | |
| AMI events flow | After call: `redis-cli XLEN salesos:stream:calls` > 0 | ✅ | |
| Events archived | After call: `SELECT COUNT(*) FROM tblsalesos_call_events` increased | ✅ | |
| CDR sync works | Admin → SalesOS → API → sync_cdr returns synced > 0 | ✅ | |
| Call appears in history | Admin → Lead → call history tab shows the test call | ✅ | |
| Stream lag recovers to 0 | `health_check.sh` → stream_lag all 0 after call completes | ✅ | |

## LAYER 5: WebSocket (if ws_url configured)

| Check | Action | Required | Result |
|-------|--------|----------|--------|
| Token endpoint works | `GET /admin/salesos/realtime/token` returns token | Optional | |
| WS connection established | Browser DevTools → Network → WS → 101 response | Optional | |
| Event arrives in browser | Place call → DevTools WS → see call.ringing event | Optional | |

## LAYER 6: Edge Cases

| Check | Action | Required | Result |
|-------|--------|----------|--------|
| Unknown number lookup | Click-to-call unrecognized number → popup shows "Unknown caller" | ✅ | |
| Known lead lookup | Click-to-call lead phone → popup shows lead name | ✅ | |
| Agent not configured | Log in as staff with no extension → originate returns "No extension mapped" | ✅ | |
| Health endpoint access | Non-admin staff → `/admin/salesos/api/health` → 403 | ✅ | |

---

## FINAL SIGN-OFF

All required checks must be ✅ before handing off to users.

**Verified by:** _______________  
**Date/time:** _______________  
**Notes:** _______________

If any required check fails → refer to [ROLLBACK_CHECKLIST.md](ROLLBACK_CHECKLIST.md).
