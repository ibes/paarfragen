# Friction log

Raw, in-the-moment notes — something was annoying, surprising, or broke,
and it's not worth stopping to fix or fully decide right now. Log it the
moment it happens; don't wait for a dedicated pass. Lower ceremony than
`SETUP-LOG.md` on purpose: no reasoning section required, no format to
get right, just enough to jog the memory later.

Not this file:
- A decision already made and acted on → `SETUP-LOG.md`.
- A blocking, must-decide-soon question → `specs/STATUS.md` § Open
  decisions.
- A considered "good idea, not needed yet" → `IDEAS.md`.

## Entry format

```
## YYYY-MM-DD — <short title>

<What happened, one or two sentences. What would have helped, if
obvious.>
```

Writing an `IDEAS.md` entry for the fix does not resolve this — the
friction itself is still live until the fix is actually built. Cross-
reference `IDEAS.md` and keep the entry here. When the underlying gap
is actually fixed: remove the entry, and log the real decision in
`SETUP-LOG.md` instead. Don't let a truly resolved entry linger here.

---

## 2026-09-06 — Task prompt referenced files that don't exist in the repo

The session's task prompt named `frontend/src/api/mockApi.ts`, a
`.claude/skills/spec/` skill, and `specs/2026-09-06-slice-1-question-loop.md`
as an example spec — none exist; `frontend/src/` has no `api/` dir at
all, and `.claude/skills/` only has `friction`/`grill-me`/`housekeeping`/
`setup-log`. `specs/STATUS.md` already stated the real situation
correctly (no spec skill, no product code yet, greenfield). Reading
`STATUS.md` first per the `CLAUDE.md` load path caught the mismatch
before anything was built against the wrong assumption. Would help:
whatever generates/relays these task prompts staying in sync with
`STATUS.md`, since the router already had the right answer.
