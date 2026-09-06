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

## 2026-09-06 — mago flags `mixed-assignment` even right before an `is_*` guard

Reading `Request->get()` (typed `mixed`) into a local variable trips
mago's `mixed-assignment`, even when the very next line narrows it with
`is_string()`/`is_int()` — flow narrowing doesn't reach back to the
assignment itself. First instinct, an `@var string $x` cast right above
the assignment, backfires: mago then treats `$x` as *proven* `string`,
so it flags the immediately-following `is_string($x)` runtime check as
`redundant-type-comparison`/`redundant-logical-operation` — the cast
and the real validation directly contradict each other from the
analyzer's point of view. What actually worked: leave the assignment
genuinely `mixed`, keep the real `is_string()`/`is_int()` guard, and
suppress just the assignment line with `// @mago-expect
analysis:mixed-assignment`. Logged in
`api/src/Infrastructure/Http/QuestionFeedbackController.php` and now
also in `api/reference/tempest.md` so the next Infrastructure
controller reading raw request data doesn't rediscover this by trial
and error. Not filed as a "not now" idea because it's already fixed
and written down — see the reference doc instead.

## 2026-09-06 — Tempest renders a full HTML debug page for 4xx/5xx in `local` env unless `Accept: application/json`

Every non-2xx `Response` Tempest's router produces gets rethrown as
`HttpRequestFailed` (`HandleRouteExceptionMiddleware`) and, in
`ENVIRONMENT=local` with a client that didn't ask for JSON (`httpie`'s
default `Accept: */*`), rendered as a large Tailwind-styled debug page
instead of the JSON body the controller actually returned. Cost real
diagnostic time during manual smoke-testing of `POST
/question-feedback`'s 400 cases — looked like the controller was
broken (wrong status *and* wrong content-type), until re-testing with
an explicit `Accept: application/json` header showed the real,
correct `{"error":{"message":...}}` response underneath. Not a bug:
content negotiation working as designed, PHPUnit's `IntegrationTest`
never surfaces it since it never negotiates content type, and any real
JSON client (a `fetch()` call, `Accept: application/json` explicitly)
gets the real payload. Worth remembering when smoke-testing an API by
hand in `local` env: always pass `Accept: application/json`, or the
debug page will look like the bug. Added to `api/reference/tempest.md`.

## 2026-09-06 — `httpie` needs `--ignore-stdin` when run via the Bash tool

`http POST url key=value ...` fails with "Request body (from stdin,
--raw or a file) and request data (key=value) cannot be mixed" every
time it's run through this session's Bash tool — the tool's own stdin
handling makes `httpie` think a body is piped in, even though it isn't.
`--ignore-stdin` fixes it every time. Not Tempest/mago-specific, just a
tooling quirk worth remembering for the next manual API smoke test in
this kind of session.

## 2026-09-06 — Slice 2's tests never caught a missing CORS setup, only a real browser did

Wiring `frontend/` against the real API (Slice 3), `script/qa` was
fully green — PHPUnit, Vitest with `fetch` mocked, `vue-tsc`, build —
and the app still didn't work: every real browser fetch from
`http://127.0.0.1:5173` (Vite dev server) to `http://127.0.0.1:8000`
(`script/dev-api`) was blocked by the browser's CORS policy, since
`api/` never sent `Access-Control-Allow-Origin`. Neither PHPUnit's
`IntegrationTest` (calls controllers in-process, no real HTTP, no
browser) nor Vitest (fetch is mocked) can catch this class of bug —
only an actual browser hitting an actual cross-origin server does.
Same lesson as Slice 2's `#[Stateless]` miss, this time on the
frontend side: a live smoke test (Playwright against `script/dev-api`
+ `npm run dev`, both real processes) is not optional once two
separately-served halves of the app need to talk to each other.
Fixed with `api/src/Infrastructure/Http/CorsMiddleware.php` — see that
file's own docblock for a second gotcha found getting it right
(middleware priority vs. `MatchRouteMiddleware`, also added to
`api/reference/tempest.md`).
