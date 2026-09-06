---
name: setup-log
description: Record a setup decision to SETUP-LOG.md — what changed and why. Use right after a script, skill, or convention is added, renamed, removed, changed shape, or an approach was tried and reverted — whenever the reasoning behind a setup change isn't obvious from the diff alone. Fires on its own — don't wait for a housekeeping run.
---

# Setup log — record a decision

One append, then done. Not a discussion skill — by the time this runs, the
decision is already made.

## When to use

- A script, skill, or convention was added, renamed, removed, or reshaped.
- An approach was tried and reverted — often the most valuable entry, since it
  prevents re-trying the same dead end later.
- The user made an explicit call between two options ("let's go with X, not Y").
- Mid-session, not just at housekeeping time — log it when it happens.

Skip: routine content edits, anything the diff already makes self-evident, WIP
not yet settled.

## Write the entry

Append to `SETUP-LOG.md` (create with the standard header — see the existing
file's top — if missing):

```
## YYYY-MM-DD — <short title>

<What changed, one or two sentences.>

**Why:** <the reasoning — what was tried, what was rejected, what tipped the
decision. This is the part that can't be re-derived from the code later.>
```

Keep it tight: 3–6 lines total. Newest entries at the bottom (chronological).

## Confirm

State the one-line entry title back to the user. Don't ask permission first —
this is additive and low-risk; just report what was logged.
