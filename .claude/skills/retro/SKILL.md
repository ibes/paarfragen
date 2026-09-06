---
name: retro
description: Run a short retro right after a feature/slice/fix is verified done (script/qa green, tests passing) and before moving to the next task. Looks back over the whole build — not just the last thing that happened — for friction that occurred but wasn't logged in the moment, and for reusable gotchas that belong in a reference doc, not just a log. Distinct from housekeeping (session/git hygiene, end of session) and from friction (log one thing, right when it happens) — this is the wrap-up pass across an entire build. Use once real work lands green, or when asked for a retro ("retro", "was hätte das leichter gemacht", "was gab's an friction").
---

# Retro — close out a build properly

By the time this runs, the feature/slice/fix already works. This isn't
about the code anymore — it's about whether the *next* build like it
goes faster, because this one's lessons actually got written down
somewhere a future session will look.

Not a discussion skill: reconstruct, log, report. Ask only where a
finding is genuinely a bigger build (a new script, a `mago.toml`
change) — additive doc/log edits need no permission first, same as
`friction`/`setup-log`.

## Why this exists, not just `friction` run more often

`friction` is designed to fire the moment something happens — but in
practice, mid-build focus is on making the next thing work, and
logging lapses: friction gets *noticed* but not *written down*, and by
the time anyone looks back, the specifics (the exact error, why the
first fix attempt didn't work) are already fading. This skill is the
deliberate second pass that catches what the in-the-moment discipline
missed, while the details are still fresh enough to reconstruct
accurately — not a replacement for logging friction as it happens, a
backstop for when that didn't fully happen.

It also catches a second, different gap: `FRICTION.md` and
`SETUP-LOG.md` are chronological logs, not lookup references. A
genuinely reusable gotcha ("this framework does X, not obvious from
the docs") that only lives in a dated log entry will get rediscovered
from scratch next time, because nobody reads the whole log before
writing new code — they read the reference doc for the thing they're
about to touch. Part of this skill's job is asking, for each finding:
*does this also belong in a reference doc, not just the log?*

## Run it

1. **Reconstruct the build**, not just the last step. Look across the
   whole session (or the relevant span, if the session covers more
   than one thing) for the friction skill's own cues — a command that
   failed/retried, something re-derived by hand that a doc/tool should
   have given directly, a doc/convention that turned out wrong, a
   multi-step sequence repeated, a task that took more steps than it
   should have. Don't stop at the first one found.
2. **Cross-check against what's already logged.** Skim `FRICTION.md`
   and this session's `SETUP-LOG.md` entries. Only the *gap* —
   friction that happened but was never written down — is this skill's
   job. Don't re-log what's already there.
3. **For each unlogged finding, place it correctly:**
   - Happened, not (yet) fixed, no obviously-reusable lesson → new
     `FRICTION.md` entry, that skill's own format.
   - A genuinely reusable gotcha about a framework/library/tool this
     repo depends on → `FRICTION.md` entry **and** a note in the
     relevant reference doc (`api/reference/*.md`, an equivalent
     `frontend/` doc if one exists, or `CLAUDE.md` itself if it's
     repo-wide) — wherever the next session would actually look before
     writing code, not where this retro happened to be run. If no such
     reference doc exists yet for that area, say so rather than
     inventing one on the spot; that's an `IDEAS.md` candidate, not
     something to build mid-retro.
   - A decision was made along the way that `setup-log` should have
     caught but didn't → `SETUP-LOG.md` entry now, per that skill's
     format.
   - A repeat of an existing `FRICTION.md` entry → follow `friction`'s
     own repeat-handling rule (fix it now if small, note the
     occurrence if not) rather than logging it again here.
4. **Ask the meta question directly: what would have made this faster?**
   Not vague ("better docs") — concrete: a missing script, a reference
   doc that was too thin for the area actually touched, a manual step
   repeated three times that a five-line script would remove. A small,
   clearly-scoped, low-risk fix (a doc addition, a short script
   matching an existing `script/*` pattern) can be built now. Anything
   bigger, or that changes shared config (`mago.toml`, CI, a WRITES
   script per `CLAUDE.md`) → propose it, `IDEAS.md` it if not needed
   yet, or ask before building.
5. **Report**, don't just silently edit files:
   ```
   ## Retro — <what was built>

   ### Logged (was friction, wasn't written down)
   - <title> → FRICTION.md [+ reference doc, if applicable]

   ### Would have helped
   - <concrete thing> — built now / proposed / IDEAS.md'd
   ```
   Skip empty sections rather than padding them.

## What this skill is not

- Not a code review — it doesn't re-examine the implementation for
  bugs or design quality.
- Not `housekeeping` — no git/branch/worktree hygiene here, that's a
  session-end concern, this is a build-end one. The two can follow
  each other but don't merge: a retro can happen mid-session, long
  before `housekeeping` would run.
- Not a substitute for logging friction as it happens next time — the
  goal is that this skill finds *less* each time, not that it becomes
  the only place friction ever gets captured.
