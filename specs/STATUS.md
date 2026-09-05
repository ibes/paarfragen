# STATUS

Session router — read this first, every session. History and full
reasoning behind any decision below live in `SETUP-LOG.md`; this file
is the current state, kept short on purpose.

## Right now

**`production.cloudfront.docker.com` fix confirmed — `docker pull` now
works.** In this session: `sudo dockerd` (daemon), then `docker pull
hello-world` succeeded, and `docker compose build api` got past the
`FROM php:8.5-cli-trixie` / `FROM composer:2` stages (both pulled and
extracted cleanly). The Docker Hub CDN blocker from the previous
session is resolved.

**New, different blocker found one layer in — same root cause, new
host: `deb.debian.org` is also not on this environment's network
allowlist.** The Dockerfile's `apt-get update` (needed to install PHP
extensions before `docker-php-ext-install`) 403s on every
`deb.debian.org` source (`trixie`, `trixie-updates`,
`trixie-security` — this image routes all three through the same
host, not a separate `security.debian.org`). Confirmed as the
environment's own egress policy, not a Debian-side or transient issue:
a direct HTTPS probe to `deb.debian.org:443` from inside the container
gets a certificate back issued by `O=Anthropic, CN=Egress Gateway SDS
Issuing CA (production)` for `CN=*.debian.org` — the same interception
pattern that produced the earlier Docker Hub 403, just gating a
different domain now that the first one is open.

**Fix (needs a human, same mechanism as last time):** add
`deb.debian.org` to this environment's Network access settings
(Custom level), then start a **new session** — network policy is
fixed at session start. **First thing to do in that new session:**
run `script/test-api` (or `docker compose build api` directly) and
confirm the `apt-get update` step now succeeds — don't assume it does.
If it still 403s on `deb.debian.org`, the settings change didn't take
(not saved, not yet propagated). If it fails on a *different* host
than `deb.debian.org`, this same pattern is repeating — check which
host via the same certificate-issuer probe used here (`openssl
s_client -connect <host>:443 -servername <host> | openssl x509 -noout
-issuer -subject`) rather than guessing, then report it the same way
this entry does. If it fails for a reason unrelated to network egress,
see "Docker for `api/`" below for what to check first (extension
names, `trixie` package naming).

## Phase

**Greenfield setup.** Repo skeleton exists (`api/` hexagonal PHP on
Tempest, `frontend/` Vue/Vite PWA), no product code yet, no spec
written.

A `VISION/`-style pre-spec input exists upstream, in the `redlich`
sibling repo:
[`paarfrage-exploration-mode.md`](https://github.com/ibes/redlich/blob/main/VISION/paarfrage-exploration-mode.md) —
a single shared-device question deck, rating loop, `deck_id` bearer
identity, no accounts. It's a design input, not a spec: restate what's
needed from it into a real spec here before building against it (same
rule `redlich`'s own `VISION/README.md` states — specs restate, never
cite by path). It says nothing about tech stack; every stack decision
below came from the human directly.

## Next step

No locked slice spec yet. Before writing any Domain/Application/
Infrastructure *implementation*, write one using the `spec` skill —
talk to the human about scope, don't assume the vision doc above is
ready to build as-is.

[`specs/api.md`](api.md) already drafts the request/response contract
for the first slice's five endpoints, so `api/` and `frontend/` can be
built against a shared interface once that spec exists, without one
side waiting on the other. It's a draft, not locked — keep updating it
as either side hits a gap.

## Decided

- **API framework: Tempest** (`^3.0`), used as a pure JSON API — no
  server-rendered views, no `vite-plugin-tempest`. `api/README.md`.
- **`api/` and `frontend/` stay decoupled** — sibling top-level dirs,
  independent toolchains — so the frontend can later be wrapped in a
  native shell (e.g. Capacitor) without touching the API's deploy
  lifecycle.
- **`api/` (PHP 8.5) runs in Docker, not on the host** —
  `docker-compose.yml` + `.devcontainer/Dockerfile`, invoked via
  `script/lib/api-php`. Same image doubles as the local VS Code Dev
  Container. See "Docker for `api/`" below for current status.
- **API contract drafted ahead of implementation** — `specs/api.md`.
- **Repo content is English** (docs, code, comments, commits) —
  `CLAUDE.md`. The product's own end-user UI language is a separate,
  still-open decision — see below.

## Open decisions (not yet made — ask before assuming)

- **Where `generate:typescript-types` (Tempest → TS type generation,
  `specs/api.md`) writes its output** — deferred until real
  Infrastructure DTOs exist to generate from.
- **Auth model** for two people sharing a question deck. Vision doc
  proposes a hardcoded, opaque `deck_id` bearer token (no login) for
  its exploration stage — not yet confirmed for this repo.
- **Data storage** — nothing wired yet. Vision doc sketches
  `questions` / `question_feedback` / `app_feedback` tables; needs a
  spec before becoming schema.
- **Deploy target's actual PHP version** — assumed ^8.5 somewhere real
  (matching `emsig`'s toolchain), not confirmed.
- **End-user UI language** — repo content is English by house rule,
  but the product's name and source vision doc are German; a
  German-language UI is plausible but not decided. Current placeholder
  frontend text is English only because it's a placeholder. Decide
  before writing real UI copy.

## Docker for `api/` — current status

Build-tested for the first time this session; gets further than ever
before but still not green end-to-end. What's confirmed:

- The Docker **daemon itself works** in this dev sandbox (`sudo dockerd`
  directly, not `sudo service docker start` — that init script hits a
  `ulimit` call this sandbox disallows).
- **`production.cloudfront.docker.com` is now allowed** — `docker pull
  hello-world`, and the `php:8.5-cli-trixie` / `composer:2` base-image
  pulls inside `docker compose build api`, all succeed.
- **Blocked on:** the Dockerfile's `apt-get update` (installing PHP
  extension build deps) 403s on `deb.debian.org` — a *different* host
  than the Docker CDN one, same environment-allowlist root cause. See
  "Right now" above for the fix and what only a human can do.
- `docker-compose.yml` parses correctly (`docker compose config`); the
  Dockerfile's package/extension list is grounded in real sources
  (Tempest's actual `composer.json` requirements, Docker Hub's real
  `php:8.5-cli-trixie` tag) — not guessed, but also not yet proven by
  an actual successful build, since the build still doesn't get past
  `apt-get update`.
- Once `deb.debian.org` is unblocked and the build gets further: likely
  places to check next are an extension name mismatch, or `trixie`
  (Debian 13) having renamed one of the `-dev` packages installed
  before `docker-php-ext-install`. Not yet checked because the build
  hasn't reached that step.
- `script/qa` was **not** run this session — the build never reached a
  green state, so per the task's own instruction there was nothing to
  validate end-to-end yet.
- Separately, **not yet done and not fixable from inside a session:**
  add `docker compose build api` to this environment's **Setup
  script** field (claude.ai/code environment dialog — a different
  mechanism from this repo's `.claude/hooks/session-start.sh`) so the
  image is cached across sessions instead of rebuilding every time.

Full narrative (the PHP-PPA dead end tried first, the corrected
"no working daemon" claim, everything ruled out along the way) is in
`SETUP-LOG.md` — not repeated here on purpose.

## Other known quirks

- GitHub's API rate-limited anonymous dist downloads through an
  earlier dev sandbox's proxy during a `composer update` run (before
  the Docker approach existed) — composer fell back to cloning from
  git source successfully; may not recur elsewhere.
- `frontend/vite.config.ts` ships `vite-plugin-pwa` with an empty
  `icons: []` — no real app icons exist yet (see the TODO next to it).
  Add 192×192 and 512×512 PNGs before treating the PWA as installable.
- `api/tests/*` and `api/src/*` subdirectories are empty (`.gitkeep`
  only) — first real code should come from a spec, not ad-hoc.
