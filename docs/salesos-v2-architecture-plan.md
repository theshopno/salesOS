# salesos v2 — Voice & Agency Platform: Architecture Plan

Status: DECIDED at the scoping level (§2 reconciliation, §5 module mapping). Phase details
in §9 are the working plan; §10 interview items are still open.
Base: Perfex-CRM-derived CodeIgniter 3 app at `/home/fizz/Projects/crm`.
Companion: `docs/ecomcore-architecture-plan.md` (separate, independently DECIDED plan —
read §2 below before touching anything e-commerce-shaped in this module).

This plan is the output of a long scoping conversation (chat history, 2026-08-21) that
went through several wrong turns before landing here. §1 exists specifically so a fresh
implementer doesn't repeat those wrong turns.

---

## 1. Critique of the pre-this-document plan (why this revision exists)

The conversation that produced this plan iterated live and made real mistakes worth
recording, not hiding:

1. **Direct collision with `ecomcore` was almost missed.** For several turns this plan
   grew a full "E-commerce Pack" — COD confirmation, abandoned-cart recovery,
   delivery/return tracking, WhatsApp order notifications — without checking whether
   anything like it already existed in `docs/`. It did: `docs/ecomcore-architecture-plan.md`
   is a **separately DECIDED, 1000+ line, evidence-cited plan** with modules
   (`ordernotifier`, `courier`, `fraudcheck`, `wcsync`) that cover almost exactly the
   same ground, using the **same BizBot API contract**. Building both would have meant
   two WhatsApp-order-confirmation systems, two delivery-tracking systems, and a third
   independent phone-normalization implementation. **Lesson applied:** §2 below scopes
   salesos v2 down to voice + agency only, and treats ecomcore as the owner of
   everything order/inventory/WooCommerce-shaped. Do not re-litigate this without a
   fresh reason — it was a deliberate, asked-and-answered decision.
2. **The `wooconnector` question flip-flopped mid-conversation** (first "stays
   separate," then "gets absorbed into salesos," then corrected again). The actual
   answer, now that ecomcore is accounted for: **neither.** `wooconnector` is
   untouched — ecomcore's own plan already decided this (its §2 "no-relation rule"),
   and salesos v2 has no reason to touch it either. `wcsync` (inside ecomcore) is its
   eventual functional replacement, not salesos.
3. **"One giant module, zero dependencies" was stated as an absolute rule**, then
   almost applied to `bizbot` in a way that would have rebuilt WhatsApp messaging
   *twice* (once in salesos, once in ecomcore's `ordernotifier`). The rule is right for
   telephony (AMI, screen-pop, CDR — genuinely salesos's own domain, previously
   duplicated four ways for no reason) but was being over-applied to e-commerce
   messaging, which is a different module's job under this codebase's actual
   conventions (fact-checked in ecomcore §1: `is_active()` guard is the real
   dependency idiom here, not zero-coupling everywhere).
4. **A full folder-swap ("backup everything, start `salesos/` empty") was proposed
   without a rollback plan, a pilot rollout, or a decision on what happens to existing
   `salesos_calls`/`salesos_agents` data.** This is a live call center's telephony —
   an abrupt cutover with no pilot and no data-continuity plan is a real operational
   risk, not a formality. §9 below adds a pilot step and an explicit data-migration
   decision to Phase 0.
5. **The feature-flag table's `salesos_core_telephony: ON, locked` design quietly
   contradicted an earlier, correct decision** ("CRM's other features must survive
   Asterisk being unreachable"). "Administratively always-on" and "must degrade
   gracefully at runtime if Asterisk is briefly down" are two different properties —
   conflating them was a mistake. §6 below fixes the wording.
6. **Credential rotation was sequenced as a day-one task** without checking whether
   anything in current production still depends on the credentials being rotated.
   §9 Phase 0 now says to check that first.
7. **No acceptance criteria, no test environment, no phase-gating discipline** — unlike
   `ecomcore-architecture-plan.md`, which has both (§15/§17 of that document) and is
   clearly the standard this codebase's docs are held to. This revision adopts the same
   discipline (§9, §11) for consistency and because it's the more honest way to plan
   telephony work where a silent regression means missed calls.

---

## 2. Relationship to `ecomcore` — read this before building anything e-commerce-adjacent

`docs/ecomcore-architecture-plan.md` §2 states a **hard, locked rule**: the `ecomcore`
family (`ecomcore`, `inventory`, `purchases`, `pos`, `returns`, `courier`, `fraudcheck`,
`wcsync`, `ordernotifier`) has **zero relation** to any pre-existing module —
`wooconnector`, old `salesos`, `bizbot`, or anything else. That rule protects
*ecomcore's* family from coupling backward to legacy code. It does **not** forbid a
*new*, freshly-built module from depending forward on ecomcore — `courier` and
`fraudcheck` already do exactly that (§2 of that doc: "depends on ecomcore ... only").

**Decision (this document): salesos v2 is scoped to voice calling + agency CRM
workflows only.** It does not own WhatsApp/SMS order notifications, delivery/courier
tracking, fraud/RTO checks, or WooCommerce sync — `ecomcore`'s family already owns all
of that, by an independently-made, evidence-based decision that predates this plan.

**What salesos v2 adds on top, once ecomcore exists:** a **Voice Escalation Bridge** —
listens to `ecomcore_order_created` / `ecomcore_order_confirmed` /
`ecomcore_order_cancelled` (hook payload shape: TBD by whoever builds `Ecomcore_model`
first, per that doc's §14) and to `ordernotifier`'s send log, and if an order sits
unconfirmed past a timeout, escalates to an IVR call or an agent call queue entry. This
is the one piece ecomcore's plan does **not** cover — `ordernotifier` (per its own §16)
is a one-way notifier (pending/sent/failed), not a reply-tracking confirmation loop, and
it has no voice channel at all.

**Known open gap, not solved here:** it is not yet decided *how* an inbound WhatsApp
"yes" reply flips an order to `confirmed` in ecomcore — `ordernotifier`'s §16.1
interview checklist doesn't cover inbound replies, only outbound sends. Until that's
resolved (in ecomcore's own interview, or as a follow-up), salesos's escalation timer
can only key off ecomcore's *order status* transitions (whatever causes them — could be
manual staff action, could be a future inbound-webhook feature), not off "a WhatsApp
reply specifically." This is good enough to build against — the escalation bridge
doesn't need to know *how* an order got confirmed, only *whether* it has been, by the
time the timeout fires.

**Sequencing consequence:** the Voice Escalation Bridge cannot be built or tested until
`ecomcore` Phase 1 (kernel) and Phase 9 (`ordernotifier`, itself gated on the §16.1
interview) are `Done` in `docs/ecomcore-ledger.md`. Check that ledger before starting
salesos v2 Phase 3 (§9 below). This is very likely a **later** phase than it originally
looked, not a parallel-track item.

---

## 3. Verified facts carried over from the conversation's own audit

(Full detail lives in this session's transcript; the load-bearing ones are repeated here
so this document is self-contained for a fresh implementer.)

| # | Fact | Evidence |
|---|---|---|
| 1 | Four modules currently overlap on telephony: `salesos`, `pbxpopup`, `pbxpilot`, `call_manager`. Three independent "Call History" tabs render on the lead page from three different data sources; phone-number normalization is implemented 3-4 times across them. | `modules/salesos/views/partials/call_history_tab.php`, `modules/call_manager/views/lead_call_history.php`, `modules/pbxpopup/controllers/Pbxpopup.php` |
| 2 | `salesos`'s AMI consumer parses raw AMI events (`Newchannel`, `DialBegin`/`DialEnd`, `BridgeEnter`/`BridgeLeave`, `Hangup`) into real-time state; it does **not** consume `Cdr`-manager events — the separate `fetch_pbx_cdr()` DB-poll path is the authoritative CDR reconciliation source. | `modules/salesos/libraries/Ami_event_parser.php:25-88`; `modules/salesos/models/Salesos_model.php:515-526` |
| 3 | `salesos`'s browser softphone signals via SIP.js over `wss://.../asterisk/ws` (PJSIP's own websocket transport) — it does **not** use ARI. Only `http.conf enabled=yes` is required on the Asterisk box for this to work, not `ari.conf`. | `modules/salesos/assets/js/salesos_webrtc.js:4`; `modules/salesos/controllers/Api.php:670` |
| 4 | Click-to-call (`Api.php::originate()`) opens its own short-lived AMI connection per HTTP request — it does not require the persistent `ami_consumer.php` daemon to be running. `Phone_cache` degrades to a plain DB lookup if Redis is unavailable (`Redis_service::connect()` fails soft, doesn't throw). Both are compatible with the CRM web app running on shared/cPanel hosting, as long as the three daemons (`salesos-ami`, `salesos-ws`, `salesos-archiver`) run on a machine that supports persistent processes (root + systemd). | `modules/salesos/controllers/Api.php:50-51`; `modules/salesos/libraries/Redis_service.php:29-48` |
| 5 | The CRM core DB driver defaults to `mysqli` (`APP_DB_DRIVER` in `application/config/database.php:93`) — Perfex core is MySQL-only. Any new CDR backend should stay MySQL-family (MariaDB) for operational simplicity, not Postgres. | `application/config/database.php:89-93` |
| 6 | `ecare` (100.85.86.93, Tailscale-only IP, Debian 13, Asterisk v23.4.1/PJSIP) currently has AMI disabled, HTTP server disabled, CDR going to flat CSV only, no MySQL server installed (Postgres is running locally for an unknown other purpose), and no CRM code deployed on it at all. | live SSH audit, this session |
| 7 | This codebase's real module-dependency idiom is a runtime guard — `$CI->app_modules->is_active('name')` checked in the dependent module's own bootstrap — not zero-coupling. `module.json` is never read by the app. | `docs/ecomcore-architecture-plan.md` fact #4, independently verified there |

---

## 4. Module consolidation — final mapping

| Old module | Fate |
|---|---|
| `salesos` | **Fresh build, same module name.** Per Phase 0 (§9): the old `modules/salesos/` folder is moved to `modules/_backup_pre_v2/` in full — no old file stays in place — and `modules/salesos/` is recreated empty. All v2 code is written new against the §5 pack structure; only the *design* of the old AMI/CDR/WebRTC logic (fact-checked in §3) is used as a reference while writing it, not the old files themselves. This avoids the file-level, route-level, and DB-hook-level collisions a "restructure in place" approach would risk |
| `pbxpopup` | Screen-pop trigger (MicroSIP `cmdIncomingCall` URL handler) absorbed as a "Desktop Trigger" sub-feature; `recordings()`/`stream.php` (points at a deprecated Issabel bridge) retired |
| `pbxpilot` | Fully absorbed — becomes the AI Call Intelligence pack's transcription/summary engine |
| `call_manager` | Fully retired — strictly inferior duplicate of salesos's own CDR tab (uncached, weaker phone-match, no click-to-call) |
| `bizbot` (module) | Retired as a standalone module. salesos v2 rebuilds a **thin, independent** BizBot HTTP wrapper for **agency lead messaging + client account provisioning only** (`api.bizbot.bd/public/v1/user` CRUD + `.../chat` send). This is deliberately separate from `ecomcore`'s `ordernotifier`, which independently re-implements its own BizBot call for **order** notifications — the same duplication-across-boundary tradeoff `ecomcore` §11.8 already accepts for phone-matching |
| `wooconnector` | **Untouched.** Not part of this plan at all — stays exactly as-is, per `ecomcore`'s own §2 decision. Its eventual functional replacement is `ecomcore`'s `wcsync`, built independently, not by salesos |

---

## 5. Folder / pack structure

```
modules/salesos/
├── core/            AMI service, event parser, single phone-normalization helper, Redis
├── channels/
│   ├── voice/        WebRTC (browser) + Desktop SIP trigger (MicroSIP)
│   └── whatsapp/      Thin BizBot wrapper — agency lead messaging only (not order notifications)
├── intelligence/      Transcription/summary/scoring (pbxpilot engine, absorbed)
├── packs/
│   └── agency/         Call List, follow-ups, agent reports, BizBot client provisioning
├── escalation/         Voice Escalation Bridge — listens to ecomcore hooks (§2). Gated, later phase.
├── reporting/          Shared report engine — agent analysis + AI scoring (§7)
├── settings/           Feature-flag UI, per-agent channel setting
├── daemons/            ami_consumer.php, ws_server.php, event_archiver.php
├── deploy/
│   ├── systemd/         App-server deployment (existing deploy.sh/update.sh/rollback.sh)
│   └── provision_pbx.sh PBX-side provisioning (new, §8a) — idempotent, targets any Asterisk box
└── views/partials/     Hooks into Perfex's existing lead-detail popup (not a separate screen)
```

No `packs/ecommerce/` — that territory belongs to `ecomcore`.

---

## 6. Feature flags — complete list

| Flag | MVP default | Notes |
|---|---|---|
| `salesos_core_telephony` | ON | Administratively always-on; **must still fail soft** if Asterisk/AMI is briefly unreachable — CRM's non-telephony pages must never hard-error because of this flag's state or Asterisk's reachability |
| `salesos_channel_browser` | ON | Per-agent override lives on the agent record, not just this global flag |
| `salesos_channel_desktop` | ON | Same |
| `salesos_ai_intelligence` | ON, mode=`manual` | Sub-modes: `off` / `manual` / `auto_flagged` / `full_auto` |
| `salesos_ai_model` | — | Cost/quality selector, provider-agnostic |
| `salesos_agency_pack` | OFF (MVP) → ON (v1.1) | Master switch for Call List/follow-ups/reports |
| `salesos_agency_bizbot_messaging` | OFF (MVP) → ON (v1.1) | Lead-messaging via BizBot, independent of ecomcore's ordernotifier |
| `salesos_agency_bizbot_provisioning` | OFF (MVP) → ON (v1.1) | `/user` CRUD client account management |
| `salesos_voice_escalation` | OFF, hard-gated | Cannot be turned on until `ecomcore` Phase 1 + Phase 9 are `Done` per `docs/ecomcore-ledger.md` — the settings UI should refuse to enable this flag and say why, not just hide it silently |

Stored as `salesos_settings` key/value rows, one "Features" tab, flat toggles + mode
dropdowns where needed — no nested config UI.

---

## 7. Agent reporting & AI call scoring — requirements

Added per stakeholder request (2026-09-02): the reporting engine's purpose is explicitly
**agent analysis**, not just raw call logs. The pre-existing (pre-v2) `salesos_calls`
table and `get_stats()` already return date-ranged totals (`total`, `inbound`,
`outbound`, `answered`, `missed`, `total_duration`, `avg_duration`) but have **no
duration-bucket breakdown and no per-agent drill-down** — that's the concrete gap this
section closes. Treat this as binding scope for the `reporting/` pack (§5) and Phase 2
(§9), not a "nice to have."

**Duration-based breakdown (new requirement, not in pre-v2 code):**
- Calls must be bucketable by talk-time length, with the bucket edges configurable
  (not hardcoded), e.g. `< 30s`, `30s–2min`, `> 2min` — "how many calls today lasted
  over 2 minutes" needs to be a direct report, not something computed by hand from a
  CSV export
- Every bucket must be clickable/drillable to the actual list of calls behind the
  count — a number alone ("14 calls over 2 min") is not sufficient; the agent's
  manager needs to open those 14 and listen/read them

**Per-agent analysis (extends existing `agent_id` filter into a real dashboard):**
- One agent (or all agents compared side by side) × one date range → totals,
  answered/missed rate, avg + total talk time, and the duration-bucket breakdown above
- Filters must combine freely: agent + date range + duration bucket + disposition +
  direction at the same time (e.g. "agent X, this week, outbound, answered, over 2
  min")
- Trend view over time (daily/weekly), not just a single-period snapshot, so a
  manager can see an agent improving or declining

**AI call scoring, integrated into the same reports (not a separate screen):**
- This connects to the AI Call Intelligence pack (§6 `salesos_ai_intelligence`) and
  Phase 2's already-planned "scoring + coaching feedback" (§9 Phase 2) — the
  requirement here is that the score must land **inside the agent report**, not just
  on the individual call
- Each AI-analyzed call gets a score (rubric TBD — see §10 item 3, still open); the
  per-agent dashboard shows that agent's average score over the selected period, its
  trend, and a way to filter/sort by "lowest-scoring calls first" so coaching time
  goes to the calls that need it
- Because scoring is `manual` mode at MVP (§6) and only reaches `auto_flagged`/
  `full_auto` in Phase 2, the score columns in agent reports will be sparse/empty
  until Phase 2 is enabled — the report UI should say "not yet scored" rather than
  showing a blank or a zero, so it isn't misread as a bad score

**Acceptance criteria (add to Phase 2's list in §9):** a manager can, without exporting
anything to a spreadsheet, answer "how many calls did agent X take today," "how many of
those ran over 2 minutes," and — once Phase 2's scoring is live — "what's agent X's
average AI score this week and which of their calls scored lowest."

---

## 8. Infra / security foundation

- `ecare`: enable AMI (dedicated least-privilege `crm-api` user, not a superuser
  permission class), enable `http.conf` (ARI not required — fact #3), install MariaDB
  locally + `cdr_adaptive_odbc` writing into it (fact #5 — stay in the MySQL family),
  verify what the box's existing Postgres instance is actually used for before assuming
  it's free to ignore
- Make `ecare` reachable over a real public domain + TLS (it already runs Caddy) — its
  current Tailscale-only IP means any agent whose machine isn't on the tailnet cannot
  reach the browser WebRTC or screen-pop WebSocket endpoints at all
- Rotate every credential currently in `modules/salesos/INFRASTRUCTURE.md` (plaintext
  PBX root password, AMI secret, ARI password, DB password, Redis password, dev-server
  password) — **but first check whether anything in current production still uses
  them**, so rotation doesn't break a live system before its replacement is ready. Move
  secrets to `.env`, `.gitignore` the doc, and only rewrite git history (commit
  `d9bffdb` already has them) once there's an explicit go-ahead — that's a destructive
  operation, not a default action

---

## 8a. PBX portability — provisioning script + settings-driven connection config

Added per stakeholder request (2026-09-02), triggered by a real question: if the
business changes PBX hardware, or salesos v2 is sold/deployed to a different
client/tenant on their own Asterisk box, how much of the PBX-side setup has to be
redone by hand? The answer from doing this once (manually, against `ecare`, this
session) is: **PBX-side setup can never be "just fill in a settings page" — it lives on
the Asterisk box itself, outside the CRM's reach — but it can and must be a scripted,
idempotent, one-time step, decoupled from any CRM code change.** Two different problems,
two different fixes:

**1. PBX-side (new box, once per PBX) — `deploy/provision_pbx.sh`:**
- Given target host + SSH creds, provisions: an isolated CDR database + dedicated
  least-privilege DB user (never touching pre-existing databases that may already live
  on a shared box — `ecare` turned out to already host unrelated production workloads,
  a real scenario, not a hypothetical), the ODBC driver + DSN, `res_odbc.conf` /
  `cdr_adaptive_odbc.conf` wiring, a scoped AMI user in `manager.conf` (ACL-restricted,
  never a superuser class), `http.conf` enablement, and a firewall rule restricting the
  AMI port to the CRM's actual source (Tailscale range or a specific IP — never
  0.0.0.0/0).
- **Must be idempotent** — safe to re-run against a box that's already partially
  provisioned (e.g. after a manual tweak, or a retry) without duplicating config
  stanzas or erroring out. The one built by hand this session was not idempotent
  (blind `>>` appends); the real script must check-before-write on every stanza.
- **Must handle these concrete, encountered issues**, discovered doing this by hand
  against `ecare` — the script exists specifically so nobody has to rediscover them:
  - `sudo -S` reading a piped password conflicts with any command that also needs
    stdin for its own input (`mysql < file.sql`, `tee -a file < snippet`) — the
    password pipe wins and the target command never sees its intended input. Fix:
    route file-to-file operations through `sudo bash -c "cat src >> dest"` (no stdin
    needed by the privileged process at all), never `sudo cmd < file`.
  - `sudo -v` credential caching does **not** reliably survive into a subprocess
    started via `bash script.sh` over a non-interactive SSH session (tty_tickets) — a
    script that assumes one `sudo -v` up front and plain `sudo` afterward will
    intermittently fail. Fix: a local `sudo() { echo "$PASS" | command sudo -S -p ''
    "$@"; }` wrapper used for every privileged call in the script, not just the first.
  - The MariaDB ODBC driver registers itself in `/etc/odbcinst.ini` under the name
    `MariaDB Unicode` (from the `odbc-mariadb` package on Debian) — do not assume a
    driver name; read what the package actually registered before writing `odbc.ini`.
  - Never build SQL with shell-escaped quotes threaded through nested `ssh '...'`
    wrappers (`\"` inside a single-quoted outer string does not survive); write SQL to
    a local file and `scp` it over, then execute the file directly.
- Output: the script prints (or writes to a local file, never git) the resulting AMI
  host/port/user/secret and CDR DB host/user/password/database — these are exactly the
  values the next step consumes.

**2. CRM-side (which PBX to talk to) — must be settings-driven, not hardcoded:**
- Web-app-side AMI/CDR connection details belong in the `salesos_settings` table (§6),
  editable from the Settings "Features"/"PBX" tab — pointing salesos at a different PBX
  should be paste-new-values-and-save, no code deploy.
- **Known gap to close in Phase 1, not deferred:** the long-running daemons
  (`ami_consumer.php`, `ws_server.php`, `event_archiver.php`) currently read a static,
  per-server `daemons/config.php` (gitignored), not the DB — so today, even after
  fixing the web-app side, switching PBXes would still require hand-editing that file
  on the server and restarting the daemons. Phase 1 must close this gap: either have
  the daemons read `salesos_settings` directly (same DB the web app already reaches),
  or add a "Save & regenerate daemon config" action in the Settings UI that writes
  `daemons/config.php` from the DB values and restarts the three systemd units. Either
  is acceptable; leaving it as a manually-edited file is not.

---

## 9. Phase plan

Each phase needs its acceptance criteria actually verified — not "looks done" — before
the next one starts, mirroring `ecomcore-architecture-plan.md` §15/§17's discipline.

### Phase 0 — Backup, foundation, pilot prep
- `git tag`/branch checkpoint of the current tree **before** moving anything (cleaner
  history than relying on git's rename detection across a 5-module simultaneous move)
- Move `modules/salesos`, `modules/pbxpopup`, `modules/pbxpilot`, `modules/call_manager`,
  `modules/bizbot` into a backup folder (`modules/_backup_pre_v2/`). **Do not touch
  `modules/wooconnector`** (§2, §4). Create empty `modules/salesos/`
- **Data-continuity decision (was silently skipped before):** existing
  `salesos_calls`/`salesos_agents`/etc. tables stay in the DB untouched by the folder
  move. Decide explicitly — build v2's schema fresh and write a one-time migration
  script pulling agent-extension mappings and historical call records forward, then
  rename the old tables (e.g. `salesos_calls` → `_archived_salesos_calls`), rather than
  silently losing operational history
- Credential audit (check live production dependency) → rotate → `.env` → `.gitignore`
- ecare infra work (§8)
- Feature-flag table + Settings "Features" tab (empty shell, wired to nothing yet)

### Phase 1 — MVP core telephony
- AMI service, CDR sync (MariaDB backend), click-to-call, screen-pop — both channels
  (browser + desktop)
- One unified call-history/recording section inside Perfex's existing lead-detail
  popup — not a new screen
- Wrap-up + basic disposition codes
- AI: manual-trigger transcription/summary only ("Analyze" button)
- `call_manager` retired, `pbxpopup`/`pbxpilot` absorbed, confirmed via smoke test
- **PBX portability (§8a):** `deploy/provision_pbx.sh` built and proven against a
  second PBX target (not just `ecare`) before Phase 1 closes; AMI/CDR connection
  details moved into `salesos_settings` + Settings UI; daemon config gap closed (DB-fed
  daemons, or a "Save & regenerate" action) — switching which PBX salesos talks to must
  not require a code deploy or hand-editing a server file
- **Pilot rollout, not a full cutover:** run with 1-2 agents (one on each channel type)
  for an agreed period before switching the whole team
- Acceptance: click-to-call succeeds on both channels; screen-pop fires within [X]
  seconds of an inbound call; a MicroSIP agent's existing workflow is provably
  unaffected; recorded calls are audible from the new unified tab; CDR sync produces no
  duplicate/missing rows over a 24h test window; re-running `provision_pbx.sh` against
  an already-provisioned box changes nothing and reports clean (idempotency proof);
  pointing salesos at a different PBX end-to-end takes one script run + one Settings
  save, no deploy

### Phase 2 (v1.1) — AI expansion + Agency Pack
- AI modes: `auto_flagged`, `full_auto`, model selector, scoring + coaching feedback
- Agency Pack: Call List / today's tasks, follow-up scheduling, agent performance
  reports, BizBot lead-messaging wrapper, BizBot client provisioning (`/user` CRUD)
- Agent reporting per §7: duration-bucket breakdown + drill-down, combinable filters,
  trend view, and AI score surfaced inside the per-agent dashboard
- Acceptance: auto-flagged calls are correctly selected by the configured rule; a
  missed follow-up shows up in the agent's list before its due time, not after; a
  BizBot client-provisioning call round-trips a real `guid` and stores it on the lead;
  §7's acceptance criteria (call-count/duration-bucket/AI-score questions answerable
  without a spreadsheet export) hold

### Phase 3 — Voice Escalation Bridge (hard-gated, see §2)
- **Do not start until `docs/ecomcore-ledger.md` shows Phase 1 and Phase 9 `Done`.**
- Listens to ecomcore's order hooks; timer-based escalation to IVR or agent-call queue
  on unconfirmed orders
- IVR build: dialplan `Read()` + AGI (or channel-var + AMI event, since the daemon
  already parses all events — fact #2) to post DTMF results back; TTS via Google,
  cached as static per-phrase audio + Asterisk's native `SayDigits` for order numbers,
  so ongoing TTS API cost is near-zero after initial setup
- Disposition write-back calls into `Ecomcore_model->set_order_status()` (a genuinely
  new, forward dependency — allowed per §2)
- Acceptance: a test order that times out unconfirmed correctly triggers a call within
  the configured window; a confirmed order (however it got confirmed) correctly cancels
  the pending escalation; IVR DTMF result correctly updates order status

---

## 10. Open questions (interview items, not defaulted)

1. Data migration: confirm the "migrate + archive old tables" approach in Phase 0, or a
   different preference
2. Pilot rollout duration/criteria before full agent cutover in Phase 1
3. Call-scoring rubric (what actually makes a "good" call) — needed before Phase 2's
   scoring feature is built, not before. See §7 for where the resulting score must
   surface (inside per-agent reports, not just on the individual call)
4. Outbound-queue mechanism for Phase 3 (cron-poll vs. Redis-backed) — needs a rough
   daily call-volume estimate once ecomcore's order volume is known
5. Whoever builds `ecomcore`'s `Ecomcore_model` first must fix the exact
   `ecomcore_order_*` hook payload shape (ecomcore §14) — Phase 3 here depends on that
   being stable before it starts
6. Whether/how an inbound WhatsApp reply should flip an ecomcore order to `confirmed`
   is unresolved on the ecomcore side (§2 above) — Phase 3 here should re-check
   `ecomcore-architecture-plan.md`'s state before starting, in case that gap has since
   been resolved differently than assumed here

---

## 11. Process note

Following `ecomcore-architecture-plan.md`'s own convention: this document stays the
fixed pre-implementation reference. If a companion `salesos-v2-ledger.md` (phase
progress tracker) would help, it's a five-minute file to add later — not created here
since it wasn't asked for, but worth doing before Phase 0 actually starts, for the same
reason it mattered for ecomcore: multi-session continuity.
