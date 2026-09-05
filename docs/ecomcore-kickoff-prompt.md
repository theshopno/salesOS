# Kickoff prompt — paste this as the first message to start implementation

Copy everything in the fenced block below and send it as your first message to
Claude Code, in this project's root, after placing `ecomcore-architecture-plan.md`,
`ecomcore-ledger.md`, and `ecomcore-technical-debates.md` in the `docs/` folder.

```
আমরা এই প্রজেক্টে docs/ecomcore-architecture-plan.md অনুযায়ী একটা e-commerce
management module family (ecomcore + inventory/purchases/pos/returns/courier/
fraudcheck/wcsync/ordernotifier) বিল্ড করছি।

শুরু করার আগে এই তিনটা ফাইল সম্পূর্ণ পড়ো, প্রথম থেকে শেষ পর্যন্ত — কোনো অংশ স্কিপ না
করে:
1. docs/ecomcore-architecture-plan.md — এটাই একমাত্র source of truth: platform facts
   (§1), architecture (§2), প্রতিটা মডিউলের DB স্কিমা (§3-§10, §16), resolved decisions
   (§11, §13), মডিউল স্ক্যাফোল্ডিং টেমপ্লেট (§14), ফেজ-ভিত্তিক প্ল্যান (§15), আর
   বাধ্যতামূলক প্রসেস রুল (§17)।
2. docs/ecomcore-ledger.md — কোন ফেজ এখন পর্যন্ত কী অবস্থায় আছে তার লাইভ রেকর্ড।
3. docs/ecomcore-technical-debates.md — ইমপ্লিমেন্টেশনের সময় নতুন যেসব টেকনিক্যাল
   সিদ্ধান্ত লাগবে সেগুলোর লগ।

পড়ার পর, কাজ শুরু করার আগে নিচের নিয়মগুলো কঠোরভাবে মানবে (এগুলো plan §17-এ বিস্তারিত
আছে, এখানে সংক্ষেপে):

**নিয়ম ১ — কোনো Assumption না, সবসময় জিজ্ঞেস করবে (§17.1):**
প্ল্যানে যা explicitly decide করা আছে (§11/§13-তে) সেগুলো অনুসরণ করবে, প্রশ্ন করার
দরকার নেই। কিন্তু প্ল্যানে যেখানে ⚠️/DRAFT/"needs interview" লেখা আছে (এই মুহূর্তে:
§16.1-এর `ordernotifier` মডিউলের ইন্টারভিউ চেকলিস্ট — channel scope অর্থাৎ
WhatsApp+SMS দুটোই থাকবে এটা §16.0-এ লক করা আছে, কিন্তু event list/template/SMS
provider এখনো ঠিক হয়নি), অথবা যেকোনো judgment call যা
ডেটা মডেল/বিজনেস লজিক/ইউজার-facing workflow ছুঁয়ে যায় এবং প্ল্যানে স্পষ্ট উত্তর নেই —
সেখানে অনুমান না করে সরাসরি আমাকে জিজ্ঞেস করবে। "প্ল্যান বিস্তারিত" মানে "সব জিজ্ঞেস
করা হয়ে গেছে" না — যা §11/§13-তে লেখা নেই সেটা এখনো ডিসাইড হয়নি।

**নিয়ম ২ — Strict phase-gating, একবারে একটা মডিউল (§17.2):**
docs/ecomcore-ledger.md চেক করে বর্তমান ফেজ বের করবে। প্ল্যান §12-এ যে অর্ডারে মডিউল
আছে (ecomcore → inventory → wcsync → purchases → pos → returns → courier →
fraudcheck → ordernotifier) ঠিক সেই অর্ডারেই এগোবে। একটা ফেজ §15-এর acceptance criteria
অনুযায়ী সম্পূর্ণ শেষ না হওয়া আর ledger-এ `Done` মার্ক না হওয়া পর্যন্ত পরের ফেজের কোনো
কোড/স্কিমা লিখবে না। ব্লকড হলে ledger-এ ব্লকার লিখে রাখবে, অন্য মডিউলে চলে গিয়ে সেটা
এড়াবে না।

**নিয়ম ৩ — existing মডিউলের সাথে কোনো সম্পর্ক না (plan §2):**
`modules/wooconnector`, `modules/salesos`, `modules/bkash`, `modules/bizbot` বা
প্রজেক্টের অন্য যেকোনো existing মডিউল স্পর্শ, মডিফাই, বা রিলেট করবে না —
`modules/bizbot` কোনোভাবেই `ordernotifier`-এর dependency না। এদের কোড শুধু
রেফারেন্স/প্যাটার্ন হিসেবে দেখা যাবে (উদাহরণ: wcsync-এর জন্য wooconnector,
ordernotifier-এর WhatsApp অংশের জন্য bizbot-এর API shape) — কিন্তু নতুন মডিউলে সেই
লজিক আলাদাভাবে fresh লেখা হবে, import/extend/কোনো shared table নয়। ordernotifier-এর
API base URL হবে api.bizbot.bd (existing bizbot মডিউলে যে api.bizbot.one আছে সেটা
থেকে ভিন্ন — এটা ইচ্ছাকৃত, প্ল্যান §16-এ flag করা আছে)। Bulk SMS চ্যানেলের
credential/provider এখনো দেওয়া হয়নি — সেটা না পাওয়া পর্যন্ত SMS-sending কোড লেখা
শুরু করবে না।

**নিয়ম ৪ — দুইটা companion ফাইল লাইভ রাখবে (§17.3):**
কাজ করার সময় docs/ecomcore-ledger.md আপডেট করবে প্রতিটা ফেজ শুরু/ব্লক/শেষ হওয়ার
মুহূর্তে — সেশনের শেষে গিয়ে না। নতুন কোনো টেকনিক্যাল সিদ্ধান্ত (যা মূল প্ল্যানে
নেই) লাগলে সেটা docs/ecomcore-technical-debates.md-এ entry-template অনুযায়ী লগ
করবে, কোডে কমেন্ট আকারে না।

এখন docs/ecomcore-ledger.md চেক করে বর্তমান ফেজ বের করো, এবং Phase 0 (environment
check, plan §15) থেকে শুরু করো। কোনো কোড লেখার আগে আমাকে জানাও তুমি কী পড়েছ আর
এখন কোন ফেজে কাজ শুরু করছ।
```
