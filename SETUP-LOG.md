# Setup — Evolution Log

History of how this repo's setup was built and why. Not an inventory
(that's `script/help` for commands, the filesystem for everything else)
— just the decisions and the reasoning behind them, so they don't have
to be re-derived or re-litigated later.

---

## 2026-09-05 — Monorepo split: `api/` (PHP) + `frontend/` (Vue/Vite)

Two independent projects, each with its own dependency manifest
(`api/composer.json`, `frontend/package.json`), rather than one Tempest
app serving both API and server-rendered views (as `emsig` does).

**Why:** the frontend is meant to become a PWA now and get wrapped in a
native cross-platform shell (e.g. Capacitor) later, without the API
changing. Coupling them into one framework's request/view cycle (like
emsig's Tempest+Vite setup) would make that split harder later, not
easier. Also deliberate: it keeps the two test suites — phpunit for
`api/`, a JS/TS runner for `frontend/` — fully separate, each runnable
(and green/red) on its own, not interleaved in one framework's test run.

## 2026-09-05 — `api/` hexagonal skeleton, no web framework picked yet

`src/Domain`, `src/Application`, `src/Infrastructure` exist as empty
directories (`.gitkeep`); `composer.json` requires only `php` +
`phpunit`.

**Why:** `emsig` (sibling repo, same author) uses Tempest, but Tempest
`^3.0` requires PHP `^8.5` and this environment only has PHP 8.4.19
installed — couldn't verify an install here. Rather than pick a
framework untested in this environment, left it open in
`specs/STATUS.md` for a real decision once the target runtime (and the
first spec) is known. Domain/Application layers don't need a framework
to start being written anyway.

## 2026-09-05 — `script/` header convention: Description + Side-effects

Every `script/*` file carries `# Description:` and `# Side-effects:`
header comments; `script/help` greps them to generate the command list.

**Why:** copied from `emsig`/`redlich` (same author, same convention) —
the header *is* the documentation, so it can't drift out of sync with a
separately maintained doc.

## 2026-09-05 — Agent-first meta files added before any product code

`CLAUDE.md`, `specs/STATUS.md`, `agent/skills/INDEX.md`, this file, and
`script/{help,setup,qa,check}` were set up before writing any
Domain/Application/Infrastructure code or frontend UI.

**Why:** requested explicitly — the app itself stays a minimal skeleton
for now, but the working conventions (specs first, `script/qa` as the
gate, decisions logged here) should be in place from the first commit,
matching how `emsig`/`redlich` operate.

## 2026-09-05 — Framework decision resolved: Tempest (superseding the open item above)

Human decided: Tempest, same as `emsig`. `api/composer.json` now requires
`php: ^8.5` and `tempest/framework: ^3.0`; `dev-require` phpunit bumped to
`^13.3@dev` to match the version `emsig` already runs successfully on
PHP 8.5 (untested combinations felt riskier to guess at than copying a
known-working pair). Added `api/public/index.php` with the same
`HttpApplication::boot()` entrypoint `emsig` uses, and `api/.env.example`
with the same minimal keys (`SIGNING_KEY`, `APPLICATION_NAME`,
`BASE_URI`, `ENVIRONMENT`) — no feature routes yet, that's still gated on
a spec.

**Couldn't fully verify here:** this sandbox has PHP 8.4.19, not the
required 8.5 — `composer install` correctly refuses on this platform, so
`composer.lock` was generated with `composer update
--ignore-platform-req=php` (resolution only, not an actual working
install) purely to produce a correct, consistent lock file. Installing
PHP 8.5 via apt to test it for real was attempted and blocked — the
`ppa.launchpadcontent.net` PHP package repo isn't on this sandbox's
network allowlist. `vendor/` was never left in a usable state here and
isn't committed (gitignored either way). Real validation needs to happen
on a machine or CI with PHP 8.5 — see `specs/STATUS.md` § Known quirks.

**Sibling `api/`/`frontend/` folders confirmed, not reconsidered:** Tempest
here runs purely as a JSON API (no views, no `vite-plugin-tempest`) — the
"couple frontend+backend into one Tempest app like emsig" alternative
was already rejected in the split decision above, and picking Tempest
doesn't change that reasoning.

## 2026-09-05 — `script/test-api` + `script/test-frontend` split out of `script/qa`

Added vitest + jsdom to `frontend/` (`npm run test`, `vite.config.ts`'s
`test` block, `passWithNoTests: true` since no tests exist yet — mirrors
phpunit's own "No tests executed!"-but-exit-0 behavior on an empty
suite). Split the two test runs into their own scripts;
`script/qa` now just calls both plus the frontend build, instead of
inlining everything itself.

**Why:** explicit goal — being able to run and reason about the PHP
suite and the JS/TS suite separately, not only as one bundled gate. The
directory split already kept the two dependency graphs apart; this
makes the *test invocation* independently runnable too
(`script/test-api` alone, `script/test-frontend` alone), not just the
source layout.

## 2026-09-05 — `specs/api.md`: draft API contract ahead of the first slice spec

Restated the five endpoints from `redlich`'s `paarfrage-exploration-mode.md`
vision doc into a standalone, living contract doc — not a locked spec,
explicitly marked as such at the top of the file. Added one thing that
doc didn't specify (a generic `{"error":{"message":...}}` shape),
flagged inline as this repo's own unconfirmed addition rather than
carried over from the source.

**Why:** `api/` and `frontend/` are separate projects on separate
toolchains (see the two split decisions above) — a written contract is
what lets them actually be built in parallel against the same interface
instead of one side blocking on the other's code. Kept as Markdown, not
OpenAPI/YAML: matches this ecosystem's house style (`redlich`/`emsig`
specs are prose, not schemas) and is cheaper to keep "fluid, revisable"
the way the source vision doc explicitly wants exploration mode to be.
Revisit as OpenAPI later if codegen (TS types, request validation)
becomes worth the added rigor.
