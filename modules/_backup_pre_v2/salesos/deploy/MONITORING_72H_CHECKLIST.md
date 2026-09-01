# SalesOS — 72-Hour Post-Deployment Monitoring Checklist

Run health checks at the intervals below for 72 hours after the first production deployment.
Use `health_check.sh` for automated checks; manual checks require CRM admin login.

---

## Monitoring Schedule

| Time After Deploy | Type | Action |
|-------------------|------|--------|
| +15 min | Auto | `health_check.sh` — all subsystems |
| +1 hour | Auto + Manual | `health_check.sh` + place 1 test call |
| +4 hours | Auto | `health_check.sh` |
| +8 hours (end of day 1) | Manual | Full layer checklist (below) |
| +24 hours | Auto + Manual | `health_check.sh` + review CDR sync count |
| +48 hours | Manual | Full layer checklist |
| +72 hours | Manual | Final sign-off |

---

## Automated Health Check Command

Run from your local machine or set up a cron job on the VPS:
```bash
# Every 5 minutes via cron on VPS (requires curl + python3):
*/5 * * * * /var/www/html/crm/modules/salesos/deploy/health_check.sh \
  --url http://localhost \
  >> /var/log/salesos_health.log 2>&1 || echo "SALESOS DEGRADED $(date)" >> /var/log/salesos_alerts.log
```

---

## Manual Check Items (run at +8h, +48h, +72h)

### Services
- [ ] `systemctl is-active salesos-ami salesos-archiver salesos-ws` → all active
- [ ] `journalctl -u salesos-ami --since "8 hours ago" --no-pager | grep -i "error\|warn\|disconnect\|reconnect"` → review any reconnects
- [ ] `journalctl -u salesos-ws --since "8 hours ago" --no-pager | grep -c "Delivered"` → events are being delivered
- [ ] `journalctl -u salesos-archiver --since "8 hours ago" --no-pager | grep "Flushed"` → archiver is writing rows

### Redis
- [ ] `redis-cli INFO memory | grep used_memory_human` → watch for unexpected growth
- [ ] `redis-cli XLEN salesos:stream:calls` → should not grow unboundedly (MAXLEN is trimming)
- [ ] Check stream lag: `redis-cli XINFO GROUPS salesos:stream:calls` → lag for `archiver` and `ws-delivery` should be 0
- [ ] `redis-cli HLEN salesos:calls` → should be 0 when no calls in progress

### Database
- [ ] `SELECT COUNT(*), DATE(calldate) FROM tblsalesos_calls GROUP BY DATE(calldate) ORDER BY calldate DESC LIMIT 7` → verify call counts look reasonable
- [ ] `SELECT COUNT(*), DATE(created_at) FROM tblsalesos_call_events GROUP BY DATE(created_at) ORDER BY created_at DESC LIMIT 7` → events archiving daily
- [ ] `SELECT COUNT(*) FROM tblsalesos_calls WHERE disposition='PENDING' AND calldate < NOW() - INTERVAL 1 HOUR` → should be 0 (stale PENDINGs indicate CDR sync issue)
- [ ] CDR sync last run: Admin → SalesOS → Dashboard → Last Sync timestamp < 1 hour

### Call Quality Spot Checks
- [ ] Review last 10 calls in Admin → SalesOS → Calls for correct disposition (ANSWERED/NO ANSWER/BUSY/FAILED)
- [ ] Verify no calls stuck in PENDING disposition for > 30 minutes
- [ ] Verify lead matching is working: calls from known lead numbers show lead name
- [ ] Verify call history tab appears on lead/contact/client records

### Error Log Review
```bash
# Check CRM application log for SalesOS errors
grep -i "salesos.*error\|salesos.*exception\|error.*salesos" \
  /var/www/html/crm/application/logs/log-$(date +%Y-%m-%d).php
```
- [ ] No SalesOS ERROR entries
- [ ] No SalesOS WARNING entries (warnings are acceptable, errors are not)

---

## Thresholds Requiring Immediate Action

| Metric | Threshold | Action |
|--------|-----------|--------|
| `health_check.sh` status | `down` | Restart services; if persistent → rollback |
| AMI reconnect frequency | > 3 reconnects/hour | Check firewall rules on PBX AMI port |
| Stream archiver lag | > 1000 entries | Restart salesos-archiver service |
| Calls stuck PENDING | > 5 calls > 30 min | Run CDR sync manually; check PBX CDR DB |
| Redis memory | > 500MB | Check MAXLEN config; prune old streams |
| salesos-ami service restarts | > 3 auto-restarts in 1h | Check AMI credentials and PBX availability |

---

## Day 3 Sign-Off (72 hours)

Complete when 72-hour monitoring period ends:

- [ ] Zero `down` health check events in logs
- [ ] Zero unresolved PENDING call records
- [ ] CDR sync running on schedule (every cron run)
- [ ] Stream lag consistently at 0 between calls
- [ ] No SalesOS-related ERROR entries in CRM log
- [ ] At least 10 real calls processed end-to-end (ANSWERED or NO ANSWER in tblsalesos_calls)

**Monitoring completed by:** _______________  
**Date/time:** _______________  
**Outstanding issues:** _______________  
**Phase 2B status:** ☐ STABLE / ☐ NEEDS ATTENTION

**Proceed to:** Plan Phase 3 (WebRTC browser softphone) only after this checklist is signed off.
