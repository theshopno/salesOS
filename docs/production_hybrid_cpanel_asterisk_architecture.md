# Production Hybrid Architecture Plan: cPanel CRM + Asterisk Cloud VPS

**Document Status**: ARCHITECTURAL SPECIFICATION & FUTURE-PROOF BLUEPRINT  
**Target Environment**: CRM on Managed/Shared cPanel Hosting | Asterisk on Dedicated Cloud VPS (New Server)  
**Creation Date**: 2026-09-18  

---

## 1. Executive Summary & Context (প্রেক্ষাপট)

বর্তমানে এই সিআরএম এবং PBX সিস্টেমটি লোকাল ডেভেলপমেন্ট ল্যাপটপে টেস্ট ও ডেভ করার জন্য কনফিগার করা আছে:
1. CRM চলছে লোকালহোস্টে এবং Cloudflare Tunnel দিয়ে `crm.bizyto.com` ডোমেইনে রাউট হচ্ছে।
2. Asterisk PBX চলছে মুনজু (`103.187.23.39`) টেস্ট সার্ভারে, যা লোকাল SSH টানেলের মাধ্যমে পোর্ট `15038`, `23306`, `18088`-এ ফরওয়ার্ড করা।
3. ব্যাকগ্রাউন্ড ডেমন (`ami_consumer.php`) লোকাল ল্যাপটপের systemd সার্ভিসে চলছে।

**ভবিষ্যতের বাস্তবতা (Future Production Reality):**
- **CRM:** পরিচালিত হবে ক্লায়েন্টের স্ট্যান্ডার্ড **cPanel হোস্টিং**-এ (PHP 8.1+, MySQL, Apache/Litespeed, কোনো রুট এক্সেস বা persistent SSH tunnel থাকবে না)।
- **Asterisk PBX:** বর্তমান মুনজু টেস্ট সার্ভার নয়, বরং প্রোডাকশনের জন্য কেনা **সম্পূর্ণ নতুন একটি Cloud VPS** (DigitalOcean / Hetzner / AWS / BD VPS) এ ডেডিকেটেডভাবে হোস্ট করা হবে।
- এই দুটি সম্পূর্ণ আলাদা সার্ভার এনভায়রনমেন্টে কীভাবে পুরো পাইপলাইন (IVR Call, Audio Prompts, Click-to-Call, Webhook) কোনো ভাঙন বা ঝামেলা ছাড়াই প্লাগ-অ্যান্ড-প্লে ভাবে চলবে—এটি তারই চূড়ান্ত ব্লুপ্রিন্ট।

---

## 2. In-Depth Critique of Current Codebase (বাস্তব সমস্যা ও বর্তমান লিমিটেশন)

আমাদের বর্তমান কোডবেস বিশ্লেষণ করে প্রোডাকশন মাইগ্রেশনের ক্ষেত্রে **৫টি প্রধান বাধা** পাওয়া গেছে:

```
+------------------------------------------------------------------------------------+
|                               CURRENT DEV COUPLING                                 |
+------------------------------------------------------------------------------------+
| 1. AMI Ports:      Hardcoded '127.0.0.1:15038' in Settings.php load_preset()      |
| 2. AMI Consumer:   Runs as local systemd service on laptop; posts to 'crm.test'    |
| 3. Audio Service:  Executes local ffmpeg binary & SCPs using ~/.ssh/config alias   |
| 4. Asterisk Conf:  Manually tuned on Munzu PBX; missing on any new VPS             |
| 5. cPanel Limits:  disable_functions (exec, shell_exec), no daemon backgrounding  |
+------------------------------------------------------------------------------------+
```

### ক্রিটিসিজম ১: cPanel হোস্টিংয়ে ব্যাকগ্রাউন্ড ডেমন চলতে পারে না
- **সমস্যা:** বর্তমানে `salesos-ami-consumer.service` একটি systemd সার্ভিস হিসেবে ২৪ ঘণ্টা ল্যাপটপে ব্যাকগ্রাউন্ডে চলছে।
- **বাস্তব সত্য:** স্ট্যান্ডার্ড cPanel হোস্টিংয়ে কোনো ইউজার root পারমিশন পায় না এবং কোনো লং-রানিং systemd ডেমন চালাতে পারে না। cPanel-এর ক্রনজব প্রতি ১ মিনিটে একবার স্ক্রিপ্ট চালু করে শেষ করে দেয়; এটি দিয়ে ২৪ ঘণ্টা সকেট কানেকশন ধরে রাখা সম্ভব নয়।
- **সমাধান প্রয়োজন:** ইভেন্ট লিসেনিংয়ের দায়িত্ব cPanel থেকে সরিয়ে **Asterisk VPS-এর নিজস্ব কাঁধে** দিতে হবে।

### ক্রিটিসিজম ২: `ami_consumer.php` ফাইলে হার্ডকোডেড `crm.test`
- **সমস্যা:** `modules/pbxpilot/daemons/ami_consumer.php` লাইন ১৮২-তে সিআরএম-এর কাছে IVR কনফার্মেশন পাঠানোর জন্য লেখা:
  ```php
  $webhook_url = 'http://127.0.0.1/pbxpilot/ivr_webhook/order_status';
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Host: crm.test']);
  ```
- **বাস্তব সত্য:** রিয়েল হোস্টিংয়ে `crm.test` ডোমেইন থাকবে না। ফলে কাস্টমার ফোনে ১ চাপলেও সিআরএম কোনো রেসপন্স পাবে না এবং অর্ডার কনফার্ম হবে না।

### ক্রিটিসিজম ৩: অডিও আপলোডে `ffmpeg` ও `scp munzu` নির্ভরতা
- **সমস্যা:** `Audio_service.php` অডিও ফাইল টেলিফোনি ফরম্যাটে রূপান্তর করতে লোকাল `/usr/bin/ffmpeg` ব্যবহার করে এবং `scp ... munzu:/usr/share/asterisk/sounds/custom/` কমান্ড চালায়।
- **বাস্তব সত্য:** 
  1. বেশিরভাগ cPanel শেয়ার্ড হোস্টিংয়ে সিকিউরিটির জন্য PHP-র `exec()`, `shell_exec()`, `system()` ফাংশনগুলো ব্লক করা থাকে (`disable_functions` ইন `php.ini`)।
  2. cPanel সার্ভারে `ffmpeg` ইনস্টল করা থাকে না।
  3. cPanel-এ `munzu` নামের কোনো SSH হোস্ট অ্যালিয়াস বা SSH প্রাইভেট কী থাকবে না।

### ক্রিটিসিজম ৪: One-Click Presets হার্ডকোডেড লোকালহোস্টে সীমাবদ্ধ
- **সমস্যা:** `Settings.php` এর `load_preset()` ফাংশন লোকাল টানেল আইপি `127.0.0.1` এবং পোর্ট `15038` ডাটাবেজে সেভ করে।
- **বাস্তব সত্য:** নতুন cPanel থেকে `127.0.0.1:15038` কল করলে তাত্ক্ষণিক `Connection Refused` এরর আসবে। কারণ নতুন সিআরএম-এ কোনো লোকাল এসএসএইচ টানেল থাকবে না।

### ক্রিটিসিজম ৫: নতুন Asterisk VPS-এর শূন্য কনফিগারেশন
- **বাস্তব সত্য:** নতুন কোনো Asterisk VPS কিনলে সেখানে সিআরএম-এর কোনো ডায়ালপ্ল্যান (`[ivr-order-confirm]`), কোনো AMI ইউজার (`crm-api`), কোনো সাউন্ড ফাইল বা ডাটাবেজ টেবিল কিছুই থাকবে না। ম্যানুয়ালি এগুলো করতে গেলে নন-টেকনিক্যাল ক্লায়েন্ট সম্পূর্ণ ব্যর্থ হবে।

---

## 3. The Future-Proof Architecture (প্রোডাকশন হাইব্রিড মডেল)

```mermaid
graph TD
    subgraph cPanel_Web_Tier [cPanel Hosting - CRM Tier (Stateless)]
        CRM[Perfex CRM Web Panel]
        DB[(cPanel MySQL)]
        CRM_Cron[cPanel 1-Min Cron Sweeper]
        IVR_Hook[Endpoint: /pbxpilot/ivr_webhook/order_status]
    end

    subgraph Cloud_VPS [Dedicated Asterisk Cloud VPS (Stateful)]
        Asterisk[Asterisk Core 18/20]
        Dialplan[extensions_custom.conf / ivr-order-confirm]
        Sounds[/usr/share/asterisk/sounds/custom/*.wav]
        VPS_Daemon[Local Event Dispatcher or func_curl]
        SIP_Trunk[Telco SIP Trunk: AmberIT / BTCL / Ecare]
    end

    Customer((Customer Phone)) <===>|SIP / Audio| Asterisk
    CRM -->|1. AMI Originate via TLS / TCP:5038| Asterisk
    Asterisk -->|2. Native func_curl or HTTPS Webhook| IVR_Hook
    IVR_Hook -->|3. Auto-Confirm Order & Trigger WhatsApp| DB
```

### আর্কিটেকচারাল মূলনীতি (Architectural Principles):

1. **cPanel হবে সম্পূর্ণ "Stateless Web Tier":**
   - সিআরএম কোনো ব্যাকগ্রাউন্ড সকেট বা ডেমন চালাবে না।
   - সিআরএম কেবল HTTP রিকোয়েস্ট গ্রহণ করবে এবং বহির্গামী কলের প্রয়োজন হলে Asterisk AMI-তে একটি শর্ট-লাইভড কমান্ড পাঠিয়ে সংযোগ বিচ্ছিন্ন করবে।
2. **Asterisk VPS হবে "Independent Telephony Engine":**
   - কল প্রসেসিং, IVR ইন্টারঅ্যাকশন, অডিও প্রম্পট প্লেব্যাক এবং ডেমন সম্পূর্ণভাবে VPS-এর ভেতরেই থাকবে।
   - কল শেষে কাস্টমার কী চাপল—তা Asterisk সরাসরি সিআরএম-এর পাবলিক HTTPS ওয়েবহুকে পুশ করবে।

---

## 4. Solving the 4 Critical Challenges (স্মার্ট সমাধানসমূহ)

---

### চ্যালেঞ্জ ১: cPanel ডেমন সমস্যা দূরীকরণ (Eliminating the Daemon)

#### স্মার্ট সমাধান: Asterisk Native `func_curl` (Zero Daemon Architecture)
আমাদের সিআরএম-এ কোনো ডেমন চালানোরই দরকার নেই! Asterisk-এর নিজস্ব ডায়ালপ্ল্যানেই বিল্ট-ইন `CURL()` ফাংশন রয়েছে। 

যখন কাস্টমার ১ বা ২ চাপবে, Asterisk স্বয়ংক্রিয়ভাবে সরাসরি সিআরএম-এর HTTPS ওয়েবহুক কল করবে:

```ini
; /etc/asterisk/extensions_custom.conf
[ivr-order-confirm]
exten => s,1,Answer()
same => n,Wait(1)
same => n,Read(CONFIRM_DIGIT,${PROMPT_MAIN},1,,,8)
same => n,GotoIf($["${CONFIRM_DIGIT}" = "1"]?confirm)
same => n,GotoIf($["${CONFIRM_DIGIT}" = "2"]?cancel)
same => n,Goto(failed)

same => n(confirm),Playback(${PROMPT_CONFIRM})
; Asterisk নিজেই সরাসরি cPanel CRM HTTPS Webhook কল করবে (No Daemon Needed!)
same => n,Set(CURL_RES=${CURL(https://crm.yourdomain.com/pbxpilot/ivr_webhook/order_status,token=pbxpilot_ivr_internal_secret_key_88&order_id=${ORDER_ID}&status=confirmed&digit=1&phone=${CUSTOMER_PHONE})})
same => n,Hangup()

same => n(cancel),Playback(${PROMPT_CANCEL})
same => n,Set(CURL_RES=${CURL(https://crm.yourdomain.com/pbxpilot/ivr_webhook/order_status,token=pbxpilot_ivr_internal_secret_key_88&order_id=${ORDER_ID}&status=cancelled&digit=2&phone=${CUSTOMER_PHONE})})
same => n,Hangup()

same => n(failed),NoOp(Order Call Failed)
same => n,Set(CURL_RES=${CURL(https://crm.yourdomain.com/pbxpilot/ivr_webhook/order_status,token=pbxpilot_ivr_internal_secret_key_88&order_id=${ORDER_ID}&status=failed&digit=&phone=${CUSTOMER_PHONE})})
same => n,Hangup()
```

> [!TIP]
> **কেন এটি শ্রেষ্ঠ?**
> এর ফলে cPanel-এ কোনো ডেমন লাগে না, SSH টানেল লাগে না। কল শেষ হওয়ামাত্রই Asterisk সাধারণ ব্রাউজারের মতো সিআরএম-এর লিংকে ডাটা পাঠিয়ে দেয়। শতভাগ নিরবচ্ছিন্ন এবং কখনো ক্র্যাশ করার ভয় নেই।

---

### চ্যালেঞ্জ ২: cPanel থেকে অডিও আপলোড ও ডিপ্লয়মেন্ট সমাধান

যেহেতু cPanel-এ `ffmpeg` বা `scp` থাকে না, তাই অডিও তিনটি স্মার্ট স্তরে হ্যান্ডেল করা হবে:

#### স্তর ১: ডিফল্ট অডিও বান্ডেল (Guaranteed Out-of-the-Box Audio)
- মডিউলের ভেতরেই ৩টি স্ট্যান্ডার্ড বাংলা প্রম্পট (`ivr_confirmation.wav`, `ivr_press_1.wav`, `ivr_press_2.wav`) প্রি-কনভার্ট করা ৮kHz ১৬-বিট মনো ফরম্যাটে প্যাকেজ করা থাকবে।
- নতুন কোনো অডিও আপলোড না করলেও সিস্টেম অবিলম্বে পুরোপুরি কাজ করবে।

#### স্তর ২: Asterisk VPS Micro-Receiver (স্বয়ংক্রিয় ক্লাউড সিঙ্ক)
- Asterisk VPS-এ একটি ছোট, সুরক্ষিত পাইথন/পিএইচপি রিসিভার সার্ভিস চলবে (যেমন পোর্ট `18089` বা Nginx রিভার্স প্রক্সি দিয়ে HTTPS-এ)।
- cPanel যখনই কোনো অডিও আপলোড পাবে, cPanel সাধারণ `curl -F file=@audio.mp3 https://asterisk.yourdomain.com/upload` এর মাধ্যমে অডিওটি VPS-এ পাঠিয়ে দেবে।
- VPS তখন নিজের `ffmpeg` দিয়ে ট্রান্সকোড করে লোকাল সাউন্ড ফোল্ডারে রেখে দেবে। কোনো SSH বা SCP-র দরকার পড়বে না!

#### স্তর ৩: অফলাইন প্যাক এক্সপোর্টার (1-Click PBX Sound Pack Exporter)
- cPanel সেটিংস পেজে একটি বোতাম থাকবে: **"📦 Download Sound Pack (.zip)"**।
- নন-টেকনিক্যাল অ্যাডমিন অডিও পরিবর্তন করে জিপ ডাউনলোড করে যে কাউকে দিলে, সে Asterisk-এর `/usr/share/asterisk/sounds/custom/` ফোল্ডারে এক ক্লিকে আনজিপ করে দিতে পারবে।

---

### চ্যালেঞ্জ ৩: নতুন Asterisk VPS-এর জন্য "১-ক্লিক অটো-প্রোভিশনিং স্ক্রিপ্ট"

ভবিষ্যতে সম্পূর্ণ নতুন একটি ক্লাউড VPS-এ Asterisk রেডি করার জন্য মডিউলে একটি সেলফ-কন্টেইন্ড স্ক্রিপ্ট থাকবে:
`modules/pbxpilot/scripts/provision_asterisk_vps.sh`

অ্যাডমিন বা ইঞ্জিনিয়ার নতুন VPS-এ SSH করে শুধু এই কমান্ডটি দেবে:
```bash
curl -sSL https://crm.yourdomain.com/modules/pbxpilot/scripts/provision_asterisk_vps.sh | bash -s -- --crm-domain crm.yourdomain.com --secret pbxpilot_ivr_internal_secret_key_88
```

**এই স্ক্রিপ্টটি যা যা স্বয়ংক্রিয়ভাবে করে দেবে:**
1. Asterisk প্যাকেজ ও `func_curl` সক্রিয় আছে কিনা চেক করবে।
2. `/etc/asterisk/manager_custom.conf`-এ `crm-api` ইউজার তৈরি করবে এবং cPanel সার্ভারের আইপি হোয়াইটলিস্টে যুক্ত করবে।
3. `/etc/asterisk/extensions_custom.conf`-এ `[ivr-order-confirm]` ডায়ালপ্ল্যান সিআরএম ডোমেইন সহ ইনজেক্ট করবে।
4. `/usr/share/asterisk/sounds/custom/` ডিরেক্টরি তৈরি করে ডিফল্ট বাংলা সাউন্ড প্রম্পটগুলো নামিয়ে `asterisk:asterisk 644` পারমিশন দেবে।
5. UFW ফায়ারওয়ালে SIP (5060 UDP), RTP (10000-20000 UDP) এবং cPanel IP-র জন্য AMI (5038 TCP) ওপেন করবে।
6. `asterisk -rx 'core reload'` রান করে সিস্টেম লাইভ করে দেবে।

---

### চ্যালেঞ্জ ৪: ডাইনামিক One-Click কানেকশন ও প্রোফাইল প্রিসেট

`Settings.php`-র `load_preset()` মেথডটিকে হার্ডকোডেড লোকালহোস্টের বদলে **এনভায়রনমেন্ট-অ্যাওয়ার (Environment-Aware)** করা হবে:

```php
public function load_preset($target = 'auto')
{
    $is_local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) 
                || (strpos($_SERVER['HTTP_HOST'] ?? '', 'crm.test') !== false);

    if ($target === 'production' || (!$is_local && $target === 'auto')) {
        // প্রোডাকশন ভিপিএস মোড: সরাসরি রিমোট আইপি ও স্ট্যান্ডার্ড পোর্ট
        $preset = [
            'pbxpilot_ami_host'    => get_option('pbxpilot_prod_asterisk_ip') ?: 'YOUR_ASTERISK_VPS_IP',
            'pbxpilot_ami_port'    => '5038',
            'pbxpilot_ami_username'=> 'crm-api',
            'pbxpilot_ami_secret'  => 'Sos2vKTuQpr8kGmYc1nBHQ7L',
            'pbxpilot_ivr_caller_id'=> '09638881188',
        ];
    } else {
        // লোকাল ডেভেলপমেন্ট টানেল মোড
        $preset = [
            'pbxpilot_ami_host'    => '127.0.0.1',
            'pbxpilot_ami_port'    => '15038',
            'pbxpilot_ami_username'=> 'crm-api',
            'pbxpilot_ami_secret'  => 'Sos2vKTuQpr8kGmYc1nBHQ7L',
            'pbxpilot_ivr_caller_id'=> '09638881188',
        ];
    }
}
```

---

## 5. Security & Firewall Architecture (নেটওয়ার্ক ও নিরাপত্তা)

cPanel ও Asterisk VPS যোগাযোগের জন্য ফায়ারওয়াল পলিসি:

| পোর্ট | প্রোটোকল | কোথা থেকে | কোথায় | উদ্দেশ্য |
|---|---|---|---|---|
| **5038** | TCP | cPanel Server Public IP Only | Asterisk VPS | AMI Originate (আউটবাউন্ড কল শুরু করা) |
| **443** | TCP (HTTPS) | Asterisk VPS | cPanel Server | Webhook Delivery (কলের ফলাফল সিআরএম-এ পাঠানো) |
| **5060 / 5061** | UDP / TCP | Telco SIP Provider (BTCL/AmberIT) | Asterisk VPS | SIP ট্রাঙ্ক সিগন্যালিং |
| **10000-20000** | UDP | Any / Telco | Asterisk VPS | RTP Voice Media Stream |
| **8089** | TCP (WSS) | Agent Browsers (WebRTC) | Asterisk VPS | CRM ইন-ব্রাউজার কলিং (যদি ব্যবহৃত হয়) |

> [!IMPORTANT]
> Asterisk VPS-এর AMI পোর্ট `5038` কখনোই উন্মুক্ত থাকবে না। এটি শুধুমাত্র সিআরএম cPanel সার্ভারের নির্দিষ্ট পাবলিক আইপির জন্য UFW বা Cloud Firewall দিয়ে বাইন্ড থাকবে।

---

## 6. Migration Roadmap: Step-by-Step Transition (বাস্তবায়নের রোডম্যাপ)

যখন অন্যান্য ফিচার ডেভ শেষে আমরা এই নতুন প্রোডাকশনে শিফট করব, তখন কাজের ক্রম হবে নিম্নরূপ:

```
[ধাপ ১: নতুন Asterisk VPS তৈরি]
  └── Ubuntu 22.04 LTS VPS নেওয়া 
  └── Asterisk 18/20 LTS ইন্সটল করা
  └── provision_asterisk_vps.sh রান করা (৩০ সেকেন্ডে অটো-কনফিগ)
  └── টেলকো এসআইপি ট্রাঙ্ক (Trunk) কনফিগার ও ইনকামিং/আউটবাউন্ড টেস্ট

[ধাপ ২: সিআরএম কোডবেস ডাইনামিক প্রিপারেশন]
  └── ami_consumer.php-তে ডাইনামিক APP_BASE_URL যুক্ত করা
  └── dialplan-এ func_curl ব্যাকআপ ইন্টিগ্রেশন চালু করা
  └── Settings.php-তে প্রোডাকশন ভিপিএস প্রিসেট যুক্ত করা

[ধাপ ৩: cPanel হোস্টিংয়ে ডেপ্লয়]
  └── সিআরএম ফাইল ও ডাটাবেজ cPanel-এ আপলোড ও ইম্পোর্ট
  └── cPanel সার্ভারের পাবলিক আইপি Asterisk VPS-এর ফায়ারওয়ালে (UFW) হোয়াইটলিস্ট করা
  └── PBXPilot Settings-এ গিয়ে "Connect to Production VPS" বাটনে ১-ক্লিক করা
  └── "Test Connection" ও "Test Ring" চেপে ভেরিফাই করা

[ধাপ ৪: লাইভ ট্রানজিশন ও টেস্ট]
  └── WooCommerce থেকে একটি টেস্ট অর্ডার প্লেস করা
  └── ফোনে অটোমেটিক কল আসা, ১ চাপা এবং সিআরএম-এ অর্ডার কনফার্ম ও হোয়াটসঅ্যাপ মেসেজ যাওয়া নিশ্চিত করা।
```

---

## 7. Conclusion & Readiness

এই আর্কিটেকচার বাস্তবায়ন করলে:
1. সিআরএম শেয়ার্ড cPanel-এ থাকুক বা ক্লাউড সার্ভারে—কোথাও কোনো সিস্টেম ডেমন বা এসএসএইচ টানেলের জন্য আটকে থাকবে না।
2. নতুন Asterisk VPS পরিবর্তন করা হবে সম্পূর্ণ ব্যথাহীন এবং মাত্র ১টি স্ক্রিপ্ট রান করার ব্যাপার।
3. নন-টেকনিক্যাল সাধারণ অ্যাডমিনের কোনো পোর্ট বা কোড নিয়ে মাথা ঘামাতে হবে না; সে আগের মতোই প্যানেল থেকে টেস্ট কল পাঠাতে ও কল কন্ট্রোল করতে পারবে।
