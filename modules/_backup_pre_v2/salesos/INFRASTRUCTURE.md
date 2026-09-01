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
| **Dev Server (OVH VPS)** | `213.32.69.195` | Ubuntu 22.04.5 LTS |

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

## ১৩. Dev Server (OVH VPS)

**তৈরির তারিখ:** 2026-07-01 | **উদ্দেশ্য:** SalesOS/CRM development ও testing (production নয়)

### SSH Access
```
IP:        213.32.69.195
IPv6:      2001:41d0:367:11c0::1
Port:      22
User:      ubuntu
Password:  EpBgnQ5HYXyY@New1
SSH Key:   ✅ passwordless key auth সেটআপ করা আছে (2026-07-03)
Sudo:      passwordless (NOPASSWD)
```

### Authorized SSH Keys (local machine → এই সার্ভার)
```
Local private key:  ~/.ssh/id_ed25519  (dev machine: fizz@crm)
Local private key:  ~/.ssh/id_rsa      (dev machine: fizz@crm)
Server:              ~ubuntu/.ssh/authorized_keys এ যোগ করা (2026-07-03, ssh-copy-id দিয়ে)

Public key (ed25519):
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIJFZbB4+H/gHQpoA5+r4byIkjE9zSJyejL/modjLZyZO helloshopno@gmail.com

Public key (rsa, comment: crm-mysql-tunnel):
ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAACAQDVXUtxWe2cO4qHU8duT2cj5IvA5IE5V0km5Vo+vaGf8yy3c3KudTq8R8WRsjNXrjGB1ucd4ddrG9FcDt2fmNOAw9kCJnReCINxjAYlcBilzyd06qkFrzh46w/tq1yrT3YjnvZ/sBpqHK0YbqPP94x26mrWAtGSu4H/Clkf/7WmAE93Yq5FZF1pA65YnUNqsoYSQi8ALq+2iFIbtCZIhul1/B82urNKRjJWtru/+Ab949uT0eziwegboJa1FoSrH0/yG+wp3XwpfjQ03B0EEKds3EmQUe90ynSK6LUq2BCbnwn51cWBaMPlFLFzQ3d/luZDV222VLTWQRyGOCdQXtKUBVeHxXTrQp2oLtL+AG9mwuP2YBsgNOcys2dqOiFpeG59uQMQO0f8Ci0jAEd7k4zequ2eDodyKr5RXfZZc64tIdRaW3jdQZ+SgEMI7xFBv8b98MKXPY7kv5A2Bmm0we97xsgZ1YfvxNEiMbnz2fHUdqnCrM0llo2Nq5cQkElHhG7GUa0LDBEeXALdOAAKesea0eh1wZPsPmirdkkXYcFujLqFqidwVC6wAcmjzYNfmxpvA+XjtuwcQMGbZ4WznG0XEbw8RfZvEoj2eg4Us1U8+tJdAgxmRP+t01PqOjPxGU97l3LQuQvdpkR0DZhMEX2BQN2C5/8Wd8hgTtpnKvKquQ== crm-mysql-tunnel

Quick login (key auth, password লাগবে না):
ssh ubuntu@213.32.69.195
```

### OS / Hardware
```
OS:        Ubuntu 22.04.5 LTS
Kernel:    5.15.0-185
CPU:       4 cores
RAM:       7.6 GB
Disk:      73 GB (/dev/sda1)
Swap:      2 GB
Timezone:  Asia/Dhaka
Hostname:  vps-1e26b3a1
```

### Stack
```
MariaDB:   10.11.18 — root: R00t@Dev#Mdb26
  DB: db_crm     / crm_user     / CRM@Dev#Mdb26
  DB: db_laravel / laravel_user / Lrv@Dev#Mdb26

Redis:     8.8.0 — 127.0.0.1:6379, no auth (DB0=CRM/SalesOS, DB1=Laravel)

PHP:       8.2.31-FPM, দুটো আলাদা pool:
  /run/php/php8.2-fpm-crm.sock     (user: www-crm)
  /run/php/php8.2-fpm-laravel.sock (user: www-laravel)

Nginx:     1.18 — vhost: /etc/nginx/sites-available/crm.stcakweb.com

Composer:  system-wide installed
```

### Perfex CRM (এই সার্ভারে)
```
Webroot:   /var/www/html/crm (owner: www-crm:www-data)
DB:        db_crm (182 tables, local dev dump — prod 103.42.4.211 থেকে না)
Domain:    crm.stcakweb.com (DNS pending নতুন IP-র জন্য)
Module:    /var/www/html/crm/modules/salesos/
Daemon config: /var/www/html/crm/modules/salesos/daemons/config.php
  (ami → 103.42.4.210, redis → 127.0.0.1 DB0)
```

### Local Asterisk Instance (এই সার্ভারে, dev/test PBX)
```
Version:   Asterisk 22.10.1
Config:    /etc/asterisk/
Logs:      /var/log/asterisk/ (full ফাইল logrotate-এ যোগ করা হয়েছে — 2026-07-03 ফিক্স)
Logger:    verbose,notice,warning,error (debug/dtmf বাদ দেওয়া হয়েছে — অতিরিক্ত ভার্বোজ ছিল)

BDIT Trunk [bdit-reg]: ❌ DISABLED (2026-07-03)
  কারণ: BDIT সার্ভার (103.170.231.10) এই OVH IP থেকে REGISTER সাইলেন্টলি
  ড্রপ করে (packet capture দিয়ে কনফার্মড — 0 response)। প্রোডাকশন PBX
  (103.42.4.210, BD-hosted) থেকে registration কাজ করে, whitelist সমস্যা
  OVH-এর জন্যই। ঠিক করতে হলে BDIT সাপোর্টে 213.32.69.195 whitelist করাতে হবে।
  endpoint/aor/auth কনফিগ অক্ষত আছে, শুধু registration comment-out করা।
  Backup: /etc/asterisk/pjsip.conf.pre-bdit-disable.bak
```

### Firewall (UFW)
```
Status:  active
Allow:   SSH(22, rate-limited), HTTP(80), HTTPS(443), WS(8080,8082),
         Dev(8000,8001,3000), SIP(5060 tcp/udp)
Deny:    MySQL(3306), Redis(6379), Postgres(5432) — external থেকে

fail2ban jails: sshd, nginx-scan (এই সার্ভারে asterisk jail নেই —
  production PBX-এর মতো SIP brute-force protection যোগ করা দরকার হতে পারে)
```

### পরিচিত সমস্যা / নোট
```
- 2026-07-03: /var/log/asterisk/full লগরোটেট মিস হয়ে 26GB জমে গিয়েছিল
  (rotate না হওয়ায়) — ফিক্স করা হয়েছে (daily rotate + verbosity কমানো)
- SIP scan/flood ট্রাফিক নিয়মিত আসে (51.222.38.229, 172.110.223.167
  ইত্যাদি থেকে) — কোনো auth ব্রেক হয়নি, শুধু noise
- Production সার্ভার (103.42.4.210, 103.42.4.211) এই dev server থেকে
  সরাসরি reachable না (SSH/network পর্যায়ে ফায়ারওয়াল/routing সীমাবদ্ধতা)
```

---

## ১৪. Phase Roadmap

| Phase | Feature | Status |
|-------|---------|--------|
| **1** | AMI integration, CDR sync, Lead matching, Call popup, Call history tab, Dashboard | ✅ Done |
| **2** | ARI real-time events, WebSocket, Queue monitoring | Planned |
| **3** | WebRTC softphone (SIP.js) | Planned |
| **4** | AI Transcription (Bengali), Call notes auto-generation | Planned |
| **5** | AI Sales Coaching, Performance analytics | Planned |

---

## ১৫. pbxpopup Bridge — eCare (⚠️ আলাদা client/server, উপরের সব সেকশন থেকে ভিন্ন)

**গুরুত্বপূর্ণ:** এই সেকশনের সার্ভার-ফ্যাক্টগুলো Section ১-১৪-এ বর্ণিত SalesOS/stcakweb deployment (103.42.4.210, 180.149.235.103) থেকে সম্পূর্ণ ভিন্ন একটা client — **eCare** (`my.uddoktayon.com` CRM, `pbxpopup` মডিউল)। দুটো গুলিয়ে ফেলা যাবে না।

### সার্ভার পরিচিতি

| ভূমিকা | ঠিকানা | বিস্তারিত |
|--------|--------|-----------|
| eCare PBX (Asterisk, vanilla) | `192.168.0.154` (LAN), public `180.149.235.103` (NAT-এর পেছনে) | Debian 13 trixie, hostname `ecare`, Tailscale IP `100.85.86.93` (শুধু admin SSH-এর জন্য ব্যবহারযোগ্য, CRM-সংযোগের জন্য না) |
| eCare CRM hosting | `uddoktayon.com` cPanel অ্যাকাউন্ট, hostname `planet.whitelabelwebpanel.com` | **শেয়ার্ড রিসেলার হোস্টিং** — verified জেইলশেল (uid 1045, `sudo` নেই, `/etc/os-release` নেই, `/dev/net/tun` নেই)। SSH কাস্টম পোর্ট `9343`-এ (২২ বন্ধ)। |

### কেন Tailscale/VPN সম্ভব না (verified, অনুমান না)

CRM হোস্টিং অ্যাকাউন্টে সরাসরি SSH ঢুকে চেক করা হয়েছে:
```
whoami        → uddoktayon (uid=1045, non-root)
sudo -n -l    → "sudo: command not found"
/etc/os-release → No such file (jailshell, real OS filesystem না)
/dev/net/tun  → No such file (TUN device নেই)
```
এই চার লাইনই চূড়ান্তভাবে প্রমাণ করে VPN mesh (Tailscale ইত্যাদি) এই cPanel অ্যাকাউন্ট থেকে অসম্ভব — root/kernel-access লাগবে যা শেয়ার্ড রিসেলার হোস্টিং-এ থাকে না। **সমাধান: HTTPS + shared-secret token bridge**, CRM সাইডে শুধু `curl`-ই লাগে।

### eCare PBX — Asterisk বিস্তারিত

```
Vanilla Asterisk (FreePBX/Issabel GUI নেই), systemd service নামে চলে
AMI:          বন্ধ (manager.conf enabled=no) — এই কাজে দরকার নেই, least-privilege বজায় রাখা হয়েছে
CDR:          CSV ফাইলে, ডাটাবেসে না → /var/log/asterisk/cdr-csv/Master.csv
              cdr.conf batch mode বন্ধ (ডিফল্ট) → প্রতি কল হ্যাংআপেই সাথে সাথে row লেখা হয়, batching delay নেই
Recording format: GSM (WAV না — অন্য client/103.42.4.210-এর থেকে ভিন্ন, ব্রাউজার সরাসরি প্লে করতে পারে না)
Recording path:   /var/spool/asterisk/recordings/YYYY/MM/DD/<AgentName>/HHMMSS_out_to_<number>.gsm  (আউটগোয়িং ফ্লো)
                   /var/spool/asterisk/recordings/YYYY/MM/DD/_pending/HHMMSS_<uniqueid>.gsm → হ্যাংআপে
                   System(mv) দিয়ে একই নামে FINALDIR-এ (ইনকামিং/queue ফ্লো, extensions.conf:924-954)
Existing backup:  /usr/local/bin/backup_recordings.sh — প্রতি ১৫ মিনিটে rclone দিয়ে
                   gdrive:Call Record PBX/eCare-এ sync (--min-age 5m)
Existing cleanup: /usr/local/bin/cleanup_recordings.sh — cron 3:10am, ৯০ দিনের পুরনো GSM লোকাল থেকে ডিলিট
Router NAT (TP-Link AX1500): 80,443→192.168.0.154 TCP; 5060 UDP; 10000-20000 UDP RTP — সব আগে থেকেই ফরওয়ার্ড করা ছিল
```

### bridge.php ডিজাইন সিদ্ধান্ত (কেন এভাবে করা হলো)

- **CDR row ↔ রেকর্ডিং ফাইল ম্যাচিং কোনো agent-name DB lookup ছাড়াই:** আউটগোয়িং ফাইলে ডায়াল করা নম্বর ফাইলনেমে থাকে (`*_out_to_<number>.gsm`), ইনকামিং/queue ফ্লোতে Asterisk uniqueid ফাইলনেমে থাকে (`*_<uniqueid>.gsm`) — দুটোর যেকোনো একটা pattern দিয়ে `find` করলেই date-folder-এর ভেতর সঠিক ফাইল পাওয়া যায়, dialplan/astdb ছোঁয়া লাগে না।
- **Master token কখনো ব্রাউজারে যায় না:** `list` action (server-to-server curl, master token দিয়ে) প্রতিটা রেকর্ডিং-এর জন্য একটা HMAC-signed `relpath` রিটার্ন করে; `<audio src>` সরাসরি সেই signed URL ব্যবহার করে `stream` action হিট করে — page source-এ master token কখনো এক্সপোজ হয় না।
- **কোনো VPN/Tailscale লাগে না CRM সাইডে:** bridge.php PBX বক্সেই চলে (Caddy + php-fpm, `ecare-pbx.uddoktayon.com` ডোমেইনে auto-TLS), CRM শুধু বহির্গামী `curl` করে — শেয়ার্ড হোস্টিং-এ এটাই একমাত্র বাস্তবসম্মত পথ (উপরের Tailscale সেকশন দ্রষ্টব্য)।

### MP3 ক্যাশ পলিসি (GSM ব্রাউজারে বাজে না বলে transcode লাগে)

- হ্যাংআপের পরপরই ব্যাকগ্রাউন্ডে transcode (dialplan-এ হুক না বসিয়ে — `extensions.conf` তখন অন্য কেউ সক্রিয়ভাবে এডিট করছিল বলে ইচ্ছাকৃতভাবে না-ছোঁয়া হয়েছে): `inotifywait` দিয়ে recordings ফোল্ডার watch করা একটা systemd service (`pbx-mp3-cache.service`, script: `mp3_cache_watcher.sh`), `close_write` (আউটগোয়িং ফ্লো) + `moved_to` (ইনকামিং ফ্লো, কারণ `mv` rename করে, close_write ফায়ার করে না) দুটো ইভেন্টই শোনে।
- ক্যাশ **আলাদা ফোল্ডারে** (`/var/spool/asterisk/mp3_cache/`, `recordings/`-এর বাইরে) — কারণ `backup_recordings.sh`-এর rclone source ঠিক `recordings/`, ওখানে MP3 রাখলে প্রতিটা কল দুইবার (GSM+MP3) cloud-এ আপলোড হতো, অপ্রয়োজনীয়।
- TTL **৩০ দিন** (GSM-এর ৯০ দিনের চেয়ে ছোট, ইচ্ছাকৃতভাবে) — ক্যাশ শুধুই speed optimization, disposable, source of truth GSM (৯০ দিন লোকাল + gdrive-এ permanent)। cron: `cleanup_mp3_cache.sh`, 3:20am।
- বিটরেট: 48kbps mono (ভয়েস কলের জন্য যথেষ্ট) — দৈনিক ~180MB নতুন ক্যাশ (৩ এজেন্ট, ~৫০০ মিনিট/দিন), ৩০-দিন উইন্ডোতে সর্বোচ্চ ~৫.৪GB, ডিস্কে (২২৬GB, ~২০৮GB ফাঁকা) নগণ্য।
- প্রথমবার প্লে-তে cache miss হলে bridge.php নিজেই on-demand transcode করে (fallback safety net), তাই watcher ডাউন থাকলেও কাজ বন্ধ হয় না, শুধু প্রথম প্লে-তে ~১-২ সেকেন্ড বেশি লাগে।

### ডিপ্লয়মেন্ট আর্টিফ্যাক্ট

`modules/pbxpopup/deploy/pbx/` -এ রাখা (রেফারেন্সের জন্য, CRM zip-এর কার্যকর অংশ না):
```
bridge.php               — PBX বক্সে /var/www/bridge/bridge.php হিসেবে ডিপ্লয় হয়
mp3_cache_watcher.sh      → /usr/local/bin/
cleanup_mp3_cache.sh      → /usr/local/bin/
pbx-mp3-cache.service     → /etc/systemd/system/
mp3-cache-cleanup.cron    → /etc/cron.d/mp3-cache-cleanup
deploy_pbx.sh             — উপরের সবকিছু + ffmpeg/inotify-tools/php8.4-fpm install +
                            Caddy site block + ufw rules এক কমান্ডে করে
```
**Deploy status: ✅ লাইভ (2026-08-22)।** DNS A record যোগ হয়েছে, `deploy_pbx.sh` PBX বক্সে (192.168.0.154) সফলভাবে রান হয়েছে, CRM মডিউল ফাইল সরাসরি production-এ (`/home/uddoktayon/public_html/my/modules/pbxpopup/`) বসানো হয়েছে। End-to-end verified: CRM-এর শেয়ার্ড হোস্টিং থেকে সরাসরি `curl` করে bridge.php-এর `list`+`stream` দুই action-ই কাজ করে দেখানো হয়েছে।

**ডিপ্লয়ের সময় যে দুটো বাগ পাওয়া গেছে ও ফিক্স হয়েছে (ভবিষ্যতে একই প্যাটার্ন রিইউজ করলে মনে রাখা জরুরি):**
1. **Caddy → PHP-FPM socket permission denied।** PHP-FPM pool `user/group = asterisk` করা হয়েছিল (recordings/CDR পড়ার জন্য), কিন্তু Caddy চলে `caddy` ইউজারে — সে `asterisk:asterisk` মালিকানার সকেটে কানেক্ট করতে পারছিল না। ফিক্স: pool config-এ `listen.group = caddy` (worker process owner অপরিবর্তিত `asterisk` রেখে শুধু socket-এর group বদলানো)।
2. **ffmpeg "Unable to choose an output format"।** temp ফাইলের নাম `....mp3.<pid>.tmp`-এ শেষ হচ্ছিল (অর্ধেক-লেখা ফাইল সার্ভ হওয়া এড়াতে `.tmp` suffix রাখা হয়েছিল) — ffmpeg extension থেকে container format গেস করে, তাই `.tmp`-এ শেষ হওয়া নাম দেখে বুঝতে পারছিল না। ফিক্স: `-f mp3` explicit flag (bridge.php-এর on-demand transcode এবং watcher script দুটোতেই)।

ব্যাকআপ রাখা হয়েছে deploy-এর আগে: `~/module_backups/pbxpopup_backup_20260822_090553.tar.gz` (uddoktayon.com cPanel অ্যাকাউন্টে)।

**তৃতীয় বাগ (deploy-পরবর্তী):** module docblock-এ `Version: 1.0.0 → 2.0.0` বাড়ানোর ফলে Perfex-এর module migration সিস্টেম ট্রিগার হয়ে "No migration could be found with the version number: 200" এরর দেয় (`application/libraries/App_module_migration.php`)। **কারণ:** Perfex-এ module version bump মানেই framework `modules/<name>/migrations/<version_stripped>_version_<version_stripped>.php` (যেমন salesos-এর সমতুল্য অন্য module-এ `134_version_134.php`) ফাইল আশা করে — ফাইল না থাকলে migration ব্যর্থ হয় (তবে DB-তে `installed_version` আপডেট *হয় না*, তাই এটা কখনো stuck/broken state তৈরি করে না, শুধু বারবার এরর দেখাতে থাকে)। **ফিক্স:** যেহেতু pbxpopup-এর কোনো DB স্কিমা/টেবিলই নেই (কোনো `install.php` নেই), migration ফাইল বানানোর বদলে version নম্বর `1.0.0`-এই রেখে দেওয়া হয়েছে — শুধু ফাইল-লেভেল পরিবর্তনে (কোনো নতুন টেবিল/কলাম) version bump করার দরকার নেই এই framework-এ। **শিক্ষা:** এই কোডবেসে কোনো module-এর `Version:` docblock বদলানোর আগে হয় (ক) `installed_version`-এর সাথে মিলিয়ে রাখতে হবে (বদলাবেন না যদি স্কিমা না পাল্টায়), অথবা (খ) মিলিয়ে একটা `migrations/<N>_version_<N>.php` ফাইলও যোগ করতে হবে।

---

*এই ফাইলটি `/home/fizz/Projects/crm/modules/salesos/INFRASTRUCTURE.md` এ সংরক্ষিত।*
*সকল credential পরিবর্তন হলে এই ফাইল আপডেট করো।*
