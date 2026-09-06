# Setup — Evolution Log

History of how this repo's setup was built and why. Not an inventory
(that's `script/help` for commands, the filesystem for everything else)
— just the decisions and the reasoning behind them, so they don't have
to be re-derived or re-litigated later.

---

## 2026-09-05 — Monorepo split: `api/` (PHP) + `frontend/` (Vue/Vite)

Two independent projects, each with its own dependency manifest
(`api/composer.json`, `frontend/package.json`), rather than one Tempest
app serving both API and server-rendered views.

**Why:** the frontend is meant to become a PWA now and get wrapped in a
native cross-platform shell (e.g. Capacitor) later, without the API
changing. Coupling them into one framework's request/view cycle would
make that split harder later, not easier. Also deliberate: it keeps the
two test suites — phpunit for
`api/`, a JS/TS runner for `frontend/` — fully separate, each runnable
(and green/red) on its own, not interleaved in one framework's test run.

## 2026-09-05 — `api/` hexagonal skeleton, no web framework picked yet

`src/Domain`, `src/Application`, `src/Infrastructure` exist as empty
directories (`.gitkeep`); `composer.json` requires only `php` +
`phpunit`.

**Why:** Tempest was the leading framework candidate, but Tempest
`^3.0` requires PHP `^8.5` and this environment only has PHP 8.4.19
installed — couldn't verify an install here. Rather than pick a
framework untested in this environment, left it open in
`specs/STATUS.md` for a real decision once the target runtime (and the
first spec) is known. Domain/Application layers don't need a framework
to start being written anyway.

## 2026-09-05 — `script/` header convention: Description + Side-effects

Every `script/*` file carries `# Description:` and `# Side-effects:`
header comments; `script/help` greps them to generate the command list.

**Why:** the header *is* the documentation, so it can't drift out of
sync with a separately maintained doc.

## 2026-09-05 — Agent-first meta files added before any product code

`CLAUDE.md`, `specs/STATUS.md`, `agent/skills/INDEX.md`, this file, and
`script/{help,setup,qa,check}` were set up before writing any
Domain/Application/Infrastructure code or frontend UI.

**Why:** requested explicitly — the app itself stays a minimal skeleton
for now, but the working conventions (specs first, `script/qa` as the
gate, decisions logged here) should be in place from the first commit.

## 2026-09-05 — Framework decision resolved: Tempest (superseding the open item above)

Human decided: Tempest. `api/composer.json` now requires
`php: ^8.5` and `tempest/framework: ^3.0`; `dev-require` phpunit bumped to
`^13.3@dev` to match a version already confirmed to run successfully on
PHP 8.5 elsewhere (untested combinations felt riskier to guess at than
copying a known-working pair). Added `api/public/index.php` with
Tempest's standard `HttpApplication::boot()` entrypoint, and
`api/.env.example` with the minimal keys Tempest needs (`SIGNING_KEY`,
`APPLICATION_NAME`, `BASE_URI`, `ENVIRONMENT`) — no feature routes yet,
that's still gated on a spec.

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
"couple frontend+backend into one Tempest app" alternative was already
rejected in the split decision above, and picking Tempest doesn't
change that reasoning.

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

Restated the five endpoints from the exploration-mode design input
(`specs/exploration-mode.md`) into a standalone, living contract doc —
not a locked spec, explicitly marked as such at the top of the file.
Added one thing that doc didn't specify (a generic
`{"error":{"message":...}}` shape), flagged inline as this repo's own
unconfirmed addition rather than carried over from the source.

**Why:** `api/` and `frontend/` are separate projects on separate
toolchains (see the two split decisions above) — a written contract is
what lets them actually be built in parallel against the same interface
instead of one side blocking on the other's code. Kept as Markdown, not
OpenAPI/YAML: prose is cheaper to keep "fluid, revisable" the way
exploration mode explicitly wants to be. Revisit as OpenAPI later if
codegen (TS types, request validation) becomes worth the added rigor.

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
(`.claude/hooks/session-start.sh` + `.claude/settings.json`) that
installs a set of efficient CLI tools
(`rg`/`fd`/`jq`/`tree`/`sqlite3`/`shellcheck`/`httpie`/`ast-grep`) —
a hook rather than a Docker build, since Claude Code on the web
sessions here aren't devcontainer-based. `rg` already ships with this
image; the rest install from Ubuntu's default archive (not a PPA) and
were confirmed to install cleanly. `gh` and `git-delta` were left out:
`gh`'s own apt source (`cli.github.com`) and arbitrary GitHub release
downloads both hit this session's network restrictions (see below) —
not worth carrying a step that's known to fail here.

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
Node 22, and the same kind of CLI tools as the session-start hook
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

## 2026-09-05 — `session-start.sh` didn't actually start the Docker daemon

Asked to close every gap that would stop the next session from going
straight down the happy path once the network allowlist is fixed.
Found one: `.claude/hooks/session-start.sh` gates its `docker compose
build api` step behind `docker info`, but nothing in the hook, or
anywhere else in this repo or the container image, ever runs `dockerd`
itself. `service docker start` is the normal way that would happen and
it fails here (the `ulimit` issue from the entry above) — so every
session, including this one, started with no daemon running at all
until a person or agent noticed and ran `sudo dockerd` by hand. The
hook's own warning path ("Docker isn't available/running — skipping")
made this look like an expected, already-handled case rather than a
bug, which is exactly why it went unnoticed until asked to verify the
happy path specifically.

**Fix:** added a `docker daemon` step to the hook, before the image
build, that checks `docker info` and — only if it's not already
up — starts `dockerd` via `sudo nohup dockerd
>/var/log/dockerd-session-start.log 2>&1 & disown`, then polls
`docker info` for up to 15s before continuing. `nohup` + `disown`
rather than a bare `&`: the daemon needs to keep running for the rest
of the session after this hook's own script has exited, not just for
the duration of this command.

**Verified, not assumed:** stopped the daemon (`sudo pkill -f
'bin/dockerd'`) and ran the whole modified `session-start.sh`
end-to-end from cold. Confirmed: daemon start step fires and succeeds,
`docker compose build api` then runs (still fails at the
`deb.debian.org` apt stage, exactly the known/expected blocker — no
new failure introduced), CLI tools and `frontend/`'s `npm install`
both complete cleanly. Once `deb.debian.org` and `deb.nodesource.com`
are both allowlisted, this hook should take a fresh session all the
way to a built image and installed dependencies with no manual step.

Also tightened the hook's header comment, which claimed Docker Hub
pulls were "allowed under this environment's default network access"
— not true, confirmed the hard way this session: it took an explicit
Custom-allowlist entry for `production.cloudfront.docker.com`. Updated
it to point at `specs/STATUS.md` for the current, accurate host list
instead of asserting a fact that turned out to be wrong.

## 2026-09-05 — Dockerfile installed extensions PHP 8.5 already bundles

`docker-php-ext-install` was building `intl`, `pdo_sqlite`, `sqlite3`,
`curl`, `dom`, `simplexml`, `readline` as shared extensions. `php -m`
on the base image shows every one but `intl` is already compiled in.
Trying anyway broke once `apt-get update` finally got past the
network blocker (see next entry): PHP 8.5 ships `sqlite3`/`curl`/`dom`/
`simplexml`/`readline`'s `ext/*/config.m4` as `config0.m4` — a stub
only the top-level `./buildconf` turns into `config.m4`, for
extensions meant to be bundled into core rather than built standalone
— so `phpize` can't find a config.m4 for them and
`docker-php-ext-install` fails with "Cannot find config.m4."
Reproduced by extracting `docker-php-source` and running
`docker-php-ext-install` one extension at a time.

**Fix:** only install `intl` (the one genuinely missing extension),
and dropped the now-unneeded apt packages (`libsqlite3-dev`,
`libxml2-dev`, `libcurl4-openssl-dev`, `libreadline-dev`) — only
`libicu-dev` remains. This is what got `docker compose build api`
past the extension stage for the first time; the network blocker had
masked this bug in every prior attempt.

## 2026-09-05 — First green `script/qa`: a container-trust gap, not a policy block, was the last blocker

With `deb.debian.org` and `deb.nodesource.com` newly allowlisted, the
build reached `apt-get update` (works — Debian's own sources.list here
is plain `http://`, no TLS involved) but then failed on the
Dockerfile's `curl -fsSL https://deb.nodesource.com/... | bash` step
with "self-signed certificate in certificate chain". Verified via
`openssl s_client` and by mounting `/root/.ccr/ca-bundle.crt` into a
throwaway container and curling with `--cacert`: nodesource returns a
real HTTP 200 once the gateway's CA is trusted — never blocked.
Checked further and found the same failure, `--cacert` or not, against
every HTTPS host tried from inside a plain container — including ones
already confirmed reachable (`registry.npmjs.org`, `api.github.com`,
`repo.packagist.org`, even `deb.debian.org` itself over HTTPS). That
ruled out a per-host policy gap: `/root/.ccr/README.md`'s own "docker
build / docker run" section documents exactly this as a standing
property of Claude Code Remote sandboxes — containers can't reach the
session's local proxy or trust its CA — and names the fix. Without it,
`composer install`/`npm install` inside the `api` container would have
hit the identical wall the moment the build got that far, regardless
of any allowlist change.

**Fix, kept portable so it's a no-op outside this sandbox:**
`session-start.sh` now writes `/root/.ccr/ca-bundle.crt` to a gitignored
`.devcontainer/session-ca.crt` before building and passes a
`HAS_SESSION_CA` build-arg (a flag only — the PEM text itself goes
through the copied file, not the build-arg, because passing the full
certificate as a `--build-arg` value overflowed the shell's argument
list). The Dockerfile's `COPY .devcontainer/session-ca.cr[t] ...` glob
is a no-op when the file doesn't exist, and `update-ca-certificates`
only runs when the flag is set — so a real, non-sandbox build never
touches any of this. Also pointed `NODE_EXTRA_CA_CERTS` at the same
file, since Node keeps its own bundled CA list and ignores the system
trust store update.

One more, unrelated, fix needed to reach a clean run: `npm install -g
@ast-grep/cli` collided with Debian's own `/usr/bin/sg` (the
`login`/shadow-utils "run as a different group" command). Fixed with
`--force` — ast-grep's `sg` is what this image's shell is meant to
expose at that name.

**End state:** `docker compose build api`, `script/test-api`, and
`script/qa` are all green for the first time ever in this repo.

## 2026-09-05 — `script/check` is now a dispatcher speaking one JSON contract

`script/check` globs `script/check-*` and folds each result into
`{status, checks: [...]}` (`script/lib/check-report.sh`); a check may
speak `{status, violations, total, summary?}` natively
(`script/lib/check-contract.sh`), detected via `jq -e '.status'`, or
just be a plain tool (composer, vue-tsc) that falls back to
`{name, status, exit, raw_output}` — `raw_output` omitted when clean,
so a passing run stays a one-liner per check. The two existing checks
moved into `script/check-composer` and `script/check-frontend-types`.
`script/qa` now runs `script/check` first, as a hard gate — a new
`check-*` joins both by construction, no wiring needed anywhere.

**Why:** the human asked for qa output to be compact, LLM-legible JSON
rather than prose, and for future conventions (git-staging discipline,
hexagonal boundaries) to be enforced *in* qa instead of only asserted
in CLAUDE.md. Deliberately small: no `--project` multi-repo dispatch
(paarfragen is one project) and no `--human` streaming mode (JSON is
the only output shape needed right now, not two).

## 2026-09-05 — Mago: hexagonal guard enforced in code, not just CLAUDE.md

Added `mago.toml` (namespace `Paarfragen\`, paths `api/src`/`api/tests`)
— formatter, linter, analyzer, and a
`[guard]` that mechanically enforces the layering CLAUDE.md used to
only assert: Domain permits nothing but `@native`; Application adds
Domain; Infrastructure adds Domain + Application + `Tempest\**`; plus
structural rules (Infrastructure classes final, Application use-cases
readonly, Domain has no base class outside its own `…\Exception\`).
`script/check-mago` folds `format --check` + `lint` + `analyze` +
`guard` (each run at `--minimum-fail-level=note` — the strictest
setting, so even a Note-level finding fails) into one
`{status, violations, total, summary}` report; `script/check` picks
it up automatically. `script/format`/`lint`/`analyze`/`guard` are the
write-capable/ad-hoc counterparts. `bin/mago` is a pinned binary
(`script/lib/mago-install`, gitignored),
fetched by `script/setup` and `session-start.sh`, not a composer
package. CLAUDE.md's architecture bullet now says a violation fails
`script/qa`, not just "please keep this framework-free."

**Why:** the human asked for Mago specifically, "as strict as
possible," and for CLAUDE.md compliance asks to become qa gates
wherever that's possible instead of staying prose. `src/Domain`,
`src/Application`, `src/Infrastructure` are still empty `.gitkeep`
placeholders — the only moment to draw this boundary before real code
could violate it. Verified against real (throwaway) violations before
committing: a Domain class importing `Tempest\Router\Get`, a
non-final Infrastructure class, and a non-readonly Application class
were all caught and reported with the right file/line before being
deleted again. Left out `*Request`/`*View`/`Bindable` structural
rules and any severity downgrades — the former assume Infrastructure
conventions paarfragen hasn't chosen yet (would be guessing), and
lowering a rule's severity contradicts "as strict as possible."

## 2026-09-05 — git pre-commit hook runs script/check

`script/hooks/pre-commit` runs `script/check`; `script/hooks/install`
copies it into `.git/hooks/pre-commit` (worktree-aware via
`git rev-parse --git-path hooks`), called from `script/setup`.
Bypassable with `git commit --no-verify`, same as any git hook.

**Why:** `script/check` already existed as a fast, read-only gate but
nothing ran it automatically before a commit — generic bash, no
adaptation needed beyond the commit message. Verified end-to-end: a
script missing its header, staged and committed, was blocked with the
exact check-script-integrity violation before the hook was removed
again for the real commit.

## 2026-09-05 — Recalibrated Mago: "as strict as useful", not "as strict as possible"

`script/check-mago` now gates lint/analyze at `--minimum-fail-level=warning`
instead of `note`; `mago.toml` explicitly re-elevates `no-debug-symbols`
and `invalid-open-tag` to `error` since Mago itself defaults those two to
Note despite them being real bugs, not style. Guard is untouched — every
guard finding is architectural, there's no cosmetic tier to filter there.

**Why:** the human pushed back on "as strict as possible" — the goal is
keeping agents on a healthy path and giving new code a clear standard to
match, not maximizing friction. Checked empirically before changing
anything: ~40% of Mago's default-enabled rules sit at Note/Help
(`no-redundant-parentheses`, `no-redundant-final`, naming-casing
nitpicks, …) — real cleanup suggestions, but hard-blocking a commit on
them added friction without matching value, especially since
`script/format`/`script/lint --fix` already clears most of that tier in
one command. Verified the new threshold still catches everything that
matters: a `var_dump()` left in code and a missing type hint both still
fail `script/check-mago`; a purely cosmetic Note-level finding no longer
does. Structural/perimeter guard rules from the previous entry stay as
strict as before — architecture boundaries drawn before any real code
exists are exactly the kind of standard worth setting high per the
human's own framing ("mit leerem Repo ist es leichter").

## 2026-09-05 — ESLint + Prettier for frontend/, calibrated the same way as Mago

`frontend/eslint.config.js` (flat config): `@eslint/js` recommended +
`typescript-eslint` `recommended` + `eslint-plugin-vue`'s
`flat/recommended`, `eslint-config-prettier` last to disable any
stylistic rule Prettier already owns. `no-console` raised to error —
the JS analogue of Mago's `no-debug-symbols`. `script/check-frontend-lint`
folds Prettier's `--list-different` + ESLint's `--format json` into the
same `{status, violations, total, summary}` contract used everywhere
else; `script/format-frontend`/`script/lint-frontend` are the
write-capable counterparts. `.editorconfig`'s header now credits
Prettier alongside Mago.

**Tried `typescript-eslint`'s `recommendedTypeChecked` first, dropped
it:** plain typescript-eslint doesn't resolve `.vue` SFC types the way
`vue-tsc`'s own language-service plugin does — `main.ts`'s
`createApp(App)` came back "unsafe argument of type error" on the very
first real file, a tool-interop gap, not a real bug. `vue-tsc`
(`script/check-frontend-types`) already gives full type-safety on real
code, so this stays syntactic-only rather than fighting that interop
for marginal extra coverage — same "strict as useful" call as the Mago
recalibration above, just found on the frontend side this time.

**Bug caught while testing the red path (worth noting — a real bash
footgun):** `check-frontend-lint`'s first draft parsed Prettier's own
`--check` text output, which puts a `[warn] Code style issues found in
the above file...` summary line through the exact same prefix as a
per-file line — it got parsed as a fake "file". Fixed by switching to
`--list-different`, which prints only file paths, nothing else.
Separately, that same draft made script/check-frontend-lint report
"broken" (exit 2) on every real violation, not "red": bash's ERR trap
fires on a failing command inside a plain `var=$(cmd)` assignment even
though `set -e` itself is documented to exempt that exact form — an
inconsistency between `errexit` and the `ERR` trap that isn't obvious
from either one's docs. Every other `check-*` script already guards
this correctly (`|| true` / `|| rc=$?` on anything expected to
sometimes fail); this one didn't, until the red-path test caught it.
Re-audited every other `check-*`'s `var=$(...)` assignments against
this exact pattern before calling it done — all already correct.

Also ran `script/format-frontend` once, immediately after: `App.vue`,
`main.ts`, `style.css`, `vite.config.ts` had never seen Prettier before
(quotes, semicolons). Reformatted now, while it's four small files,
rather than letting the diff grow.

## 2026-09-05 — tsconfig.app.json: noUncheckedIndexedAccess

Added explicitly — `@vue/tsconfig`'s `tsconfig.lib.json` variant ships
this (`array[i]` types as possibly-`undefined`), but the
`tsconfig.dom.json` this project extends doesn't. Cheap, real
strictness win, no code exists yet to be affected either way.

## 2026-09-05 — Made the repo self-contained: no more references to sibling repos

This session's access to the `redlich`/`emsig` sibling repos ends going
forward, so every reference to them was removed or restated:

- `specs/exploration-mode.md` is new — a full, standalone restatement of
  the product vision that used to live only as a link to `redlich`'s
  `VISION/paarfrage-exploration-mode.md` (product pitch, core loop,
  rating scale, full data model, sync/offline behavior, frontend
  storage and screen layout, explicit out-of-scope list — none of that
  detail existed anywhere in this repo before now, only a two-line
  sketch in `specs/STATUS.md`). `specs/api.md` and `specs/STATUS.md` now
  point at this local file instead of the external one.
- Every `SETUP-LOG.md`/`api/README.md`/`.devcontainer/Dockerfile`
  comment that credited a decision to "matching emsig" or "same idea as
  redlich's devcontainer" was reworded to state the reasoning on its
  own terms — the underlying facts and decisions are unchanged, only
  the now-unfollowable cross-repo pointer is gone.

**Why call this out as its own entry:** the vision-doc restatement in
particular was a real information-loss risk, not just a style cleanup —
`redlich`'s vision doc had significant product detail this repo had
never actually captured, only linked to. Read it in full and restated
it before removing the link, rather than after.

## 2026-09-05 — FRICTION.md + IDEAS.md: a self-improvement loop lighter than harvest/kaizen

Two new root-level files, both append-only, no tooling behind either:
`FRICTION.md` for raw, in-the-moment notes (something annoying/
surprising, not worth stopping for), `IDEAS.md` for considered "good
idea, not needed yet" entries with enough context to act on later
without re-deriving it. `CLAUDE.md` points at both. Seeded `IDEAS.md`
with six items worth remembering but deliberately not built this
session: a CI workflow, a test-conventions doc (TDD order + no
cosmetic assertions), `dependency-cruiser` for frontend boundaries, a
named spec-template shape, Mago structural rules for a
not-yet-chosen Infrastructure DTO convention, and revisiting Mago's
size-rule thresholds / typescript-eslint's type-checked linting once
real code exists to judge either against.

**Why:** this repo has no self-improvement mechanism at all —
`SETUP-LOG.md` only records decisions already made and acted on, with
nowhere for "noticed, not deciding yet" to live in between. Designed
together with the human rather than built unilaterally, explicitly as
a lighter version of what `SETUP-LOG.md`'s own past entries described
(now removed) as two known, heavier mechanisms elsewhere: one an
append-only JSONL ledger with dedup and a scheduled review cadence,
the other a periodic review skill coupled to a slice/phase system.
Neither fits a repo with zero product code and no build cadence yet.
Deliberately no review skill either, for the same reason — one is
worth building once these files are long enough that scanning them by
hand stops working, not before.

Also the concrete answer to "how do proven insights from elsewhere
survive losing access to where they were found": write them down now,
in this repo's own words, before the access is gone — same principle
`specs/exploration-mode.md` (previous entry) already applied to the
product vision.

## 2026-09-06 — `VALUES.md`: two standing values, not another log

Human-requested: a place for foundational values that should guide
decisions here, distinct from the other files because it's not a log of
events — it doesn't grow entry-by-entry the way `SETUP-LOG.md`/
`IDEAS.md`/`FRICTION.md` do. Two values recorded: prefer simple tools
over impressively complex ones, and treat friction as something to
solve, not just to name or document (lean/kaizen).

**Why:** `CLAUDE.md` says what to do; nothing said *why*, for the cases
a rule doesn't already cover. Referenced from `CLAUDE.md`'s
Non-negotiables so a session actually reads it rather than it becoming
a file nobody opens. The second value directly reinforces the
`FRICTION.md`/`IDEAS.md` lifecycle rule added earlier today (writing an
idea isn't resolving the friction) — stating it as a value rather than
only as a procedural note makes the intent explicit for cases that
mechanism doesn't cover.

## 2026-09-06 — Friction value: high priority, explicitly not highest

Sharpened same-day: lean's actual claim is that improving how the work
gets done outranks the work itself, so a recurring `FRICTION.md` entry
should get high priority — not deferred indefinitely — but not the
highest either, so it doesn't preempt whatever real task is in progress
for every papercut.

**Why:** the original wording ("gets solved, not just logged") named
the failure mode but not where the fix sits relative to everything
else competing for attention — without a priority band it either reads
as "drop everything" or, more likely in practice, as no priority at
all. Naming it explicitly as high-but-not-highest gives a session
something concrete to weigh a stumbled-over friction fix against the
task at hand, instead of either extreme.

## 2026-09-06 — Built `script/repo-hygiene` + paarfragen's own `housekeeping` skill

Closed the `FRICTION.md` entry from earlier today by actually building
the fix, per the value just recorded (a recurring friction is the
signal to build, not to describe better): `script/repo-hygiene` reports
branch/upstream/ahead-behind, dirty-tree counts, worktrees, and a
pre-graded `FINDINGS:` section (dirty-tree, unpushed-commits,
stale-merged-branch — the last one already caught a real stale local
branch, `claude/docker-api-build-test-r5bjv0`, merged into `main` and
safe to delete).

**Naming, deliberately not `check-repo-hygiene`:** `script/check` globs
`script/check-*` into `script/qa`'s hard pass/fail gate. A stale branch
or an unpushed commit is a session-hygiene note, not a reason to fail
the build — naming it `check-*` would have silently pulled a reporter
into the hard gate the first time `script/qa` ran. Named it
`script/repo-hygiene` instead; documented the reasoning inline so it
doesn't get "corrected" back to `check-*` by a future session matching
the `/housekeeping` skill's own wording.

**Bigger finding along the way:** paarfragen had no `housekeeping`
skill of its own at all — every earlier `/housekeeping` run in this
repo was actually resolving redlich's or emsig's `.claude/skills/
housekeeping`, found on disk as sibling directories, not anything this
repo defines. That's a live gap against the earlier self-containment
work ("paarfragen shouldn't need redlich/emsig visible") — it was
reference-clean in its own files but still runtime-dependent on those
repos existing beside it. Fixed by adding this repo's own
`.claude/skills/housekeeping/SKILL.md`, adapted from redlich's (dropped
the `/kaizen` cross-reference and `script/worktree rm` — paarfragen has
neither a kaizen skill nor a worktree script; plain `git worktree
remove` instead).

## 2026-09-06 — `friction` skill, own `setup-log` skill, `dockerd` self-heal

Human asked for a skill that recognizes friction on its own and logs it
to `FRICTION.md` — added `.claude/skills/friction/SKILL.md`: concrete
noticing cues (failed command, hand-re-derived fact, doc/reality
mismatch, repeated manual sequence, assumed-but-missing convention),
a dedup step against existing `FRICTION.md`/`SETUP-LOG.md` entries
before appending, and the existing "log friction before writing an
idea" ordering rule. Deliberately just a skill, not the ledger/CLI/
review-cadence machinery `IDEAS.md`'s "Extended harvest" entry
describes — that's still premature.

While wiring `CLAUDE.md`'s "Setup decisions" bullet to a same-named
skill, found `setup-log` had the identical gap `housekeeping` had:
referenced by name, resolving only via redlich's `.claude/skills/`
on disk. Added paarfragen's own copy, same reasoning as the
`housekeeping` fix above.

Used the new `friction` skill immediately and it caught a real,
already-recurring one: `dockerd` had needed a manual `sudo nohup
dockerd` restart mid-session twice already today (session-start.sh
only starts it once, at session start) — logged, then fixed per
`VALUES.md` (recurring friction, high priority, same turn) rather than
left open. Extracted `ensure_dockerd()` into
`script/lib/ensure-dockerd.sh`, sourced by both `session-start.sh` and
`script/lib/api-php` (the single chokepoint every Docker call already
went through) so any Docker call self-heals instead of just failing
with a static message. Verified by killing `dockerd` mid-session and
confirming `script/lib/api-php php -v` self-healed and still ran.

## 2026-09-06 — Audited for more silent redlich/emsig infrastructure

Human asked whether anything else currently only works because
redlich/emsig happen to be mounted beside this repo (won't be true next
session). Checked skills, subagent types, hooks, MCP config, and
leftover text references.

**Found, not fixed (deliberately):** several other skills
(`tempest-check`, `hexagon-check`, `bug-report`, `grill-me`, `kaizen`,
`pre-review`, `slice-gate`, `build-loop` from emsig; `spec` from
redlich) and three emsig subagent types (`emsig-verifier`,
`review-subagent`, `status-scout`) currently show up as "available" in
this session too. Unlike `housekeeping`/`setup-log`, these don't
silently produce plausible-but-wrong output if invoked here — they
point at relative paths only their home repo has (`agent/reviews/
*.md`, `data/runner/`, `specs/TRACKS.md`) and would fail loudly. They
also weren't actually used anywhere in this repo's own history. Nothing
to port: they'll simply stop appearing once redlich/emsig access ends,
which is the correct outcome, not a regression. Preserved the one
genuinely reusable pattern — `hexagon-check`/`tempest-check`'s
"structural guard misses semantic drift, so pair it with a checklist
skill" idea — in `IDEAS.md`, since that insight would otherwise be lost
along with the access.

**Confirmed clean:** hooks are scoped per repo's own `.claude/
settings.json` via `CLAUDE_PROJECT_DIR` (redlich's hooks never actually
ran against this repo); no `.mcp.json` or user-level settings reference
either sibling; a full grep of this repo's own tracked files turns up
`redlich`/`emsig` only in `SETUP-LOG.md`'s own historical entries, which
is what that file is for.

## 2026-09-06 — `grill-me` skill, adapted (not ported) from emsig

Human wanted the `grill-me` skill adopted: a relentless, one-question-
at-a-time design interview that resolves or explicitly defers open
questions before a spec locks. Added `.claude/skills/grill-me/
SKILL.md`, rewritten rather than copied — emsig's version is tied to
files and concepts this repo doesn't have (`specs/inbox/`, `specs/
reference/glossary.md`/`work-model.md`, a vault sync step, "Emsig-
specific lenses" about German club volunteer workflows). Kept the
actual technique (one question per message, recommend + reason, track
decisions, explicit resolved-vs-deferred handoff) and pointed it at
this repo's real files instead (`specs/STATUS.md`, `specs/
exploration-mode.md`, `specs/api.md`). Replaced the domain-specific
lenses with this repo's own already-decided constraints (no accounts/
`deck_id` identity, `api/`+`frontend/` decoupling, hexagonal) rather
than inventing placeholder ones.

## 2026-09-06 — `api/reference/php.md` + `tempest.md`, distilled from emsig

Human asked for an opinion on emsig's `agent/reference/*.md` docs
(PHP/Tempest house rules) before access to that repo ends. Assessment:
most of it is Emsig's own app conventions (DaisyUI components, German
i18n catalog, `FlashMessage` enum, a `Guide` feature module) — not
reusable here — but a real core is framework/language-level knowledge
that doesn't depend on this repo having any Domain/Infrastructure code
yet, unlike a naming/DTO convention: the PHP 8.2–8.4 syntax table,
guard-clause readability rules, and genuine Tempest v3 framework
gotchas (exact-name POST-to-`*Request` field mapping — confirmed real
by finding Tempest's own `MapFrom` attribute, which would be pointless
if mapping already did case conversion; middleware being global by
default via `HttpMiddlewareDiscovery`, confirmed present in the
vendored source; browsers hiding `<template>` content while Tempest
still renders inside it).

Added `api/reference/php.md` and `api/reference/tempest.md` with just
that distilled core — dropped Homebrew-PHP paths (this repo only runs
PHP in Docker), the glossary-locked-enum rule (no glossary mechanism
here), and every Emsig-app-specific example. Linked from `api/README.md`
under a new "House rules" section, which also dropped its now-stale
"not yet build-tested end-to-end" line (superseded by the session that
got `docker compose build api` fully green).

## 2026-09-06 — Removed the leftover `agent/` folder; committed fully to `.claude/`

Human decided against carrying emsig's agent-tool-neutral split
(`agent/` for real content, `.claude/` as thin pointers into it) for
this repo — this repo only targets Claude Code, and every skill built
here already went straight into `.claude/skills/*` without anyone
missing the indirection. Removed `agent/skills/INDEX.md` (the only
file under `agent/`, a leftover from the very first commit, unreferenced
by anything current) and the now-empty `agent/` tree.

Found while doing this: `agent/skills/INDEX.md` had survived the
earlier "silent redlich/emsig infrastructure" audit, because that audit
grepped for the literal strings `redlich`/`emsig` — this file names
neither, but still told a reader to reach for `spec`/`kaizen`/
`bug-report` as "global skills," which was already false by the
housekeeping/setup-log fixes earlier in this session, and would have
been misleading regardless. It was also stale on its own terms
("no project-specific skills yet" — four already existed). Widened the
sweep while here and found one more of the same shape:
`specs/STATUS.md`'s "Next step" told a reader to write the first spec
"using the `spec` skill" — a skill that, like `spec`/`kaizen`/
`bug-report`, only exists in the sibling repos. Reworded it to say so
directly rather than naming a skill this repo doesn't have.

## 2026-09-06 — Third value: product over system

Human self-flagged a tendency to build the system instead of the
product, and asked for pushback against it going forward. Added as a
third `VALUES.md` entry rather than only a one-off remark, since this
session is itself the evidence: roughly ten commits today, all
tooling/docs/skills, none touching `api/src/` (still empty `.gitkeep`
placeholders) or locking a spec.

**Why a value, not a script:** unlike the other two values, there's no
clean mechanical check for "is this system work earning its keep" —
it's a judgment call each time a next task is chosen, which is exactly
what `VALUES.md` is for (guidance for situations `CLAUDE.md`'s rules
don't cover). Deliberately did not build a tripwire script/counter for
this — would be system work justified by a value about not doing too
much system work.
