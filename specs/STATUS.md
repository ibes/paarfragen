# STATUS

Session router — read this first, every session. History and full
reasoning behind any decision below live in `SETUP-LOG.md`; this file
is the current state, kept short on purpose.

## Right now

**One active blocker, waiting on a human action outside this repo:**
`api/`'s Docker image (needed for PHP 8.5 / Tempest) can't build in
this dev sandbox — every `docker pull` from Docker Hub 403s on
`production.cloudfront.docker.com`, one of Docker Hub's CDN hosts that
isn't on this environment's network allowlist (confirmed via the proxy's
own failure log, not guessed). Fix: a human adds that domain to the
environment's Network access settings (Custom level), then starts a
**new session** — network policy is fixed at session start, so this
session can't pick up the change itself.

**First thing to do in that new session:** run `script/test-api` (or
`docker compose build api` directly) and confirm it now actually
builds and runs — don't assume it does. If it still fails on the same
domain, the settings change didn't take (wrong domain, not saved, not
yet propagated). If it fails differently, see "Docker for `api/`"
below for what to check first (extension names, `trixie` package
naming).

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

Not yet build-tested end-to-end anywhere in this repo's history.
What's confirmed:

- The Docker **daemon itself works** in this dev sandbox (`sudo dockerd`
  directly, not `sudo service docker start` — that init script hits a
  `ulimit` call this sandbox disallows).
- **Blocked on:** `docker pull`/`docker compose build` for *any* Docker
  Hub image 403s on `production.cloudfront.docker.com` — see "Right
  now" above for the fix and what only a human can do.
- `docker-compose.yml` parses correctly (`docker compose config`); the
  Dockerfile's package/extension list is grounded in real sources
  (Tempest's actual `composer.json` requirements, Docker Hub's real
  `php:8.5-cli-trixie` tag) — not guessed, but also not yet proven by
  an actual successful build.
- If the build fails once the CDN domain is unblocked: likely places
  to check first are an extension name mismatch, or `trixie` (Debian
  13) having renamed one of the `-dev` packages installed before
  `docker-php-ext-install`.
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
