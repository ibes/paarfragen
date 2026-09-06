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
