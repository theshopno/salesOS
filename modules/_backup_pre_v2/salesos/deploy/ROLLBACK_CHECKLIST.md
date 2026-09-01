# SalesOS — Rollback Checklist

Use this checklist when a production deployment must be rolled back.

**Decision criteria for rollback:**
- Health check shows `down` status and cannot be resolved within 15 minutes
- Active calls are being lost (no AMI events reaching Redis)
- Deployment verification fails with > 3 critical errors
- Browser JS errors on call popup or originate for all users

---

## IMMEDIATE ACTIONS (first 5 minutes)

- [ ] Confirm the problem is SalesOS-specific (not a broader CRM outage)
- [ ] Check `journalctl -u salesos-ami -n 50` for fatal errors
- [ ] Check `journalctl -u salesos-ws -n 50` for fatal errors
- [ ] Check Redis: `redis-cli ping` → should return PONG
- [ ] Determine: is this a config issue or a code issue?
  - Config issue → fix `daemons/config.php` and restart daemons (no rollback needed)
  - Code issue → proceed with full rollback below

---

## ROLLBACK STEPS

### 1. Notify stakeholders
- [ ] Inform team that SalesOS is temporarily unavailable
- [ ] Estimated time to restore: ~15 minutes

### 2. Run rollback.sh
```bash
# Uses the most recent backup automatically
./rollback.sh --host root@103.198.133.243 --webroot /var/www/html/crm

# Or specify a specific backup timestamp
./rollback.sh --host root@103.198.133.243 --webroot /var/www/html/crm --backup 20260626_143000
```
- [ ] rollback.sh stops services
- [ ] rollback.sh restores module directory from backup
- [ ] rollback.sh re-applies config.php (credentials preserved)
- [ ] rollback.sh restarts services
- [ ] Services show active

### 3. Verify recovery
```bash
./health_check.sh --url https://crm.example.com --email admin@example.com --password PASS
```
- [ ] Overall status: **ok** (or at minimum **degraded**, not **down**)
- [ ] `ami_consumer` → ok
- [ ] `event_archiver` → ok

```bash
ssh root@103.198.133.243 'bash /var/www/html/crm/modules/salesos/deploy/verify_install.sh --webroot /var/www/html/crm'
```
- [ ] DB tables all exist
- [ ] Module active in tblmodules

### 4. Test basic functionality
- [ ] Log in to CRM admin
- [ ] Navigate to Admin → SalesOS → Dashboard (loads without error)
- [ ] Click **Test AMI Connection** → success
- [ ] Place a test call via Click-to-Call → call originates

---

## DATABASE CONSIDERATIONS

The rollback script restores only the **module code files**. It does NOT reverse DB schema migrations.

- [ ] Were any DB schema migrations applied in the failed deployment? (check `tblsalesos_*` for new columns)
  - If YES and they caused problems: manually revert via ALTER TABLE DROP COLUMN
  - If NO: no DB action needed

- [ ] Were any options added to `tbloptions`? (check `SELECT * FROM tbloptions WHERE name LIKE 'salesos%' ORDER BY name`)
  - Generally harmless to leave — new options with no corresponding code are ignored

---

## POST-ROLLBACK

- [ ] Update LATEST backup marker to point to the known-good version
- [ ] Document what failed and why in the incident log
- [ ] Create a fix branch before re-attempting deployment

| Item | Status |
|------|--------|
| Rollback completed | |
| Services confirmed running | |
| Health check passing | |
| Test call successful | |
| Incident documented | |

**Rollback completed by:** _______________  
**Date/time:** _______________  
**Root cause (brief):** _______________
