# STATUS

Session router — read this first, every session.

## Phase

**Greenfield setup.** Repo skeleton exists (`api/` hexagonal PHP on
Tempest, `frontend/` Vue/Vite PWA), no product code yet, no spec written.

`VISION/`-style pre-spec input exists upstream in the `redlich` sibling
repo: [`paarfrage-exploration-mode.md`](https://github.com/ibes/redlich/blob/main/VISION/paarfrage-exploration-mode.md)
describes the intended first slice (a single shared-device question deck,
rating loop, `deck_id` bearer identity, no accounts). It's a design input,
not a spec — restate what's needed from it into a real spec here before
building against it (same rule redlich's own `VISION/README.md` states:
specs restate, never cite by path). It does not mention any tech stack;
the stack decisions below come from the human directly, not from that doc.

## Next step

[`specs/api.md`](api.md) drafts the request/response contract for the
first slice's five endpoints, so `api/` and `frontend/` can be built
against a shared interface without waiting on each other. It's a draft,
not a locked spec — still update it as either side hits a gap.

Still no locked slice spec. Before writing any Domain/Application/
Infrastructure *implementation* (as opposed to the contract doc), write
one using the `spec` skill — talk to the human about scope, don't
assume the vision doc is ready to build as-is.

## Decided

- **API framework: Tempest** (`^3.0`), used as a pure JSON API — no
  server-rendered views, no `vite-plugin-tempest`. See `api/README.md`.
- **Frontend/API stay decoupled** (`api/` and `frontend/` as sibling
  top-level dirs, independent toolchains) — deliberate, so the frontend
  can later be wrapped in a native shell (e.g. Capacitor) without
  touching the API's deploy lifecycle. See `SETUP-LOG.md`.
- **API contract drafted ahead of implementation** — `specs/api.md` —
  so both sides can build against it in parallel once a slice spec
  exists.
- **`api/` (PHP 8.5) runs in a Docker container, not on the host** —
  `docker-compose.yml` + `.devcontainer/Dockerfile`, invoked via
  `script/lib/api-php`. Chosen over changing this environment's network
  access level: sidesteps the PHP-PPA problem below entirely (Docker
  Hub pulls are allowed under the default network access level; a
  third-party apt PPA is not) and gives local devs the same container
  as a VS Code Dev Container. See `SETUP-LOG.md` for the fuller
  reasoning and what's still unverified.

## Open decisions (not yet made — ask before assuming)

- **Where `generate:typescript-types` (Tempest → TS type generation,
  see `specs/api.md`) writes its output.** Deferred until real
  Infrastructure DTOs exist to generate from.
- **Auth model** for two people answering the same question set
  together. The vision doc proposes a hardcoded, opaque `deck_id` bearer
  token (no login) for its exploration stage — not yet confirmed for
  this repo.
- **Data storage** — nothing wired yet. Vision doc sketches
  `questions` / `question_feedback` / `app_feedback` tables; needs a
  spec before becoming schema.
- **Deploy target's actual PHP version** — assumed to be PHP ^8.5
  somewhere real (matching `emsig`'s toolchain), but not confirmed.

## Known quirks

- **`api/`'s Docker image was never actually built in this dev sandbox
  — no working Docker daemon here** (`docker info` fails; starting it
  hit a `ulimit`/permission error consistent with this specific sandbox
  not allowing nested containers). `docker-compose.yml` config is
  validated (`docker compose config` parses correctly) and the
  Dockerfile's package/extension choices are grounded in real sources
  (Tempest's own `composer.json` for which PHP extensions it needs,
  Docker Hub's actual tag list for `php:8.5-cli-trixie` — not guessed),
  but the actual `docker build` / `docker compose run` has **not**
  succeeded anywhere in this repo's history yet. First real session
  with a working Docker daemon should treat that as the thing to
  verify, not assume it's already proven. If it fails, likely places to
  look: an extension name mismatch, or `trixie` (Debian 13) having a
  renamed package for one of the `-dev` libs installed before
  `docker-php-ext-install`.
- Getting there needed two earlier approaches, both dead ends worth not
  repeating: (1) apt-get installing `php8.5-cli` from the `ondrej/php`
  PPA directly onto the session VM — blocked (403) by this
  environment's network access level, which only allows common package
  registries, not arbitrary third-party PPAs; (2) assuming a `docker
  build`'s network access differs from the live session's — it
  doesn't, same access level applies to both (see [cloud environments
  docs](https://code.claude.com/docs/en/cloud-environments)); Docker
  Hub itself being in the default allowlist is what actually makes the
  container approach work, not some build-time exception.
- **Not yet done, and not something I can do from inside a session:**
  add `docker compose build api` to this environment's **Setup
  script** field (claude.ai/code → environment settings — a UI/account
  setting, not a repo file) so the image gets cached in the
  environment's ~7-day filesystem snapshot instead of rebuilding (from
  Docker's layer cache, so not from scratch, but still redone) on every
  `.claude/hooks/session-start.sh` run. Until someone does that by hand,
  every fresh session pays the build cost once.
- GitHub's API rate-limited anonymous dist downloads through this
  sandbox's proxy during an earlier `composer update` run (before the
  Docker approach existed) — composer fell back to cloning from git
  source successfully; a different sandbox/network may not hit this at
  all.
- `frontend/vite.config.ts` ships `vite-plugin-pwa` with an empty
  `icons: []` — no real app icons exist yet (see the TODO next to it).
  Add 192×192 and 512×512 PNGs before treating the PWA as installable.
- `api/tests/*` and `api/src/*` subdirectories are empty (`.gitkeep`
  only) — first real code should come from a spec, not ad-hoc.
