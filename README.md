# paarfragen

A PWA of questions for couples. Planned: first as a Progressive Web App,
later optionally as a native cross-platform app (same frontend, wrapped
in a native container).

## Layout

```
api/         PHP backend on Tempest, hexagonal (Domain / Application / Infrastructure)
frontend/    Vue 3 + Vite, built as a PWA (vite-plugin-pwa)
specs/       Specs; STATUS.md is the entry point for every session
script/      All toolchain commands — see script/help
```

No feature code yet — pure skeleton. Next step and open decisions:
[`specs/STATUS.md`](specs/STATUS.md).

## Getting started

```bash
script/setup   # Install dependencies (api/ + frontend/)
script/qa      # Tests + typecheck + build — the gate before every commit
script/help    # All commands with description + side-effects
```

`api/` needs PHP **^8.5** (Tempest) — runs in a container for that
(`docker-compose.yml` / `.devcontainer/`), not on the host. So you need
**Docker**, not PHP 8.5 installed locally. The same image is also the
local VS Code Dev Container for the whole repo. Details:
`api/README.md`, `specs/STATUS.md`.

Agent working rules: [`CLAUDE.md`](CLAUDE.md).
