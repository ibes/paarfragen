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

## 2026-09-05 — Correction: the daemon works; the real blocker is one CDN host

The "no working Docker daemon" line above was wrong, found out an hour
later in the same investigation. `sudo service docker start`'s own
init script fails on a `ulimit` call this sandbox doesn't permit —
running `sudo dockerd` directly instead works fine (`docker
info`/`docker pull` both then succeed). Worth recording as a mistake,
not quietly editing away: I reported "no working Docker daemon in this
sandbox" as settled fact in the previous entry and to the human,
without having tried the one obvious workaround.

The actual, now-confirmed blocker: `docker pull`/`docker compose build`
for *any* Docker Hub image (tested `php:8.5-cli-trixie`, `composer:2`,
and bare `alpine`/`hello-world` — all four, identical failure) 403s on
`production.cloudfront.docker.com`. Confirmed via the agent proxy's own
failure log showing `connect_rejected` specifically for that host —
not inferred from the error message alone. This environment allows the
*other* Docker Hub CDN host, `production.cloudflare.docker.com`
(reachable, confirmed separately), just not this one. So "Docker Hub
is in the Trusted default allowlist" (per Claude Code's own docs) is
true but incomplete for this environment: Docker Hub apparently serves
layers from more than one CDN backend, and only one of them is
allowed. This is a narrower, more precise problem than "third-party
PPAs are blocked" was — one specific domain, not a category — and
`specs/STATUS.md` names the exact fix: switch this environment's
Network access to **Custom** and add
`production.cloudfront.docker.com` to the allowed list.

Net effect: the Docker Compose approach itself (image choice,
Dockerfile content, `script/lib/api-php` routing) is unchanged and
still believed sound — it's still blocked from actually running here,
same as the PPA approach was, just by a single missing domain instead
of a blocked category. Once that domain is allowed, the real
`docker compose build` verification `specs/STATUS.md` calls for can
finally happen.

## 2026-09-05 — Repo content in English; translated what had drifted into German

House rule, made explicit in `CLAUDE.md`: docs, code, comments, and
commit messages are English regardless of what language a session was
conducted in. Audited the whole tracked tree for German text (grepped
for umlauts/German words) and found it had drifted into four files —
`README.md` (fully German), `frontend/src/App.vue`'s placeholder
paragraph, `frontend/index.html`'s `lang="de"`, and the PWA manifest's
`description: 'Fragen für Paare'` in `frontend/vite.config.ts`. All
translated to English; `script/test-frontend` and `frontend`'s build
re-verified green afterward.

**Why call this out rather than just fix it silently:** the app's own
name and its source vision doc are German, so a German end-user UI is
a plausible real outcome later — this rule is about repo/session
language, not a decision that the product itself will ship in English.
Recorded that distinction as an open item in `specs/STATUS.md` rather
than letting the placeholder text's language quietly stand in for a
decision nobody's actually made.

## 2026-09-05 — `production.cloudfront.docker.com` fix confirmed; new blocker found one layer in (`deb.debian.org`)

A human added `production.cloudfront.docker.com` to this environment's
Network access allowlist and started this new session to verify it.
Confirmed fixed: `sudo dockerd` (daemon start, same workaround as
before), then `docker pull hello-world` succeeded, and `docker compose
build api` got past both `FROM php:8.5-cli-trixie` and `FROM
composer:2` — full pull and layer extraction, no CDN 403.

**New blocker, same root cause, different host:** the build then hit
`apt-get update` (installing `libicu-dev` etc. before
`docker-php-ext-install`) failing with 403 on every `deb.debian.org`
source (`trixie`, `trixie-updates`, `trixie-security` — this base
image's default sources.list routes all three through `deb.debian.org`
itself, not a separate `security.debian.org` host). Didn't assume this
was the same class of problem — verified it: `docker run --rm
php:8.5-cli-trixie` probing `deb.debian.org` directly showed plain
HTTP getting a 403 and HTTPS getting intercepted with a certificate
issued by `O=Anthropic, CN=Egress Gateway SDS Issuing CA (production)`
for `CN=*.debian.org` (via `openssl s_client -connect
deb.debian.org:443 -servername deb.debian.org | openssl x509 -noout
-issuer -subject`). That's this environment's own egress gateway, the
same mechanism that gated the Docker CDN host — just a different
domain not yet on the allowlist.

**Not fixable from inside this session** (same as the CDN host
before): logged the exact host and the certificate-issuer evidence in
`specs/STATUS.md` rather than guessing at a workaround (no alternate
mirror substituted, no `--no-check-certificate`-style bypass) and
stopped to ask, per this task's own instruction to check rather than
self-diagnose when a network block repeats. `script/qa` was not run —
the build still isn't green.

## 2026-09-05 — Checked every remaining host the pipeline needs, not just the one that failed

Asked to verify whether more domains would need allowlisting before
going back to the human a third time. Rather than fixing one blocker
and waiting to discover the next by re-running the build, walked the
whole `script/qa` chain (both Dockerfile apt stages, `composer
install`, `npm install`) and tested every external host it touches.

**Method:** a bare TLS probe (`openssl s_client` + `x509 -noout
-issuer`) only proves a host is being intercepted by this
environment's egress gateway (`O=Anthropic, CN=Egress Gateway SDS
Issuing CA (production)`) — it can't distinguish "intercepted and
allowed through" from "intercepted and blocked," since both look like
a MITM'd cert to a container that doesn't trust that CA. Fixed that by
mounting this session's own trusted bundle
(`-v /root/.ccr/ca-bundle.crt:/tmp/ca.crt:ro`) into a throwaway
`php:8.5-cli-trixie` container and `curl --cacert`-ing each host's
*real* endpoint (not `/`, which 404s harmlessly on API-only hosts
regardless of policy — e.g. `repo.packagist.org/`) — a genuine 200/302
with real payload means allowed, a 403 means blocked.

**Found a second blocked host that hadn't failed yet:**
`deb.nodesource.com` (the Dockerfile's Node 22 apt source, the stage
right after the PHP-extension one). Would have been next session's
"new blocker found one layer in" if not caught now. Everything else
the pipeline needs came back genuinely reachable: `repo.packagist.org`
(real `p2/*.json` metadata), `api.github.com` (this lock file's
packages all dist from GitHub zipballs, not Packagist mirrors — real
302 to `codeload.github.com`, which itself returns real content),
`github.com` (git-upload-pack works, matches this session's own
already-working git push/fetch), `registry.npmjs.org` (real package
metadata).

**Why record the negative results too, not just the blocker:** so the
next session doesn't re-derive "does packagist/npm/github work" from
scratch, and so the human adding `deb.debian.org` this time also adds
`deb.nodesource.com` in the same pass instead of a third round-trip.
