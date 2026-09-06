---
name: grill-me
description: Stress-test a design or draft reference through a relentless, one-question-at-a-time interview until open questions are resolved or explicitly deferred. Use when grilling a design decision or a draft spec, or before locking a spec on a new domain model ("grill me", "stress-test this design").
---

# Grill me — design interview

Relentless, **one question at a time**, until open design questions are
resolved or explicitly deferred.

**Do not code. Do not lock a spec during the grill** — that's a separate
step once the questions here are actually settled.

## Before starting

1. Read [`specs/STATUS.md`](../../../specs/STATUS.md) — phase, next
   step, § Open decisions. What is the grill target?
2. Read the draft under grill — usually
   [`specs/exploration-mode.md`](../../../specs/exploration-mode.md) or
   a `specs/*.md` draft. Skip anything `specs/STATUS.md` § Decided
   already settles — don't re-litigate a locked call.
3. If invoked without a topic, propose one from `specs/STATUS.md` §
   Open decisions or § Next step. Human confirms before Q1.

## During the grill

- **One question per message** — labeled Q1, Q2, Q3…
- Each question: a **recommended answer + short reasoning** — human
  picks or adjusts.
- Answerable from `specs/STATUS.md`, `specs/exploration-mode.md`,
  `specs/api.md`, or the actual code? **Read first** — don't ask what's
  already decided.
- Out-of-scope sub-topic → say so in one line, human decides whether to
  chase it now or defer it.
- Track decisions in the thread as they're made.

### Paarfragen-specific lenses

Real, already-decided constraints from `CLAUDE.md`/`specs/STATUS.md` —
flag a design that quietly contradicts one of these, don't silently let
it slide:

- **No accounts** — `specs/exploration-mode.md`'s `deck_id` bearer
  identity is the starting design; flag anything that assumes login or
  per-person accounts.
- **`api/` and `frontend/` stay decoupled** — flag a design that
  couples them (e.g. server-rendered views, a shared runtime).
- **Hexagonal** — flag if a design forces a framework/DB concern into
  `Domain`/`Application` instead of behind a port.

## After the grill

- List each decision made, one line each.
- **Unresolved** → note it in `specs/STATUS.md` § Open decisions —
  don't let it silently drop.
- **Resolved** → ask the human before editing `specs/STATUS.md` §
  Decided (and `specs/exploration-mode.md`/`specs/api.md` if the
  decision changes what they say) — those are the binding record other
  work builds on, not something to update unasked.
