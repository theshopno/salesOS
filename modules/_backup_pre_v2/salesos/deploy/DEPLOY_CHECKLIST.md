# SalesOS Phase 2B — Production Deployment Checklist

**Server:** `103.198.133.243`
**Module:** SalesOS (Perfex CRM)
**PBX:** `103.42.4.210` (Asterisk 22)

Complete every item in order. Do not proceed past a blocked item without resolution.

---

## PRE-DEPLOYMENT (on local machine)

### Code
- [ ] All Phase 2A local validation tests pass (`verify_install.sh` on dev: 29/29)
- [ ] `git status` is clean — no uncommitted changes
- [ ] RC1 tag exists: `git tag | grep rc1`
- [ ] `daemons/config.php` is **excluded** from git (contains production secrets)
- [ ] `daemons/config.sample.php` is up to date with all keys

### Credentials — gather before you SSH
- [ ] VPS SSH access confirmed: `ssh root@103.198.133.243 whoami`
- [ ] VPS CRM DB host/name/user/password (from `/var/www/html/crm/application/config/app-config.php`)
- [ ] AMI user (`crm-api`) and secret (`CRM_AMI_S3cr3t#2024`) confirmed working
- [ ] PBX CDR DB host/port/name/user/password
- [ ] Redis on VPS: confirm host (127.0.0.1), port (6379), password (if any)
- [ ] Desired WebSocket internal port (default: `8080`)
- [ ] Public WebSocket URL (e.g., `wss://crm.ecaresolutions.com/salesos-ws`)

---

## DEPLOYMENT STEPS

### 1. Backup VPS
- [ ] SSH to VPS: `ssh root@103.198.133.243`
- [ ] Confirm current Perfex version: `grep version /var/www/html/crm/module.json 2>/dev/null || ls /var/www/html/crm/`
- [ ] Manual DB backup: `mysqldump -u root -p crm > /root/crm_backup_$(date +%Y%m%d).sql`
- [ ] Module backup will be created automatically by `deploy.sh` in `/var/backups/salesos/`

### 2. Run deploy.sh
```bash
./deploy.sh --host root@103.198.133.243 --webroot /var/www/html/crm --ws-port 8080
```
- [ ] deploy.sh completes without error
- [ ] Backup created in `/var/backups/salesos/` on VPS
- [ ] Module files rsync'd to `/var/www/html/crm/modules/salesos/`
- [ ] Composer dependencies installed
- [ ] systemd units installed: `salesos-ami.service`, `salesos-archiver.service`, `salesos-ws.service`
- [ ] Nginx snippet installed at `/etc/nginx/snippets/salesos_ws.conf`
- [ ] All three services started and active

### 3. Configure daemon config.php
- [ ] Open browser: `https://crm.example.com/admin/salesos/settings`
- [ ] Verify all fields match production values:
  - AMI Host: `103.42.4.210`, Port: `5038`, Username: `crm-api`
  - PBX CDR DB credentials
  - Redis Host: `127.0.0.1`, Port: `6379`
  - WebSocket Internal Port: `8080`
  - WebSocket Public URL: `wss://crm.example.com/salesos-ws`
  - PBX ID: `pbx-01`, PBX Name: `Main PBX`
- [ ] Click **Save Settings & Regenerate Daemon Config**
- [ ] Confirm alert: "Settings saved. Daemon config regenerated."
- [ ] Click **Test AMI Connection** → confirm "AMI connected successfully"

### 4. Restart daemons with new config
```bash
ssh root@103.198.133.243 'systemctl restart salesos-ami salesos-archiver salesos-ws'
```
- [ ] All three services restart successfully
- [ ] `journalctl -u salesos-ami -n 20` shows "AMI connected."
- [ ] `journalctl -u salesos-archiver -n 10` shows "Starting event archiver."
- [ ] `journalctl -u salesos-ws -n 10` shows "WebSocket listening on ws://0.0.0.0:8080"

### 5. Nginx WebSocket proxy
- [ ] Add to your active Nginx server block:
  ```nginx
  include /etc/nginx/snippets/salesos_ws.conf;
  ```
- [ ] `nginx -t` passes
- [ ] `systemctl reload nginx`
- [ ] `wss://crm.example.com/salesos-ws` is reachable from browser devtools

### 6. Run verify_install.sh on VPS
```bash
ssh root@103.198.133.243 'bash /var/www/html/crm/modules/salesos/deploy/verify_install.sh --webroot /var/www/html/crm'
```
- [ ] All checks pass (29/29)
- [ ] systemd services show active (not just "not installed — acceptable on local dev")

### 7. Run health_check.sh
```bash
./health_check.sh --url https://crm.example.com --email admin@example.com --password YOUR_PASS
```
- [ ] Overall status: **ok**
- [ ] `redis` → ok
- [ ] `ami_consumer` → ok
- [ ] `event_archiver` → ok
- [ ] `ws_server` → ok
- [ ] `ws_port` → ok
- [ ] `db` → ok
- [ ] Stream lag: all 0

---

## SIGN-OFF

| Check | Result | By |
|-------|--------|----|
| deploy.sh completed | | |
| Settings saved + config regenerated | | |
| AMI connected | | |
| verify_install.sh 29/29 | | |
| health_check.sh all-ok | | |
| Test call placed + appears in dashboard | | |

**Deployment completed by:** _______________  
**Date/time:** _______________  
**Proceed to:** [POST_DEPLOY_CHECKLIST.md](POST_DEPLOY_CHECKLIST.md)
