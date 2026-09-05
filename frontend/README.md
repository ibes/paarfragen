# paarfragen — Frontend

Vue 3 + TypeScript + Vite, `vite-plugin-pwa` wired for installable-PWA
output (`npm run build` emits a manifest + service worker). No UI beyond
a placeholder yet — see `../specs/STATUS.md`.

Talks to `../api` over HTTP; no server-side rendering, no coupling to the
API's runtime. That split is deliberate: the plan is to wrap this same
frontend in a native container (e.g. Capacitor) later without touching
the API.

## Toolchain

Via `../script/*`, not npm directly — see `../script/help`.

## Open items

- Real app icons (192×192, 512×512 PNG) — see TODO in `vite.config.ts`.
