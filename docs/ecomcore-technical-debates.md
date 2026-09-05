# ecomcore Technical Debates — Implementation-Time Log

Companion to `ecomcore-architecture-plan.md` (§17.3). The plan's own §11/§13 cover
every debate identified and resolved **before** implementation started. This file is
for debates that come up **during** actual coding — things the plan didn't
anticipate. Keep these out of code comments and out of the architecture file.

## How to use this file

Every entry follows the same discipline used throughout the architecture plan — do
not skip steps:

1. **Verify the relevant fact first.** Don't assume framework/library behavior — grep
   the actual code, read the actual schema, check the actual running app, the same way
   the architecture plan's §1 does. Cite `file:line`.
2. **State the real options**, with genuine trade-offs — not a leading question.
3. **Ask the user** (per plan §17.1 — never default a real decision).
4. **Record the decision here**, in the same entry, once made.

Each entry is dated and numbered, newest at the top. Reference the affected
phase/module and, if it changes something written in the main plan, note that too
(but don't edit the main plan's historical §11/§13 — this file is the record of
what happened after that point).

---

## Entry template (copy this for each new debate)

```
### TD-<N> — <short title> (<YYYY-MM-DD>, Phase <N> / <module>)

**Context:** what came up, and why the existing plan doesn't cover it.

**Verified facts:** file:line citations, actual behavior confirmed — not assumed.

**Options considered:**
- (A) ...
- (B) ...

**Decision:** what the user chose, and why.

**Impact:** which files/schema/phase this touches. Does it change any acceptance
criterion in plan §15? If so, note the update here (don't silently edit §15).
```

---

## Log

_(no entries yet)_
