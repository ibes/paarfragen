---
name: friction
description: Log a friction to FRICTION.md — something annoying, surprising, wrong, or broken just now, or a manual step that had to be repeated. Use the moment it happens, not just when asked and not only when something visibly fails — don't wait for a housekeeping run or for the human to point it out. Also checks whether this is a repeat of an existing entry, since a recurring friction is worth more than a new one.
---

# Friction — notice it, log it, now

Frictions are valuable: they're the raw material for improving how work
gets done here, not noise to get past (`VALUES.md`). Catching one costs a
few seconds; missing it costs the same friction happening again with
nobody able to tell it's the second time.

Not a discussion skill and not a fix skill: by the time this runs, the
friction already happened. Logging it is a checkpoint, not a fix — see
`VALUES.md` and `FRICTION.md`'s own lifecycle note for what happens next.

## When to notice one

Don't wait for something to loudly break. Concrete cues, from what
actually happens during a task:

- A command failed, needed a retry, or needed a workaround before it
  worked.
- Something had to be re-derived by hand (facts, state, a decision) that
  a script, doc, or tool should have just given directly.
- A doc, comment, or convention turned out to not match reality.
- The same multi-step manual sequence got repeated — this session or a
  past one, per `SETUP-LOG.md`/`FRICTION.md`.
- A script/skill/convention was assumed to exist (because something else
  referenced it) and didn't.
- The user corrected a process or behavior mistake, not just a code bug.
- A task took noticeably more steps or time than it should have, for a
  reason that will recur next time too.

A friction doesn't need to block anything or even be fully understood —
that's exactly what makes it cheap to log and easy to skip logging by
mistake.

## Before logging: is this a repeat?

Skim `FRICTION.md` (and recent `SETUP-LOG.md` entries, in case it was
already fixed) for the same root cause under different words. This is a
quick read, not a search tool to build.

- **New** → append it (format below).
- **Repeat of an open `FRICTION.md` entry** → this is the signal
  `VALUES.md` describes: fix it now, high priority but not the highest —
  don't drop the current task, but don't defer this one again either.
  Say explicitly that it's a repeat, then fix it in this same turn if
  it's small enough; only add a note to the existing entry (occurrence
  count or new detail) if the fix genuinely doesn't fit here.
- **Already fixed, but happened again** → the fix didn't hold. Log it as
  a new entry saying so — that's more valuable than a silent second
  workaround.

## Write the entry

Append to `FRICTION.md` using its own format:

```
## YYYY-MM-DD — <short title>

<What happened, one or two sentences. What would have helped, if
obvious.>
```

Low ceremony on purpose — no reasoning section, no permission needed
first (additive, reversible). If a fix is obvious enough to also be
worth an `IDEAS.md` entry, write the `FRICTION.md` line *first* — an
idea write-up doesn't resolve the friction (`CLAUDE.md`).

## Confirm

State the one-line entry title back to the user (or, for a repeat, that
it was a repeat and what you did about it instead of just re-logging).
