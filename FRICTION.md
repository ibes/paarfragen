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

## 2026-09-06 — Ad-hoc Playwright smoke script needed manual path-hunting

Writing a throwaway `.mjs` script (scratchpad, not in the repo) to
drive the live browser smoke test above: `import { chromium } from
"playwright"` fails under plain `node` even with `NODE_PATH` set to
the global `node_modules` — Node's ESM resolver doesn't consult
`NODE_PATH` the way CommonJS `require` does. Had to import the global
package's `index.mjs` by its absolute path instead
(`/opt/node22/lib/node_modules/playwright/index.mjs`). Then
`chromium.launch()` failed again: the executable isn't at
`/opt/pw-browsers/chromium/chrome-linux/chrome` as the environment's
own docs text implies — the real path has a version suffix
(`/opt/pw-browsers/chromium-1194/chrome-linux/chrome`, found via `find
/opt/pw-browsers -iname '*chrome*'`), and needed `args:
["--no-sandbox"]` to launch at all in this sandbox. Worth remembering
verbatim next time an ad-hoc live-browser check is needed here, rather
than rediscovering both quirks again.

A third quirk in the same script: `page.goto(url, { waitUntil:
"networkidle" })` against the Vite dev server hung until Playwright's
own 30s navigation timeout — Vite's HMR client keeps a persistent
WebSocket open, so the page never actually goes network-idle. Known
Playwright+Vite interaction, not a bug in anything here. Fixed by
using `waitUntil: "load"` instead. Same "remember this verbatim"
reasoning as the two quirks above.

## 2026-09-06 — This session's Bash tool blocks bare `cd`, breaks `npm run <script>` in a subdirectory

Running the frontend dev server or any other subdirectory-scoped
command by `cd frontend && ...` is rejected outright ("No `cd` — the
shell persists at the repo root and every script/* self-anchors").
Recurred twice in the same session (starting the Vite dev server, then
again reaching for the smoke script's directory). `npm --prefix
<dir> run <script>` (or an absolute path for non-npm commands) works
every time — worth reaching for directly instead of trying `cd` first
and hitting the block.

## 2026-09-06 — ESLint's `no-undef` doesn't know about tsconfig's DOM lib

Writing `App.vue`'s `onMounted`/`onUnmounted` wiring, `script/check-
frontend-lint` flagged `window` as `'window' is not defined
(no-undef)` — even though `vue-tsc` (the actual type-authority here,
via `@vue/tsconfig/tsconfig.dom.json`) type-checks it correctly. Plain
(non-type-checked) ESLint tracks globals syntactically, with no idea
what `tsconfig.app.json` declares available — a known false-positive
class for `no-undef` in TypeScript projects, which is why
`typescript-eslint`'s own docs recommend turning it off. Fixed by
disabling `no-undef` in `frontend/eslint.config.js` (see that file's
own comment for the reasoning) rather than reaching for a `globals`
npm package to re-declare what TypeScript already knows.

## 2026-09-06 — Tempest's MCP docs say route decorators protect a server's route; the actual routing code ignores them

`tempest/mcp`'s docs (`docs/2-features/20-mcp.md`) say an
`#[McpServer(path: ...)]`'s route can be protected "by adding
middleware through a route decorator." Building
`AppFeedbackServer`'s `/mcp` route (Slice 4), a
`#[WithMiddleware(McpAuthMiddleware::class)]` on the server class
turned out to be a silent no-op: `Tempest\Mcp\McpDiscovery::
registerRoutes()` always points every discovered server's route at
the same generic `Tempest\Mcp\McpHttpController`, with a hardcoded
decorator list (`[new WithoutMiddleware(PreventCrossSiteRequests
Middleware::class)]`) that never reads anything off the server class
itself. Caught only by reading `McpDiscovery.php`'s actual source
instead of trusting the docs sentence — and the framework's own
`IntegrationTest.mcp` test helper (`$this->mcp->onServer(...)`) drives
the protocol in-process via `McpRequestHandler` directly, bypassing
HTTP/middleware entirely, so a test written only against that helper
would never have caught this either; a real HTTP request via
`$this->http->post('/mcp', ...)` was needed. Fixed by making
`McpAuthMiddleware` (`api/src/Infrastructure/Http/
McpAuthMiddleware.php`) a normally-discovered **global** middleware
(same shape as `CorsMiddleware`) that no-ops unless `$request->path
=== '/mcp'`, instead of route-scoped. Would help: whoever adds
another `#[McpServer]` here should assume route decorators on the
server class do nothing, and reach for this same self-scoping-global-
middleware pattern — or the docs' *other* suggestion, "placing the
server behind your existing authentication middleware," which does
hold up since that's exactly a global middleware already. Also added
to `api/reference/tempest.md`'s "Framework gotchas" (found during this
build's retro pass — should have gone there the first time, not just
here).

## 2026-09-06 — A model's `= null` default sends an explicit NULL on insert, breaking a NOT NULL + current-timestamp column

Found during Slice 4's retro: not logged in the moment even though it
cost real debugging time (a `PDOException: NOT NULL constraint failed`
traced back through five layers of router/middleware stack trace).
`AppFeedbackModel::$created_at` was declared `public ?string
$created_at = null;`, mirroring how a "this field isn't always set"
property normally looks. Tempest's `ModelInspector::getPropertyValues()`
(used to build an `INSERT`) only skips a property that's genuinely
*uninitialized* — a typed property with a `= null` default is
initialized the instant the object is constructed, so it got sent as
an explicit `NULL`, tripping the column's `NOT NULL` + `current: true`
default (`CreateAppFeedbackTable`). Fixed by dropping the `= null`
entirely (`public ?string $created_at;`, no default) — same trick
`QuestionFeedbackModel`'s `PrimaryKey $id` already uses for a
sometimes-absent value. Added to `api/reference/tempest.md`'s
"Framework gotchas" so the next model with an optional/DB-managed
column doesn't rediscover this via a stack trace.

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
