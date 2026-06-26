# SalesOS Infrastructure — Complete Setup Archive

**তৈরির তারিখ:** 2026-06-21
**Status:** Production Ready (Phase 1)

---

## সার্ভার পরিচিতি

| ভূমিকা | সার্ভার | OS |
|--------|---------|-----|
| CRM Server (Perfex CRM) | `180.149.235.103` | AlmaLinux 8.10 |
| PBX Server (Asterisk) | `103.42.4.210` | AlmaLinux 8.10 |
| Old PBX (Issabel - Inactive) | `103.42.4.207` | AlmaLinux 9.7 |

---

## ১. CRM Server (Perfex CRM)

### SSH Access
```
IP:       180.149.235.103
User:     root
```

### Perfex CRM Path
```
Root:     /home/fizz/Projects/crm/
Module:   /home/fizz/Projects/crm/modules/salesos/
```

---

## ২. PBX Server (Asterisk 22)

### SSH Access
```
IP:       103.42.4.210
Port:     22
User:     root
Password: bUf@W*&XFhZr
```

### SSH Login Note
```bash
sshpass -p 'password' ssh -o PreferredAuthentications=password -o PubkeyAuthentication=no root@103.42.4.210 'whoami && hostname && pwd'
```
Use this when you need a quick password-based login test without typing the password interactively.

### OS Info
```
OS:       AlmaLinux 8.10 (Cerulean Leopard)
Kernel:   4.18.0-553.123.2.el8_10.x86_64
Hostname: mustafizz.com
RAM:      19 GB
Disk:     99 GB (7.7 GB used)
```

---

## ৩. Asterisk PBX

### Version
```
Asterisk 22.10.0
Built: 2026-06-20
Source: /usr/src/asterisk-22.10.0/
Binary: /usr/sbin/asterisk
Config: /etc/asterisk/
Logs:   /var/log/asterisk/
Spool:  /var/spool/asterisk/
```

### Asterisk User
```
Linux User:  asterisk
Group:       asterisk
```

### AMI (Asterisk Manager Interface)
```
Host:     103.42.4.210
Port:     5038
Protocol: TCP

--- User: crm-api (Full Access) ---
Username: crm-api
Secret:   CRM_AMI_S3cr3t#2024
Permit:   127.0.0.1, 180.149.235.103
Read:     system,call,log,verbose,agent,user,config,dtmf,reporting,cdr,dialplan,originate,message
Write:    system,call,agent,user,config,command,reporting,originate,message

--- User: monitor-ro (Read Only) ---
Username: monitor-ro
Secret:   M0n!tor#ReadOnly2024
Permit:   127.0.0.1, 180.149.235.103
Read:     system,call,log,verbose,agent,user,cdr,reporting
Write:    command
```

### ARI (Asterisk REST Interface)
```
Host:     127.0.0.1 (localhost only)
Port:     8088
Base URL: http://127.0.0.1:8088/asterisk/ari/

--- User: crm-ari (Read/Write) ---
Username: crm-ari
Password: CRM_ARI_S3cr3t#2024

--- User: crm-ari-ro (Read Only) ---
Username: crm-ari-ro
Password: CRM_ARI_R3ad#2024
```

### PJSIP Config
```
Config:   /etc/asterisk/pjsip.conf
Protocol: UDP + TCP on 0.0.0.0:5060
TLS:      0.0.0.0:5061 (cert প্রয়োজন, configured কিন্তু inactive)
WSS:      WebSocket (WebRTC এর জন্য - Phase 2)
RTP:      10000-20000/UDP
```

### Agents (Extensions)
```
Extension | SIP Username | Password
1001      | 1001         | Agent1001#Pbx!
1002      | 1002         | Agent1002#Pbx!
1003      | 1003         | Agent1003#Pbx!
1004      | 1004         | Agent1004#Pbx!
1005      | 1005         | Agent1005#Pbx!
1006      | 1006         | Agent1006#Pbx!
1007      | 1007         | Agent1007#Pbx!
1008      | 1008         | Agent1008#Pbx!
1009      | 1009         | Agent1009#Pbx!
1010      | 1010         | Agent1010#Pbx!

SIP Server:   103.42.4.210
SIP Port:     5060 (UDP/TCP)
SIP Domain:   103.42.4.210
```

### Recordings
```
Path:   /var/spool/asterisk/recording/
Format: WAV
```

---

## ৪. SIP Trunk (BDIT)

```
Provider:      BDIT
Number (DID):  09649699699
Server:        103.170.231.10
Username:      09649699699
Password:      #bdit9933
Protocol:      SIP UDP
Endpoint Name: endpoint-trunk-bdit (Asterisk এ)
Status:        Registered ✅
```

### Outbound Dial Test
```
Test Call:   01717951166 (সফল — 200 OK পেয়েছে)
SIP Gateway: SemuxGW @ 103.170.231.10
```

---

## ৫. MariaDB (CDR Database)

### সার্ভার
```
Host:     103.42.4.210
Port:     3306
Version:  MariaDB 10.3.39
```

### CDR Database
```
Database: asteriskcdrdb
Table:    cdr
Charset:  utf8mb4

--- Credential (CRM access - Read only) ---
User:     asterisk
Password: Ast3r!skDB#2024
Host:     180.149.235.103 (CRM IP / local dev PC)
Grants:   SELECT on asteriskcdrdb.*

--- Credential (Local full access) ---
User:     asterisk
Password: Ast3r!skDB#2024
Host:     localhost
Grants:   ALL on asteriskcdrdb.*
```

### CDR Table Schema
```sql
CREATE TABLE cdr (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  calldate      DATETIME NOT NULL,
  clid          VARCHAR(80),
  src           VARCHAR(80),
  dst           VARCHAR(80),
  dcontext      VARCHAR(80),
  channel       VARCHAR(80),
  dstchannel    VARCHAR(80),
  lastapp       VARCHAR(80),
  lastdata      VARCHAR(80),
  duration      INT(11),
  billsec       INT(11),
  disposition   VARCHAR(45),
  amaflags      INT(11),
  accountcode   VARCHAR(20),
  uniqueid      VARCHAR(32),
  userfield     VARCHAR(255),
  did           VARCHAR(50),
  recordingfile VARCHAR(255),
  INDEX (calldate), INDEX (dst), INDEX (src), INDEX (accountcode)
);
```

---

## ৬. Redis

```
Host:     127.0.0.1 (localhost only)
Port:     6379
Password: R3d!sPBX#2024
Version:  Redis 5.0.3
```

---

## ৭. Fail2Ban

```
Status:  Active ✅
Jails:
  - sshd      (SSH brute force — 5 retry, 24h ban)
  - asterisk  (SIP auth fail — 5 retry, 1h ban)

Log:     /var/log/fail2ban.log
Config:  /etc/fail2ban/jail.d/asterisk.conf
Filter:  /etc/fail2ban/filter.d/asterisk.conf
```

---

## ৮. Firewall (firewalld)

```
Zone:   public (active on eth0)

Open Ports:
  22/tcp    — SSH (সব IP)
  5060/udp  — SIP UDP
  5060/tcp  — SIP TCP
  5061/tcp  — SIP TLS
  8088/tcp  — ARI HTTP (localhost preferred)
  10000-20000/udp — RTP Media

Rich Rules (specific IP whitelist):
  180.149.235.103 → 5038/tcp (AMI)
  180.149.235.103 → 3306/tcp (MariaDB CDR)
```

---

## ৯. Backup

```
Script:   /usr/local/sbin/pbx-backup.sh
Schedule: Daily 2:00 AM (cron)
Location: /var/backups/asterisk/
Retention: 30 days

Backup করে:
  - /etc/asterisk/ (সব config)
  - asteriskcdrdb (MySQL dump)
```

---

## ১০. SalesOS Module (Perfex CRM)

```
Module Path:  /home/fizz/Projects/crm/modules/salesos/
Version:      1.0.0
Status:       Phase 1 Complete (activate করতে হবে)
```

### Module Settings (Settings page থেকে দিতে হবে)
```
AMI Host:         103.42.4.210
AMI Port:         5038
AMI Username:     crm-api
AMI Secret:       CRM_AMI_S3cr3t#2024

DB Host:          103.42.4.210
DB Port:          3306
DB Name:          asteriskcdrdb
DB User:          asterisk
DB Password:      Ast3r!skDB#2024

Trunk Endpoint:   endpoint-trunk-bdit
Caller ID:        09649699699
Poll Interval:    5 (seconds)
Popup:            Enabled
CDR Auto Sync:    Enabled
```

### API Endpoints
```
Base URL:  https://<crm-domain>/admin/salesos/api/

POST /originate      — Outbound call করো
GET  /active_calls   — Active call poll (popup এর জন্য)
GET  /lookup         — Phone → Lead/Contact match
GET  /history        — Phone call history
POST /test_ami       — AMI connection test
POST /sync_cdr       — Manual CDR sync
POST /agent_login    — Agent login state
POST /agent_logout   — Agent logout
GET  /agent_status   — Current agent status
```

---

## ১১. নতুন Asterisk সার্ভারে migrate করলে

### Asterisk সার্ভারে করতে হবে:
```bash
CRM_IP="180.149.235.103"

# ১. manager.conf এ CRM IP যোগ করো
# /etc/asterisk/manager.conf এ [crm-api] section এ:
permit = 127.0.0.1/255.255.255.255
permit = <CRM_IP>/255.255.255.255

# ২. AMI reload
asterisk -rx "module reload manager"

# ৩. MariaDB user তৈরি করো
mysql -e "
CREATE USER 'asterisk'@'<CRM_IP>' IDENTIFIED BY '<PASSWORD>';
GRANT SELECT ON asteriskcdrdb.* TO 'asterisk'@'<CRM_IP>';
FLUSH PRIVILEGES;
"

# ৪. MariaDB bind address
echo "bind-address = 0.0.0.0" >> /etc/my.cnf.d/mariadb-server.cnf
systemctl restart mariadb

# ৫. Firewall
firewall-cmd --permanent --add-rich-rule="rule family=ipv4 source address=<CRM_IP>/32 port port=5038 protocol=tcp accept"
firewall-cmd --permanent --add-rich-rule="rule family=ipv4 source address=<CRM_IP>/32 port port=3306 protocol=tcp accept"
firewall-cmd --reload
```

### CRM Settings page থেকে করতে হবে:
```
১. AMI Host → নতুন IP
২. DB Host → নতুন IP
৩. Trunk Endpoint → নতুন trunk name (pjsip.conf দেখো)
৪. "Test AMI Connection" বাটন দিয়ে verify করো
৫. "Sync CDR" করো
```

---

## ১২. Quick Commands (Troubleshooting)

```bash
# Asterisk status
systemctl status asterisk
asterisk -rx "core show version"

# PJSIP endpoint check
asterisk -rx "pjsip show endpoints"

# Trunk registration
asterisk -rx "pjsip show registrations"

# Active calls
asterisk -rx "core show channels"

# Reload config
asterisk -rx "core reload"

# Fail2Ban status
fail2ban-client status asterisk

# CDR sync (manual, CRM থেকে)
curl -X POST https://<crm>/admin/salesos/api/sync_cdr \
  -H "X-Requested-With: XMLHttpRequest"

# Test AMI from CRM server
php -r "
\$s = fsockopen('103.42.4.210', 5038, \$e, \$m, 5);
fgets(\$s, 256);
fwrite(\$s, \"Action: Login\r\nUsername: crm-api\r\nSecret: CRM_AMI_S3cr3t#2024\r\n\r\n\");
while (\$l = fgets(\$s, 512)) { if (trim(\$l)==='') break; echo \$l; }
fclose(\$s);
"
```

---

## ১৩. Phase Roadmap

| Phase | Feature | Status |
|-------|---------|--------|
| **1** | AMI integration, CDR sync, Lead matching, Call popup, Call history tab, Dashboard | ✅ Done |
| **2** | ARI real-time events, WebSocket, Queue monitoring | Planned |
| **3** | WebRTC softphone (SIP.js) | Planned |
| **4** | AI Transcription (Bengali), Call notes auto-generation | Planned |
| **5** | AI Sales Coaching, Performance analytics | Planned |

---

*এই ফাইলটি `/home/fizz/Projects/crm/modules/salesos/INFRASTRUCTURE.md` এ সংরক্ষিত।*
*সকল credential পরিবর্তন হলে এই ফাইল আপডেট করো।*
