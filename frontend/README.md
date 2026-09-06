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

## House rules

[`reference/vue.md`](reference/vue.md) — Vue/Vite/tooling-level
gotchas (ESLint/`vue-tsc` interop, Playwright quirks, PWA/service-worker
testing). Framework-level, not this repo's own conventions.

## Open items

- Real branding icon (the current one is a generated placeholder —
  `frontend/public/icon-*.png`, `../specs/2026-09-06-slice-5-pwa-offline.md`).
