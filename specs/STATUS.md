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

- **PHP 8.5 required, this dev sandbox only has 8.4.19.** `api/composer.lock`
  was generated with `--ignore-platform-req=php` purely to get a correct,
  resolvable lock file; it was never installed/run end-to-end here.
  `script/setup` and `script/qa` will correctly refuse in any PHP 8.4
  environment (composer's own platform-requirement error) — that's
  expected, not a bug. Needs a real PHP 8.5 machine or CI to validate.
- GitHub's API rate-limited anonymous dist downloads through this
  sandbox's proxy during that same composer run (unrelated to the PHP
  version issue) — composer fell back to cloning from git source
  successfully; a different sandbox/network may not hit this at all.
- `frontend/vite.config.ts` ships `vite-plugin-pwa` with an empty
  `icons: []` — no real app icons exist yet (see the TODO next to it).
  Add 192×192 and 512×512 PNGs before treating the PWA as installable.
- `api/tests/*` and `api/src/*` subdirectories are empty (`.gitkeep`
  only) — first real code should come from a spec, not ad-hoc.
