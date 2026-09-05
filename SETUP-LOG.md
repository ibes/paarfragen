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

## 2026-09-05 — Tempest's TypeScript generation documented, not wired up

Confirmed against Tempest's own docs/source (cloned `tempest-framework`
to check, not guessed): `#[Tempest\Generation\TypeScript\AsType]` +
`generate:typescript-types` generates `.ts`/`.d.ts` from PHP DTOs/enums;
`tempest/generation` is already pulled in by `tempest/framework` itself,
no new composer dependency. Marked experimental by Tempest. Documented
the mechanism and its hexagonal-layout implication (`#[AsType]` classes
must live in `src/Infrastructure`, per `CLAUDE.md`) in `specs/api.md`.

**Deliberately not decided yet:** where `generate:typescript-types`
should write its output (a directory tree straight into
`frontend/src/...` vs. a single `types.d.ts` in `api/` referenced
across the sibling-folder boundary). Asked the human directly; answer
was to defer until real DTOs exist to generate from, rather than commit
to a directory layout with nothing to test it against yet.

## 2026-09-05 — `.claude/hooks/session-start.sh`: CLI tools + best-effort PHP 8.5

Used the `session-start-hook` skill to add a `SessionStart` hook
(`.claude/hooks/session-start.sh` + `.claude/settings.json`), following
the same "efficient CLI tools" idea as `redlich`'s `.devcontainer/Dockerfile`
(`rg`/`fd`/`jq`/`tree`/`sqlite3`/`shellcheck`/`httpie`/`ast-grep`) —
adapted to a hook instead of a Docker build, since Claude Code on the
web sessions here aren't devcontainer-based. `rg` already ships with
this image; the rest install from Ubuntu's default archive (not a PPA)
and were confirmed to install cleanly. `redlich`'s `gh` and `git-delta`
steps were dropped: `gh`'s own apt source (`cli.github.com`) and
arbitrary GitHub release downloads both hit this session's network
restrictions (see below) — not worth carrying a step that's known to
fail here.

Also has the hook attempt `apt-get install php8.5-cli` (Tempest's
requirement) from the same `ondrej/php` PPA this very image's PHP 8.4
was itself built from. **Confirmed by running the hook live:** that
install is blocked (403) from inside a running session — this
environment's network egress policy denies `ppa.launchpadcontent.net`
at runtime, even though the identical PPA was clearly reachable when
this base image was built (its PHP 8.4 package is a `+deb.sury.org`
build from that exact PPA). Build-time and session-runtime network
access are evidently not the same policy. Left the step in anyway
(harmless — fails soft, doesn't block the rest of the hook) since it
depends on the environment's own network policy, not anything this
repo controls; documented the confirmed failure mode in
`specs/STATUS.md` rather than claiming it works.

**Why not chase this further right now:** getting `api/` to actually
run in a Claude Code web session either needs the environment's network
policy opened up for that PPA (a human, environment-level setting, not
a repo change), or a properly maintained static PHP 8.5 binary from a
trustworthy source — didn't want to pull an unvetted binary from a
random GitHub repo (also blocked here anyway: this session's GitHub
proxy 403'd a release-asset download from a repo outside its scoped
list). Revisit if/when the environment's network policy changes.

## 2026-09-05 — `api/` runs PHP 8.5 in a Docker container, not on the host

Superseded the previous entry's stopping point. Read Claude Code's own
[cloud environments docs](https://code.claude.com/docs/en/cloud-environments)
properly instead of stopping at the empirical 403 — one thing stated
there directly corrects a guess from that earlier entry: **a cloud
environment's "Setup script" runs under the exact same network access
level as the live session**, it doesn't get more access at some
separate "build time." My earlier "build-time and session-runtime
network access are evidently not the same policy" conclusion was
wrong — I inferred it from this base image's PHP 8.4 having been built
from the `ondrej/php` PPA, but that image is built by Anthropic's own
infrastructure, entirely outside any user's environment/network-policy
config — not evidence about setup-script timing at all.

What the docs actually point at: Docker Hub pulls **are** in the
default network access level's allowlist, and "run your own image as a
container alongside Claude with `docker compose`" is the docs' own
suggested way to get a toolchain the base image doesn't ship, since
"replacing the base image entirely isn't supported yet." That's a real,
supported path — unlike guessing at network policy internals.

**What was built:** `docker-compose.yml` (root) + `.devcontainer/Dockerfile`
define an `api` service — `php:8.5-cli-trixie` (confirmed as a real
Docker Hub tag, not guessed) with the PHP extensions `tempest/framework`'s
own `composer.json` actually requires (ext-dom, ext-fileinfo, ext-intl,
ext-libxml, ext-mbstring, ext-pdo, ext-readline, ext-simplexml) plus
pdo_sqlite/sqlite3/curl, Composer (copied from its own official image),
Node 22, and the same kind of CLI tools as `redlich`'s devcontainer
(`rg`/`fd`/`jq`/`tree`/`shellcheck`/`httpie`/`ast-grep` — all from
Debian's default archive or npm, nothing needing a blocked network
path). `script/lib/api-php` routes every `script/*` PHP/composer call
through `docker compose run --rm api ...`; `script/setup`, `script/qa`,
`script/check`, `script/test-api` were updated to use it instead of
bare `composer`/`vendor/bin/phpunit`. `.claude/hooks/session-start.sh`
now builds the `api` image instead of apt-getting PHP onto the VM.

**Why this over an environment network-policy change:** doesn't need
anyone to edit the environment's allowed-domains list at all — Docker
Hub already being trusted by default means this works out of the box.
It's also strictly more useful: the exact same image is now the local
VS Code Dev Container (`devcontainer.json` → `dockerComposeFile` +
`service: api`) for the whole repo, not just a fix for one cloud
session's PHP version. One definition, three consumers (cloud session,
CI later, local Dev Container) instead of three separately-maintained
setups.

**Caching, since a fresh build every session is real cost:** the
environment's own filesystem-snapshot cache (~7 days, covers Docker
images per Claude Code's own docs) only applies to its **Setup
script** — a claude.ai/code environment-dialog field, a different
mechanism from this repo's `.claude/hooks/session-start.sh`. Left a
clear note in `specs/STATUS.md` that someone needs to add `docker
compose build api` there by hand; I have no tool access to that
environment setting from inside a session. Without it,
`session-start.sh` still rebuilds every session — from Docker's own
layer cache, so not fully from scratch, but not as fast as the
environment cache would make it.

**What's still unverified, plainly:** this specific sandbox has no
working Docker daemon (`docker info` fails; `sudo service docker start`
hit a `ulimit` permission error, consistent with nested containers
being disabled here) — so neither `docker compose build` nor a single
container run has actually succeeded anywhere in this repo's history.
Validated everything that didn't need a daemon: `docker compose
config` parses the compose file correctly, `shellcheck` is clean on
every changed script, the PHP extensions were checked against
Tempest's real `composer.json` rather than guessed, and
`docker-php-ext-install` is confirmed (via its own documented
behavior) to skip gracefully rather than fail when an extension turns
out to already be compiled into the base image. The actual build is
the next thing to prove, in a session that has Docker working.
