🚀 Bizbot WhatsApp Module 1.1.0 - Changelog (12-02-2026)

## [1.1.0] - 2026-02-12
### Added
- **Conditional Lead Entry:** Automated lead creation via `#lead` keyword or `CRM` tag.
- **Direct Chat Linking:** Added WhatsApp icon in lead lists to jump to Bizbot chat.
- **Lightweight Chat History:** Dedicated tab and preview section in Lead profile.
- **Webhook Integration:** New endpoint and URL utility in settings.
- **API Extension:** On-demand contact and label fetching.

🚀 Bizbot WhatsApp Module 1.0.0 - Changelog (11-02-2026)

💎 Core Features (মূল ফিচারসমূহ)
100% Core-less Implementation: সিস্টেমের কোনো কোর ফাইল পরিবর্তন ছাড়াই মডিউলটি এখন পুরোপুরি স্বাধীনভাবে কাজ করে।
Multi-Event Automation: লিড, কাস্টমার, ইনভয়েস, টাস্ক, প্রজেক্ট এবং সাপোর্ট টিকিটের যাবতীয় ইভেন্টে অটোমেটিক হোয়াটসঅ্যাপ নোটিফিকেশন।
Template Manager: প্রতিটি ইভেন্টের জন্য আলাদা মেসেজ টেমপ্লেট সেট করা এবং সেগুলো প্রয়োজন অনুযায়ী চালু বা বন্ধ করার সুবিধা।
Flexible Recipients: কাস্টমার, অ্যাসাইন করা স্টাফ, নির্দিষ্ট অ্যাডমিন বা টাস্ক ফলোয়ারদের কাছে মেসেজ পাঠানোর সুবিধা।
Automated Follow-ups: লিডদের জন্য ডায়নামিক ডিলে (মিনিট, ঘন্টা, দিন বা সপ্তাহ) অনুযায়ী অটোমেটেড ফলো-আপ মেসেজ পাঠানোর সিস্টেম।
Message Queue: মেসেজ শিডিউল করে রাখার জন্য কিউ (Queue) ফাংশনালিটি।
WhatsApp Widget: ওয়েবসাইটের ফ্রন্টএন্ডে হোয়াটসঅ্যাপ চ্যাট উইজেট ইন্টিগ্রেশন।

✉️ Bulk Messaging Features
Advanced Filtering: লিড স্ট্যাটাস, স্টাফ, ট্যাগ, কাস্টমার গ্রুপ এবং ডেট রেঞ্জ অনুযায়ী রিসিপিয়েন্ট ফিল্টার করার সুবিধা।
CSV Support: সরাসরি CSV ফাইল আপলোড করে কাস্টম লিস্টে বাল্ক মেসেজ পাঠানোর ক্ষমতা।
Smart Sending UI: ফ্রন্টএন্ড থেকে ডিলে (Delay) এবং জিটার (Jitter) সেট করে মেসেজ পাঠানোর সময় পজ (Pause) ও রিজুম (Resume) করার সুবিধা।

🛡️ Security & Performance (নিরাপত্তা ও পারফরম্যান্স)
SQL Injection Hardening: ডাটাবেজ কুয়েরি বিল্ডার ব্যবহার করে সব ধরণের ইনজেকশন রিস্ক বন্ধ করা হয়েছে।
Settings Whitelist: শুধুমাত্র অনুমোদিত সেটিংস পরিবর্তন করার সিকিউরিটি লেয়ার।
XSS Protection: ইউজার ইনপুট এবং জাভাস্ক্রিপ্ট ইনজেকশন থেকে সুরক্ষার জন্য আউটপুট হার্ডেনিং।
High-Speed Placeholder Logic: টেমপ্লেট ট্যাগগুলো দ্রুত প্রসেস করার জন্য ম্যাপ-বেসড রিফ্যাক্টরিং।
Log Management: ডাটাবেজের ওজন কমাতে ৩০ দিনের পুরনো লগ অটো-ক্লিনআপ সিস্টেম।

🛠️ Diagnostics & Maintenance
API Connection Tester: সেটিংস পেজ থেকে সরাসরি বিজবট এপিআই কানেকশন চেক করার টুল।
Manual Retry: ফেইল হওয়া মেসেজগুলো লগ থেকে সরাসরি পুনরায় পাঠানোর (Retry) সুবিধা।
Full Localization (i18n): পুরো মডিউলটি ল্যাঙ্গুয়েজ ফাইলের মাধ্যমে লোড হয়, যা মাল্টি-ল্যাঙ্গুয়েজ সাপোর্টের জন্য প্রস্তুত।
SSL Toggle: লোকাল বা আনট্রাস্টেড এনভায়রনমেন্টের জন্য এসএসএল ভেরিফিকেশন অন/অফ করার অপশন।

🧹 Cleanup & Polishing
Clean Build: প্রোডাকশন থেকে সব ধরণের ডেভেলপার টুলস (_dev) এবং ডায়াগনস্টিক স্ক্রিপ্ট রিমুভ করা হয়েছে।
Runtime Config: পারফরম্যান্স রক্ষায় শুধুমাত্র অ্যাক্টিভ থাকা অবস্থায় মডিউল কনফিগারেশন লোড হয়।