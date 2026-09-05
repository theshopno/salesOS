# ecomcore Build Ledger

Companion to `ecomcore-architecture-plan.md` (§17.3). This file is the **live,
authoritative record of build progress** — update it the moment a phase starts,
blocks, or finishes, not retroactively. Before starting any work in a new session,
read this file first to find the true current phase (per §17.2 — one module at a
time, no exceptions).

Do not edit `ecomcore-architecture-plan.md` to reflect progress — that file stays a
fixed pre-implementation reference. Progress lives here.

---

## How to use this file

- One row per phase, in the fixed order from plan §12/§15.
- Status values: `Not Started` / `In Progress` / `Blocked` / `Done`.
- A phase only moves to `Done` when **every** acceptance criterion listed for it in
  plan §15 is actually verified true — not "looks done," not "should work."
- If `Blocked`, fill in the Blocker column and stop — do not start the next phase to
  work around a block (plan §17.2). Resolve it (asking the user if it requires a
  decision, per §17.1) before moving on.
- Add a dated log entry under the relevant phase for anything worth remembering:
  what was built, what was tested, what surprised you. Short is fine — this is a
  progress trail, not prose.

---

## Phase status

| Phase | Module | Status | Started | Finished | Blocker (if any) |
|---|---|---|---|---|---|
| 0 | Environment check | Not Started | | | |
| 1 | ecomcore | Not Started | | | |
| 2 | inventory | Not Started | | | |
| 3 | wcsync | Not Started | | | |
| 4 | purchases | Not Started | | | |
| 5 | pos | Not Started | | | |
| 6 | returns | Not Started | | | |
| 7 | courier | Not Started | | | |
| 8 | fraudcheck | Not Started | | | |
| 9 | ordernotifier | Not Started (interview pending — see plan §16.1/§17.1) | | | |

---

## Phase logs

### Phase 0 — Environment check
_(no entries yet)_

### Phase 1 — ecomcore
_(no entries yet)_

### Phase 2 — inventory
_(no entries yet)_

### Phase 3 — wcsync
_(no entries yet)_

### Phase 4 — purchases
_(no entries yet)_

### Phase 5 — pos
_(no entries yet)_

### Phase 6 — returns
_(no entries yet)_

### Phase 7 — courier
_(no entries yet)_

### Phase 8 — fraudcheck
_(no entries yet)_

### Phase 9 — ordernotifier
_(no entries yet — cannot start until the §16.1 interview is done; log the interview
answers here once held, then proceed)_
