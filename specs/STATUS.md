# STATUS

Session router — read this first, every session.

## Phase

**Greenfield setup.** Repo skeleton exists (`api/` hexagonal PHP,
`frontend/` Vue/Vite PWA), no product code yet, no spec written.

## Next step

No active spec. Before writing any Domain/Application/Infrastructure
code, write a spec for the first slice (e.g. "ask a couple a question,
both answer, see if you matched") — talk to the human about what that
first slice should be, don't assume.

## Open decisions (not yet made — ask before assuming)

- **API web framework.** `api/composer.json` requires only `php` +
  `phpunit` so far. Tempest (used in the `emsig` sibling repo) is one
  candidate but needs PHP ^8.5; this environment only has PHP 8.4.19 —
  confirm the target runtime before picking a framework that requires 8.5.
- **Auth model** for two people answering the same question set
  together (invite link? shared code? accounts?).
- **Data storage** — nothing wired yet.

## Known quirks

- `frontend/vite.config.ts` ships `vite-plugin-pwa` with an empty
  `icons: []` — no real app icons exist yet (see the TODO next to it).
  Add 192×192 and 512×512 PNGs before treating the PWA as installable.
- `api/tests/*` and `api/src/*` subdirectories are empty (`.gitkeep`
  only) — first real code should come from a spec, not ad-hoc.
