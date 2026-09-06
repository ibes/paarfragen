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

## 2026-09-06 — Tempest's `query()` mistypes rows as class-strings under mago

`query()`'s `@param TModel $model` binds `TModel` to the literal
class-string passed in, not to instances of it, so every row from
`->select()->all()`/`->select()->get(...)` gets mistyped — property
access and passing a row on both get flagged. Recurred three times now
(`DatabaseQuestionRepository`, `DatabaseAppFeedbackRepository`,
`DatabaseQuestionFeedbackRepository`), each needing its own
`@mago-expect analysis:*` suppressions — the exact codes/count differ
per call site (see `api/reference/tempest.md`'s "Writing Tempest code
that also satisfies Mago"), so don't copy-paste another file's block;
run `bin/mago analyze <file>` and use its real codes. The proper fix
(patch mago's bundled Tempest stub, or report the imprecise
`@param TModel $model` upstream) is more than this cosmetic dev-tool
annoyance currently justifies — not filed anywhere actionable from
this session (Tempest's own repo is outside this session's GitHub
scope).

## 2026-09-06 — This session's Bash tool has three sharp edges

- `httpie` needs `--ignore-stdin` — without it, `http POST url
  key=value` fails claiming a body came from stdin, even though none
  did; the tool's own stdin handling confuses it.
- No bare `cd` — `cd frontend && ...` is rejected outright ("the shell
  persists at the repo root"). Use `npm --prefix <dir> run <script>`
  (or an absolute path) instead. Recurred at least three times across
  the session before it stopped being a reflex mistake.
- `run_in_background: true` on a command that already backgrounds
  itself with a trailing `&` reports "[exited with code 0]" almost
  immediately, looking like the process died — it didn't; only the
  outer wrapper shell exited, and the real process (a dev server, a
  preview server) is still running underneath, causing "port already
  allocated" on a well-meaning retry. Recurred once after first being
  logged (Slice 4 → Slice 5) before it stuck; Slice 6 avoided it
  cleanly. Fix: pass the plain foreground command, no trailing `&`, no
  output redirection — let the tool handle backgrounding itself, never
  combine the two.

## 2026-09-06 — A user message can reference an unlabeled artifact from a *different* session on the same repo

The user answered "Q1" of a `GET /question-feedback` grill with just
"keep as full history" — this session had no record of Q1 (that grill
hadn't started here). Two wrong guesses before an open "what does this
refer to?" question surfaced the real cause: the user was mid-grill in
a *different* Claude Code session on this same repo/branch and assumed
this one shared that context. `git log`/`git status`, checked directly,
would have confirmed the mismatch (clean tree, no matching in-progress
spec file) on the first guess instead of the third. Would help: when a
message references a specific unlabeled artifact ("Q1", "the spec
we're writing", a decision "we already made") that isn't anywhere in
this session's own visible history, check `git log`/`git status`/
`specs/STATUS.md` first — "does this exist here" beats semantic
guessing.
