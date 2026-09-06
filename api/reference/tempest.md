# Tempest — house rules

Tempest v3 framework behavior worth knowing before writing
Infrastructure code — not this repo's own conventions (there are none
yet; `src/` is still empty, see `IDEAS.md`'s DTO-rules entry) and not
a copy of Tempest's own docs (those ship in-repo, see below).

Tempest is the **Infrastructure adapter only** — `api/README.md`.
Hexagon still wins in `Domain/`/`Application/`.

## Framework gotchas (not obvious from a quick skim)

- **POST field mapping is exact-name, no case conversion.** Tempest
  maps a POST field to a `*Request` property by exact name — no
  automatic `snake_case` → `camelCase`. A form field
  `name="return_url"` will **not** fill `public string $returnUrl`;
  it silently stays at its default. Match the HTML `name` to the PHP
  property name.
- **Middleware is global by default.** Any class implementing
  `HttpMiddleware` is auto-discovered and applied to **every** route
  (`HttpMiddlewareDiscovery`). The per-route
  `#[Get(..., middleware: [X::class])]` argument is *additive on top
  of* that global registration, not a scope — a middleware meant for a
  few routes will otherwise run on every page, and run twice on the
  routes that name it. To scope one: add `#[SkipDiscovery]` to the
  class, then opt in per route via `middleware:`.
- **`<template>` + Tempest's `:if`/`:foreach` directives.** Never wrap
  visible UI in an HTML `<template :if="…">` or `<template
  :foreach="…">`. Browsers treat `<template>` as inert — its content
  is hidden, not rendered — but Tempest's server-side rendering still
  includes the markup, so a test asserting on the raw HTML (`assertSee`)
  can pass while the page looks empty in a real browser. Use
  `<div :if="…">` for conditionals and `<x-template :foreach="…">`
  (a Tempest component, not the HTML tag) for loops.
- **A bearer-token JSON API controller needs `#[Stateless]`.** Without
  it, Tempest sets a session cookie on *every* response and its
  `PreventCrossSiteRequestsMiddleware` 403s any request that doesn't
  carry a same-origin `Sec-Fetch-Site` header — both wrong for a
  `deck_id`-style bearer API with no login/session (this repo's own
  auth model, `specs/api.md`), and the CSRF check specifically breaks
  a legitimate cross-origin call from a decoupled `frontend/` origin.
  `#[Stateless]` on the controller class disables both, plus
  `ManageSessionMiddleware`/`SetCookieHeadersMiddleware`
  (`Tempest\Router\Stateless`'s own source). PHPUnit's
  `IntegrationTest` doesn't catch a missing `#[Stateless]` — it never
  inspects `Set-Cookie` or sends real `Sec-Fetch-*` headers; only a
  live smoke test (`php -S` + a real HTTP client) surfaces it.
- **4xx/5xx responses render as a full HTML debug page in
  `ENVIRONMENT=local`, unless the client sends
  `Accept: application/json`.** Every non-2xx `Response` gets rethrown
  as `HttpRequestFailed` (`HandleRouteExceptionMiddleware`) and, in
  `local` env without a JSON `Accept` header (e.g. plain `httpie`),
  rendered as a Tailwind debug page instead of the controller's real
  JSON body — looks exactly like the controller is broken. It isn't:
  content negotiation working as designed. Always pass
  `Accept: application/json` when smoke-testing an endpoint by hand.
- **A middleware that must run before routing (CORS preflight,
  anything that needs to answer for a path/method with no registered
  route) needs a priority below `Tempest\Router\MatchRouteMiddleware`'s
  own (`Priority::FRAMEWORK - 29`, i.e. `-30`).** `MatchRouteMiddleware`
  returns its own `NotFound` directly, without calling `$next()`, the
  moment nothing matches — a lower-priority (numerically higher, per
  `Tempest\Support\Priority`: lower number runs first) middleware
  downstream never even runs for that request. An `OPTIONS` preflight
  almost never matches a real route (only `GET`/`POST`/… are
  registered), so a global CORS middleware has to sit *before*
  `MatchRouteMiddleware` — e.g. `#[Priority(-50)]` — not rely on the
  normal global-middleware-runs-on-every-route assumption. See
  `api/src/Infrastructure/Http/CorsMiddleware.php`.
- **`tempest/mcp`: a route decorator on the `#[McpServer]` class does
  nothing.** The docs (`docs/2-features/20-mcp.md`) say the server's
  auto-registered `path` route can be "protected... by adding
  middleware through a route decorator" — untrue for the actual
  routing. `Tempest\Mcp\McpDiscovery::registerRoutes()` always points
  every discovered server's route at the same generic
  `Tempest\Mcp\McpHttpController`, using a hardcoded decorator list
  that never reads anything off the server class. A
  `#[WithMiddleware(...)]` on the `#[McpServer]` class is silently a
  no-op. To actually protect it: a normally-discovered **global**
  middleware (same shape as `CorsMiddleware` above) that scopes
  *itself* to the server's path (`$request->path === '/mcp'`) and
  no-ops otherwise. Also: the framework's own `IntegrationTest.mcp`
  test helper (`$this->mcp->onServer(...)`) drives the protocol
  in-process via `McpRequestHandler` directly — it bypasses HTTP and
  middleware entirely, so it can't be used to test this either; use a
  real `$this->http->post($mcpPath, ...)` request instead. See
  `api/src/Infrastructure/Http/McpAuthMiddleware.php` and
  `FRICTION.md`.
- **A model property with a `= null` default is sent as an explicit
  `NULL` on insert — it is NOT the same as "leave it to the column
  default."** Tempest's `ModelInspector::getPropertyValues()` (used to
  build an `INSERT`) skips a property only if it is genuinely
  *uninitialized*; a typed property with a default value is
  initialized the moment the object is constructed, default and all.
  For a nullable column with a `NOT NULL` + `current: true` default
  (`CreateTableStatement::datetime(..., current: true)`), that explicit
  `NULL` trips the `NOT NULL` constraint. Declare the property with
  **no default at all** (`public ?string $handled_at;`, not `= null`)
  to exclude it from inserts while it stays fully readable after a
  `select()` populates it via reflection — same trick `PrimaryKey $id`
  already relies on for a server/client-generated id. See
  `api/src/Infrastructure/Persistence/AppFeedbackModel.php`.
- **The model-based `SelectQueryBuilder` has no `distinct()`** — only
  `CountQueryBuilder` does (checked the source, not assumed). For a
  small, deck-scoped result set, dedupe in PHP after `->all()` (an
  array keyed by the column, then `array_keys()`) rather than reaching
  for a raw-SQL escape hatch — see
  `DatabaseQuestionFeedbackRepository::listRatedQuestionIds()`.

## View / Request / Bindable — the object shapes Tempest expects

- A **View** class implements `View` (+ often `IsView`), sets
  `$this->path` to its co-located template, and is what a controller
  returns — a concrete union (`QuestionShown|NotFound`), not a bare
  `View` type, so callers know what they're getting.
- A **Request** class implements `Request` (+ `IsRequest`) and maps
  incoming POST/route data to typed properties.
- A **Bindable** resolves a route parameter: implements `Bindable`,
  `resolve(): ?static` — returning `null` becomes an HTTP 404
  automatically, instead of a manual "not found" check in the
  controller.

## Writing Tempest code that also satisfies Mago

`mago.toml`'s `[analyzer]` runs on `api/src` + `api/tests`
(`mago.toml`'s `[source]`) — real analyzer diagnostics, not just style.
Two patterns worth knowing before the first attempt:

- **Reading raw `Request` data:** `Request->get()` is `mixed`. Assigning
  it to a variable trips `mixed-assignment` even one line before an
  `is_string()`/`is_int()` guard — flow narrowing doesn't reach back to
  the assignment. Don't "fix" it with an `@var string $x` cast: mago
  then treats the variable as *proven* `string`, and flags the real
  validation right after it as `redundant-type-comparison`. Instead,
  leave the assignment genuinely `mixed`, keep the real `is_*` guard,
  and suppress only the assignment line: `// @mago-expect
  analysis:mixed-assignment`. (`analysis:` is the analyzer-issue prefix
  — the same `@mago-expect` comment Tempest's own source uses for
  `lint:*` codes works for analyzer codes too, confirmed empirically;
  `bin/mago analyze --list-codes` lists every valid code.) See
  `api/src/Infrastructure/Http/QuestionFeedbackController.php`.
  **Don't copy-paste another file's `@mago-expect` block for the same
  `query()` stub-typing issue** — the exact codes and count depend on
  how many properties/constructor args that call site touches (e.g.
  `DatabaseQuestionRepository::all()`'s 2-property loop needed
  `invalid-property-access`×2 + `null-argument`×1, while
  `DatabaseAppFeedbackRepository::listUnhandled()`'s 4-property loop
  needed `mixed-assignment`×1 + `mixed-property-access`×4 +
  `mixed-argument`×3 — different codes entirely, not just a different
  count). Run `bin/mago analyze <file>` directly and use its exact
  `warning[...]`/`error[...]` codes. The same stub-typing gap also
  shows up as `less-specific-return-statement` when a method's return
  type is narrower than what mago infers from an untyped `$row` (e.g.
  `array_keys()` on an array keyed by `$row->question_id` infers
  `list<array-key>`, wider than a declared `string[]`) — same fix,
  suppress the return line.
- **Mago's `too-many-methods` lint caps a class's method count** —
  hits PHPUnit test classes fastest, since one behavior often needs
  several small test methods. Don't fight it with a bigger threshold;
  split the test class by concern instead, the same way this repo
  already splits `AppFeedbackControllerTest`/`AppFeedbackMcpTest`/
  `McpAuthMiddlewareTest` and `QuestionFeedbackControllerTest`
  (writes)/`QuestionFeedbackListTest` (reads) — each stays focused and
  under the cap without disabling the rule.
- **`*.config.php` discovery files need an explicit namespace.**
  `mago.toml`'s `[guard.perimeter]` only permits a Tempest dependency
  from a namespace it recognizes (`Paarfragen\Infrastructure\`,
  `Paarfragen\Tests\`, …); a config file with no `namespace` statement
  at all (Tempest's own docs never show one) reads as the global
  namespace to the guard, which no `permit` rule covers, so any
  `use Tempest\...` in it gets flagged as an illegal dependency. Give
  it a namespace matching where it lives (e.g. `namespace
  Paarfragen\Tests;` for `api/tests/database.testing.config.php`) —
  Tempest's own discovery doesn't care about the namespace, only the
  file suffix and the returned object's type.

## When you need real API detail

Full docs ship in-repo at `api/vendor/tempest/framework/docs/` — load
the relevant chapter on demand rather than guessing or relying on
possibly-stale training data. Worth knowing where to look before
starting: routing, views, database, config, and testing chapters each
have their own file under `1-essentials/`.
