# Asterisk PBX Setup Guide — CRM SalesOS Integration

**Stack:** AlmaLinux 8.10 · Asterisk 22 · PJSIP · BDIT SIP Trunk  
**উদ্দেশ্য:** নতুন সার্ভারে একই কনফিগে Asterisk PBX দ্রুত সেটআপ করার রেফারেন্স গাইড।

---

## সিস্টেম আর্কিটেকচার

```
Mobile Caller
     ↕ PSTN
BDIT SIP Trunk (103.170.231.10)
     ↕ SIP/UDP 5060
Asterisk PBX (এই সার্ভার)
     ↕ SIP
SIP Clients: Linphone (1001) · MicroSIP (1002) · Zoiper (1003+)
     ↕ AMI/ARI
CRM Server (Perfex + SalesOS Module)
```

---

## ১. সার্ভার রিকোয়ারমেন্ট

| Item | Specification |
|------|--------------|
| OS | AlmaLinux 8.x / Rocky Linux 8.x |
| RAM | ন্যূনতম 2GB (প্রোডাকশনে 4GB) |
| Disk | ন্যূনতম 20GB (recording রাখলে আরো বেশি) |
| Public IP | Static IP প্রয়োজন (BDIT whitelisting) |
| Ports | UDP/TCP 5060, UDP 10000-20000 (RTP) |

---

## ২. Asterisk ইনস্টলেশন

### ২.১ Dependencies

```bash
dnf install -y epel-release
dnf install -y gcc gcc-c++ make wget openssl-devel \
  libxml2-devel ncurses-devel sqlite-devel libuuid-devel \
  jansson-devel libedit-devel
```

### ২.২ Asterisk 22 ডাউনলোড ও কম্পাইল

```bash
cd /usr/src
wget https://downloads.asterisk.org/pub/telephony/asterisk/asterisk-22-current.tar.gz
tar xzf asterisk-22-current.tar.gz
cd asterisk-22*/

# PJPROJECT ডাউনলোড (Asterisk এর সাথে বান্ডেল)
contrib/scripts/get_mp3_source.sh

# Configure
./configure --with-jansson-bundled

# Menuselect — res_pjsip, app_mixmonitor, res_ari নিশ্চিত করো
make menuselect
# নিচের items enable থাকতে হবে:
#   Codec Translators: codec_ulaw, codec_alaw, codec_g722
#   Applications: app_dial, app_playback, app_mixmonitor, app_stasis
#   Resources: res_pjsip*, res_ari*, res_stasis*
#   CDR: cdr_csv, cdr_odbc (optional)

make -j$(nproc)
make install
make samples       # ডিফল্ট config ফাইল জেনারেট করে
make config        # systemd service ইনস্টল করে
ldconfig
```

### ২.৩ Asterisk User

```bash
useradd -r -d /var/lib/asterisk -M asterisk
chown -R asterisk:asterisk /etc/asterisk /var/lib/asterisk \
  /var/log/asterisk /var/spool/asterisk /usr/lib/asterisk
```

### ২.৪ Recording ডিরেক্টরি

```bash
mkdir -p /var/spool/asterisk/recording
chown asterisk:asterisk /var/spool/asterisk/recording
```

---

## ৩. Asterisk কনফিগারেশন

### ৩.১ `/etc/asterisk/asterisk.conf`

```ini
[directories]
astetcdir => /etc/asterisk
astmoddir => /usr/lib/asterisk/modules
astvarlibdir => /var/lib/asterisk
astdbdir => /var/lib/asterisk
astkeydir => /var/lib/asterisk
astdatadir => /var/lib/asterisk
astagidir => /var/lib/asterisk/agi-bin
astspooldir => /var/spool/asterisk
astrundir => /var/run/asterisk
astlogdir => /var/log/asterisk

[options]
verbose = 0
debug = 0
```

### ৩.২ `/etc/asterisk/logger.conf`

> ⚠️ **সতর্কতা:** `verbose` এবং `debug` প্রোডাকশনে কখনো রাখবে না। ৫ দিনে ৭৭GB লগ জমেছিল এই কারণে।

```ini
[general]
rotatestrategy = rotate
appendhostname = no
dateformat = %F %T

[logfiles]
console => notice,warning,error
messages => notice,warning,error
full    => notice,warning,error
security => security

[colors]
notice  => WHITE
warning => YELLOW
error   => RED
```

### ৩.৩ `/etc/asterisk/modules.conf`

```ini
[modules]
autoload = yes

; PJSIP (বাধ্যতামূলক)
load = res_pjproject.so
load = res_pjsip.so
load = res_pjsip_authenticator_digest.so
load = res_pjsip_caller_id.so
load = res_pjsip_dtmf_info.so
load = res_pjsip_endpoint_identifier_anonymous.so
load = res_pjsip_endpoint_identifier_ip.so
load = res_pjsip_endpoint_identifier_user.so
load = res_pjsip_header_funcs.so
load = res_pjsip_logger.so
load = res_pjsip_nat.so
load = res_pjsip_outbound_authenticator_digest.so
load = res_pjsip_outbound_registration.so
load = res_pjsip_refer.so
load = res_pjsip_rfc3326.so
load = res_pjsip_sdp_rtp.so
load = res_pjsip_session.so
load = res_pjsip_mwi.so
load = res_pjsip_notify.so
load = res_pjsip_path.so

; Security
load = res_crypto.so
load = res_security_log.so

; WebSocket/WebRTC
load = res_http_websocket.so
```

### ৩.৪ `/etc/asterisk/pjsip.conf`

```ini
;====================
; PJSIP Global
;====================
[global]
type = global
user_agent = PBX-CRM/22
endpoint_identifier_order = ip,username,anonymous
debug = no

;====================
; Transport — UDP (প্রধান)
;====================
[transport-udp]
type = transport
protocol = udp
bind = 0.0.0.0:5060
allow_reload = yes
tos = cs3
cos = 3

;====================
; Transport — TCP
;====================
[transport-tcp]
type = transport
protocol = tcp
bind = 0.0.0.0:5060

;====================
; Transport — TLS (সার্টিফিকেট থাকলে activate করো)
;====================
[transport-tls]
type = transport
protocol = tls
bind = 0.0.0.0:5061
cert_file = /etc/asterisk/keys/asterisk.crt
priv_key_file = /etc/asterisk/keys/asterisk.key
method = tlsv1_2
verify_server = no
verify_client = no

;====================
; Transport — WebSocket (WebRTC-এর জন্য)
;====================
[transport-wss]
type = transport
protocol = wss
bind = 0.0.0.0

;====================
; Transport — Alternate Port 5080 (ISP SIP block workaround)
;====================
[transport-udp-5080]
type = transport
protocol = udp
bind = 0.0.0.0:5080
allow_reload = yes

[transport-tcp-5080]
type = transport
protocol = tcp
bind = 0.0.0.0:5080

;====================
; Agent Template (template হিসেবে কাজ করে)
;====================
[agent-template](!)
type = endpoint
context = from-internal
allow = !all,ulaw,alaw,g722,opus
direct_media = no
force_rport = yes          ; NAT-এর পেছনে থাকলে বাধ্যতামূলক
rtp_symmetric = yes        ; NAT traversal
rewrite_contact = yes      ; Dynamic IP handle করে
send_rpid = yes
send_pai = yes
trust_id_inbound = yes
dtmf_mode = rfc4733
media_encryption = no
language = en
tone_zone = us
timers = yes
timers_min_se = 90
timers_sess_expires = 1800

[aor-template](!)
type = aor
max_contacts = 2           ; একটা extension-এ সর্বোচ্চ ২টি ডিভাইস
minimum_expiration = 60
default_expiration = 3600
qualify_frequency = 30     ; প্রতি ৩০ সেকেন্ডে OPTIONS পাঠিয়ে alive চেক করে
remove_existing = yes

[auth-template](!)
type = auth
auth_type = userpass

;====================
; Agents (1001–1010)
; নতুন agent যোগ করতে এই pattern follow করো
;====================
[1001](agent-template)
auth = auth1001
aors = 1001
callerid = "Agent 1001" <1001>

[auth1001](auth-template)
username = 1001
password = Agent1001#Pbx!   ; পরিবর্তন করো

[1001](aor-template)

[1002](agent-template)
auth = auth1002
aors = 1002
callerid = "Agent 1002" <1002>

[auth1002](auth-template)
username = 1002
password = Agent1002#Pbx!

[1002](aor-template)

; ১০০৩–১০১০ একই pattern-এ যোগ করো...

;====================
; BDIT SIP Trunk
;====================

; Outbound Registration (Asterisk → BDIT)
[trunk-bdit]
type = registration
transport = transport-udp
outbound_auth = auth-trunk-bdit
server_uri = sip:103.170.231.10         ; BDIT সার্ভার IP
client_uri = sip:09649699699@103.170.231.10   ; আমাদের DID নম্বর
retry_interval = 60
max_retries = 10
expiration = 3600
outbound_proxy = sip:103.170.231.10\;lr
contact_user = 09649699699

; Trunk Authentication
[auth-trunk-bdit]
type = auth
auth_type = userpass
username = 09649699699
password = #bdit9933                     ; BDIT দেওয়া password

; Trunk AOR (inbound-এর জন্য)
[aor-trunk-bdit]
max_contacts = 1
type = aor
contact = sip:103.170.231.10

; Trunk Endpoint
[endpoint-trunk-bdit]
type = endpoint
transport = transport-udp
context = from-trunk                     ; inbound call এই context-এ যাবে
allow = !all,ulaw,alaw,g722,g729
outbound_auth = auth-trunk-bdit
aors = aor-trunk-bdit
from_user = 09649699699
from_domain = 103.170.231.10
direct_media = no
force_rport = yes
rtp_symmetric = yes
rewrite_contact = yes
callerid = "09649699699" <09649699699>

; IP-based Trunk Identification (BDIT এর IP থেকে যা আসবে সেটা এই trunk)
[identify-trunk-bdit]
type = identify
endpoint = endpoint-trunk-bdit
match = 103.170.231.10                   ; BDIT সার্ভার IP whitelist
```

### ৩.৫ `/etc/asterisk/extensions.conf`

```ini
[general]
static = yes
writeprotect = no
autofallthrough = yes

[globals]
RECORDING_DIR=/var/spool/asterisk/recording
TRUNK=endpoint-trunk-bdit

;====================
; Internal Calls (1001–1010)
;====================
[from-internal]
exten => _1XXX,1,NoOp(Internal: ${CALLERID(num)} → ${EXTEN})
 same => n,Set(CDR(accountcode)=${CALLERID(num)})
 same => n,Dial(PJSIP/${EXTEN},30,rTt)
 same => n,Hangup()

; Outbound — BD Mobile (013/014/015/016/017/018/019)
exten => _0[1][3-9]XXXXXXXX,1,NoOp(Outbound BD: ${EXTEN})
 same => n,Set(CDR(accountcode)=${CALLERID(num)})
 same => n,Set(CALLERID(num)=09649699699)          ; Trunk CLI
 same => n,Set(RECORDING_FILE=${RECORDING_DIR}/${STRFTIME(${EPOCH},,%Y%m%d-%H%M%S)}-${EXTEN})
 same => n,MixMonitor(${RECORDING_FILE}.wav,b,)    ; Call recording
 same => n,Set(CDR(recordingfile)=${RECORDING_FILE}.wav)
 same => n,Set(CDR(userfield)=${RECORDING_FILE}.wav)
 same => n,Dial(PJSIP/${EXTEN}@${TRUNK},60,rTt)
 same => n,Hangup()

; Outbound — 09 Hotline Numbers
exten => _09XXXXXXXX,1,NoOp(Outbound 09: ${EXTEN})
 same => n,Set(CALLERID(num)=09649699699)
 same => n,Dial(PJSIP/${EXTEN}@${TRUNK},60,rTt)
 same => n,Hangup()

; Echo Test
exten => 9999,1,Answer()
 same => n,Echo()
 same => n,Hangup()

;====================
; Inbound from BDIT Trunk
;====================
[from-trunk]
exten => _X.,1,NoOp(Inbound: ${CALLERID(num)} → ${EXTEN})
 same => n,Set(CDR(accountcode)=inbound)
 same => n,Set(RECORDING_FILE=${RECORDING_DIR}/${STRFTIME(${EPOCH},,%Y%m%d-%H%M%S)}-${CALLERID(num)})
 same => n,MixMonitor(${RECORDING_FILE}.wav,b,)
 same => n,Set(CDR(recordingfile)=${RECORDING_FILE}.wav)
 same => n,Set(CDR(userfield)=${RECORDING_FILE}.wav)
 same => n,Dial(PJSIP/1001&PJSIP/1002,30,rTt)     ; 1001 ও 1002 একসাথে ring করে
 same => n,Hangup()

exten => s,1,GoTo(from-trunk,_X.,1)

;====================
; ARI — SalesOS CRM Integration
;====================
[stasis-crm]
exten => _X.,1,Stasis(crm-app,${EXTEN})
 same => n,Hangup()
```

### ৩.৬ `/etc/asterisk/ari.conf`

```ini
[general]
enabled = yes
pretty = no
allowed_origins = *

[crm-ari]
type = user
read_only = no
password = CRM_ARI_S3cr3t#2024
```

### ৩.৭ `/etc/asterisk/manager.conf` (AMI)

```ini
[general]
enabled = yes
port = 5038
bindaddr = 0.0.0.0

[crm-api]
secret = CRM_AMI_S3cr3t#2024
permit = 103.198.133.243/255.255.255.255   ; CRM সার্ভার IP
read = all
write = all
```

---

## ৪. Systemd Service

### `/etc/systemd/system/asterisk.service`

```ini
[Unit]
Description=Asterisk PBX
After=network.target mariadb.service redis.service
Wants=mariadb.service redis.service

[Service]
Type=simple
User=asterisk
Group=asterisk
ExecStart=/usr/sbin/asterisk -f -C /etc/asterisk/asterisk.conf
ExecReload=/usr/sbin/asterisk -rx "core reload"
ExecStop=/usr/sbin/asterisk -rx "core stop gracefully"
Restart=always
RestartSec=5
LimitCORE=infinity
LimitNOFILE=65536
LimitNPROC=8192

[Install]
WantedBy=multi-user.target
```

```bash
systemctl daemon-reload
systemctl enable asterisk
systemctl start asterisk
```

---

## ৫. Security — Fail2ban

### `/etc/fail2ban/jail.local`

```ini
[DEFAULT]
bantime = 86400       ; 24 ঘন্টা (আগে 1 ঘন্টা ছিল, সেটা কারণে scanners বার বার আসত)
findtime = 300
maxretry = 5
ignoreip = 127.0.0.1/8 ::1
           103.170.231.10      ; BDIT Trunk — কখনো block করবে না
           103.198.133.243     ; CRM Server

[sshd]
enabled = true
port = ssh
logpath = %(sshd_log)s
backend = %(sshd_backend)s
maxretry = 5
bantime = 86400
findtime = 600

[asterisk]
enabled = true
port = 5060,5061,5080
action_ = %(default/action_)s[name=%(name)s-tcp, protocol="tcp"]
          %(default/action_)s[name=%(name)s-udp, protocol="udp"]
logpath = /var/log/asterisk/full
maxretry = 5
bantime = 86400
findtime = 300
```

> **গুরুত্বপূর্ণ:** `ignoreip`-এ BDIT Trunk IP এবং SIP client IP যোগ করো। না হলে fail2ban তাদেরও block করে দিতে পারে।

```bash
dnf install -y fail2ban
systemctl enable fail2ban
systemctl start fail2ban
```

---

## ৬. Log Rotation

### `/etc/logrotate.d/asterisk`

```
/var/log/asterisk/full
/var/log/asterisk/messages
/var/log/asterisk/security
{
    daily
    rotate 7
    compress
    delaycompress
    missingok
    notifempty
    create 0640 asterisk asterisk
    postrotate
        /usr/sbin/asterisk -rx "logger reload" > /dev/null 2>&1 || true
    endscript
}
```

---

## ৭. SIP Client সেটআপ

### MicroSIP (Windows)

| Field | Value |
|-------|-------|
| SIP Server | `103.42.4.210` |
| Domain | `103.42.4.210` |
| Username | `1001` (বা 1002, 1003...) |
| Password | `Agent1001#Pbx!` |
| Transport | `UDP` |

### Linphone (Linux/Mac/Windows)

- Account → Edit → SIP Address: `sip:1001@103.42.4.210`
- Password: `Agent1001#Pbx!`
- Transport: **TCP** (NAT-এর পেছনে হলে TCP বেশি reliable)
- STUN: `stun.l.google.com:19302` (optional, NAT traversal)

### Zoiper (Mobile)

- Username: `1001`
- Password: `Agent1001#Pbx!`
- Hostname: `103.42.4.210`
- Port: `5060`
- Transport: `UDP`

> **NAT সমস্যা:** Client "offline" দেখালেও Asterisk-এ registered থাকতে পারে।  
> TCP transport বা STUN enable করলে এই সমস্যা কমে।

---

## ৮. BDIT SIP Trunk

### BDIT একাউন্ট তথ্য

| Item | Value |
|------|-------|
| DID Number | `09649699699` |
| SIP Server | `103.170.231.10` |
| Username | `09649699699` |
| Password | `#bdit9933` |

### গুরুত্বপূর্ণ বিষয়

1. **Outbound** — Asterisk BDIT-এ REGISTER করে (`trunk-bdit`), তারপর সেখান দিয়ে call যায়।
2. **Inbound** — BDIT আমাদের server-এর Contact address-এ INVITE পাঠায়।
3. **Balance শেষ হলে** — Outbound-এ `402 Payment Required` এবং Inbound-এ "switched off" দেখায়। উভয়ের solution: BDIT একাউন্ট recharge।
4. **`pjsip send register trunk-bdit`** command দিলে আগে Expires: 0 (un-register) যায়, তারপর re-register হয় — এই সময় briefly "switched off" দেখাতে পারে।

---

## ৯. AMI/ARI — CRM সংযোগ

| Item | Value |
|------|-------|
| AMI Host | `103.42.4.210:5038` |
| AMI User | `crm-api` |
| AMI Password | `CRM_AMI_S3cr3t#2024` |
| ARI URL | `http://103.42.4.210:8088/ari/` |
| ARI User | `crm-ari` |
| ARI Password | `CRM_ARI_S3cr3t#2024` |

CRM server IP (`103.198.133.243`) AMI-তে whitelisted থাকতে হবে।

---

## ১০. Call Flow ডায়াগ্রাম

### Inbound (বাইরে থেকে call আসলে)

```
Mobile → BDIT (103.170.231.10)
         → INVITE sip:09649699699@103.42.4.210:5060
         → [identify-trunk-bdit] IP match → endpoint-trunk-bdit
         → context: from-trunk
         → Dial(PJSIP/1001&PJSIP/1002, 30s)
         → 1001 (Linphone) + 1002 (MicroSIP) একসাথে ring
         → যে আগে ধরে সে কথা বলে
```

### Outbound (agent থেকে call দিলে)

```
MicroSIP/Linphone → INVITE sip:01717951166@103.42.4.210
         → [auth1002] authenticate
         → context: from-internal
         → pattern _0[1][3-9]XXXXXXXX match
         → Set CALLERID = 09649699699
         → MixMonitor শুরু (recording)
         → Dial(PJSIP/01717951166@endpoint-trunk-bdit)
         → BDIT → 401 → re-INVITE with auth → 183 Ringing → connected
```

---

## ১১. ইনস্টলেশন ভেরিফিকেশন চেকলিস্ট

নতুন সার্ভারে সেটআপের পরে এগুলো চেক করো:

```bash
# ১. Asterisk চলছে?
systemctl status asterisk

# ২. Trunk registered?
asterisk -rx "pjsip show registrations"
# expected: trunk-bdit ... Registered

# ৩. SIP clients connected?
asterisk -rx "pjsip show contacts"
# expected: 1001, 1002 → Avail

# ৪. Dialplan loaded?
asterisk -rx "dialplan show from-internal"
asterisk -rx "dialplan show from-trunk"

# ৫. Fail2ban চলছে?
systemctl status fail2ban
fail2ban-client status asterisk

# ৬. Port 5060 open?
ss -ulnp | grep 5060
ss -tlnp | grep 5060

# ৭. Echo test (call 9999 from any SIP client)
# নিজের কণ্ঠস্বর ফিরে আসলে সব ঠিক আছে
```

---

## ১২. সাধারণ সমস্যা ও সমাধান

### "Switched Off" শোনা যাচ্ছে (inbound)

| কারণ | সমাধান |
|------|--------|
| BDIT balance শেষ | BDIT একাউন্ট recharge করো |
| trunk register হয়নি | `asterisk -rx "pjsip show registrations"` চেক করো |
| BDIT-এর inbound routing নেই | BDIT support-কে `103.42.x.x:5060` configure করতে বলো |

### "Server Internal Error" (outbound)

| কারণ | সমাধান |
|------|--------|
| BDIT balance শেষ | Recharge করো |
| Trunk endpoint নেই | `asterisk -rx "pjsip show endpoints"` চেক করো |
| Wrong number format | `_0[1][3-9]XXXXXXXX` pattern মেলে কিনা দেখো |

### Linphone/MicroSIP "Offline" দেখাচ্ছে

| কারণ | সমাধান |
|------|--------|
| REGISTER response NAT পার করছে না | TCP transport ব্যবহার করো |
| IP changed | Linphone restart করো |
| fail2ban block | `iptables -L -n \| grep <client-ip>` |

> **মনে রেখো:** Linphone "offline" দেখালেও Asterisk-এ registered থাকতে পারে।  
> `asterisk -rx "pjsip show contacts"` দিয়ে server-side status দেখো।

### Log বড় হয়ে যাচ্ছে

```bash
# কারণ ১: PJSIP logger চালু
asterisk -rx "pjsip set logger off"

# কারণ ২: logger.conf-এ verbose/debug
# full => notice,warning,error  ← এটাই রাখো, verbose/debug সরাও

# লগ পরিষ্কার
> /var/log/asterisk/full
> /var/log/asterisk/messages
> /var/log/asterisk/security
asterisk -rx "logger reload"
# Note: rotate হলে .0 ফাইল তৈরি হবে, সেটাও delete করো
```

---

## ১৩. দরকারি Asterisk CLI Commands

```bash
# Registration status
asterisk -rx "pjsip show registrations"

# সব endpoint
asterisk -rx "pjsip show endpoints"

# সব registered contact
asterisk -rx "pjsip show contacts"

# Live call দেখো
asterisk -rx "core show channels"

# Dialplan
asterisk -rx "dialplan show from-internal"
asterisk -rx "dialplan show from-trunk"
asterisk -rx "dialplan reload"

# Debug (সাময়িক — শেষে অবশ্যই off করো)
asterisk -rx "pjsip set logger on"
# ... debug শেষে:
asterisk -rx "pjsip set logger off"

# Trunk force re-register
asterisk -rx "pjsip send register trunk-bdit"

# Log reload
asterisk -rx "logger reload"
```

---

## ১৪. নিরাপত্তা চেকলিস্ট

- [ ] সব agent password পরিবর্তন করো (`Agent100X#Pbx!` → কাস্টম)
- [ ] AMI password পরিবর্তন করো
- [ ] ARI password পরিবর্তন করো
- [ ] Fail2ban `ignoreip`-এ trusted IP যোগ করো
- [ ] `pjsip.conf`-এ `debug = no` নিশ্চিত করো
- [ ] `logger.conf`-এ `verbose`/`debug` নেই নিশ্চিত করো
- [ ] Port 5060 কে শুধু প্রয়োজনীয় IP-তে সীমাবদ্ধ করো (optional, firewall-এ)
- [ ] Recording directory permission: `chown asterisk:asterisk`

---

## ১৫. Extension যোগ করা (নতুন Agent)

উদাহরণ: 1003 যোগ করতে `pjsip.conf`-এ:

```ini
[1003](agent-template)
auth = auth1003
aors = 1003
callerid = "Agent 1003" <1003>

[auth1003](auth-template)
username = 1003
password = Agent1003#Pbx!   ; পরিবর্তন করো

[1003](aor-template)
```

তারপর reload:
```bash
asterisk -rx "module reload res_pjsip.so"
```

Inbound-এ এই extension যোগ করতে `extensions.conf`:
```ini
same => n,Dial(PJSIP/1001&PJSIP/1002&PJSIP/1003,30,rTt)
```
তারপর:
```bash
asterisk -rx "dialplan reload"
```
