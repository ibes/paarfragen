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

**Recurred same session:** a later task message referenced a
`run-frontend` skill at `frontend/.claude/skills/run-frontend/` using
`script/dev-frontend` — neither exists (`frontend/.claude/` has no
`skills/` dir, `script/` has no `dev-frontend`). Same root cause, not a
new entry.

## 2026-09-06 — Tempest's `query()` mistypes rows as class-strings under mago

Tempest's `query()` helper (`api/vendor/tempest/framework/packages/database/src/functions.php`)
is annotated `@template TModel` / `@param TModel $model`, but its real
signature is `string|object $model` and every doc example calls it as
`query(Book::class)->...`. With mago's `tempest` integration enabled,
calling `query(QuestionModel::class)` binds `TModel` to the literal
class-string type, not to `QuestionModel` instances — every row from
`->select()->all()`/`->select()->get(...)` then gets typed as
`class-string('...QuestionModel')`, so property access on a row
(`$row->id`) is flagged `invalid-property-access`, and passing it on
triggers `null-argument`. A `@var` override doesn't fix it (mago reports
"no overlap" between the annotation and its own inferred type); neither
does swapping `array_map` for `foreach`. Worked around in
`api/src/Infrastructure/Persistence/DatabaseQuestionRepository.php`
with `@mago-expect analysis:*` suppressions (confirmed that prefix
works for analyzer codes, not just the `lint:*` ones already used in
Tempest's own source) plus a comment explaining why. Whoever hits this
again: don't re-diagnose from scratch — same root cause will recur on
every `query(SomeModel::class)` call. Worth reporting the imprecise
`@param TModel $model` upstream to Tempest, or patching mago's bundled
tempest-integration stub if this repo ever vendors its own.

## 2026-09-06 — `httpie` needs `--ignore-stdin` when run via the Bash tool

`http POST url key=value ...` fails with "Request body (from stdin,
--raw or a file) and request data (key=value) cannot be mixed" every
time it's run through this session's Bash tool — the tool's own stdin
handling makes `httpie` think a body is piped in, even though it isn't.
`--ignore-stdin` fixes it every time. Not Tempest/mago-specific, just a
tooling quirk worth remembering for the next manual API smoke test in
this kind of session.

## 2026-09-06 — This session's Bash tool blocks bare `cd`, breaks `npm run <script>` in a subdirectory

Running the frontend dev server or any other subdirectory-scoped
command by `cd frontend && ...` is rejected outright ("No `cd` — the
shell persists at the repo root and every script/* self-anchors").
Recurred twice in the same session (starting the Vite dev server, then
again reaching for the smoke script's directory). `npm --prefix
<dir> run <script>` (or an absolute path for non-npm commands) works
every time — worth reaching for directly instead of trying `cd` first
and hitting the block.

## 2026-09-06 — Bash tool's `run_in_background: true` on a command that already backgrounds itself with `&` reports "exited" while the process is still alive

Starting `script/dev-api`/`script/dev-frontend` for Slice 4's live
smoke test, the first attempt ran `script/dev-api > log 2>&1 &` *and*
passed `run_in_background: true` to the Bash tool. The tool reported
"[exited with code 0]" almost immediately — read as the dev server
having failed to start — but a later `curl` against the port it was
supposed to bind succeeded, and a retry attempt failed with "port
already allocated." The first process was never dead; only the outer
wrapper shell (the one holding the trailing `&`) exited immediately,
which is what the tool's exit-code report actually reflects when a
command backgrounds itself a second time on top of the tool's own
backgrounding. Cost a few minutes of confused re-diagnosis (restart
attempt, port-conflict error, only then checking with `curl` whether
the "exited" process was actually still serving). Would help: when
using `run_in_background: true`, pass the plain foreground command
(`script/dev-api`, no trailing `&`, no output redirection) and let the
tool handle backgrounding itself — never combine the two.

**Recurred same session:** starting `npm run preview` for Slice 5's
own live PWA verification, immediately after logging this — same
`command > log 2>&1 &` plus `run_in_background: true` combination,
same misleading "exited" report. Self-caught on the very next attempt
this time (recognized the pattern instead of re-diagnosing from
scratch), but the mistake itself still happened once more — the "would
help" above evidently isn't front-of-mind enough by itself; worth
extra care the next few times this comes up.

## 2026-09-06 — A user message can reference an unlabeled artifact from a *different* session on the same repo

The user answered "Q1" of a `GET /question-feedback` grill with just
"keep as full history" — no other context, and this session had no
record of Q1 ever being asked (that grill hadn't started here). Guessed
twice (a `FRICTION.md`/`IDEAS.md` pruning-policy question, then
git-history-rewriting) before asking an open "what does this refer
to?", which surfaced real user frustration
("Woher kommt die Verwirrung?!") before the actual cause came out: the
user was mid-grill in a *different* Claude Code session on this same
repo/branch and assumed this session shared that context. `git log`/
`git status`, checked directly, immediately confirmed nothing matched
(clean tree, no in-progress spec file) — that check would have caught
the mismatch on the first guess instead of the third. Would help: when
a message references a specific unlabeled artifact ("Q1", "the spec
we're writing", a decision "we already made") that isn't anywhere in
this session's own visible history, check `git log`/`git status`/
`specs/STATUS.md` first, before guessing at what it might mean — "does
this exist here" beats semantic guessing, and surfaces a cross-session
mismatch immediately instead of after several wrong guesses.
