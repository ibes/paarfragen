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

## When you need real API detail

Full docs ship in-repo at `api/vendor/tempest/framework/docs/` — load
the relevant chapter on demand rather than guessing or relying on
possibly-stale training data. Worth knowing where to look before
starting: routing, views, database, config, and testing chapters each
have their own file under `1-essentials/`.
