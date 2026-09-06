# Vue / Vite / tooling — house rules

Frontend framework/tooling behavior worth knowing before writing code
or a live-browser check — not this repo's own conventions (see
`frontend/README.md` for those) and not a copy of Vue/Vite's own docs.
Mirrors `api/reference/tempest.md`'s role for `api/`.

## Framework/tooling gotchas (not obvious from a quick skim)

- **ESLint's `no-undef` doesn't know about tsconfig's DOM lib.** Plain
  (non-type-checked) ESLint tracks globals syntactically — it has no
  idea what `tsconfig.app.json`'s `@vue/tsconfig/tsconfig.dom.json`
  declares available, so it flags `window`, `document`, etc. as
  undefined even though `vue-tsc` (`script/check-frontend-types`, the
  real type-authority here) type-checks them correctly.
  `typescript-eslint`'s own docs recommend disabling `no-undef` for
  exactly this reason. Already off in `frontend/eslint.config.js` —
  see that file's own comment.
- **Type-checked ESLint (`tseslint.configs.recommendedTypeChecked`)
  doesn't resolve `.vue` SFC types the way `vue-tsc` does.** Tried
  first, dropped: `main.ts`'s `createApp(App)` came back "unsafe
  argument of type error" on the very first file — a tool-interop
  problem (plain `typescript-eslint` can't see through a `.vue` file
  the way `vue-tsc`'s language-service plugin can), not a real bug.
  `frontend/eslint.config.js` stays syntactic-only lint; `vue-tsc`
  already gives full type-safety on real code, so there's no gap this
  would close that's worth fighting the interop for.
- **A throwaway Playwright script needs `script/lib/
playwright-launch.mjs`, not a hand-rolled `chromium.launch()`.**
  This sandbox's global Playwright install isn't resolvable via plain
  `import { chromium } from "playwright"` (Node's ESM resolver ignores
  `NODE_PATH`), and its Chromium binary lives under a version-suffixed
  directory (`chromium-1194` at time of writing) that isn't guessable
  ahead of time. `launchChromium()` in that file handles both
  (resolving the version directory dynamically) plus `--no-sandbox`.
  See that file's own header comment for usage.
- **`page.goto(url, { waitUntil: "networkidle" })` hangs against the
  Vite dev server.** Vite's HMR client keeps a persistent WebSocket
  open, so a page against `script/dev-frontend` never actually goes
  network-idle — Playwright waits out its own 30s navigation timeout.
  Use `waitUntil: "load"` instead for any live check against the dev
  server.
- **A service worker's `active`/`navigator.serviceWorker.controller`
  state doesn't mean Workbox's precache has actually finished.**
  Verifying PWA offline behavior (`vite-plugin-pwa`, `generateSW`
  mode): the registration can report an active, controlling worker
  _before_ the precache-population step (writing every manifest entry
  into Cache Storage during `install`) has finished writing all of
  them. A `context.setOffline(true)` + reload right after that still
  fails with `net::ERR_INTERNET_DISCONNECTED`, and it looks exactly
  like a broken `vite-plugin-pwa` config even when it isn't. Wait on
  the actual precache instead: poll `caches.keys()` /
  `cache.keys()` until an `index.html` entry shows up, not
  `reg.active`/`.controller`.
  - **Also:** the PWA service worker only precaches real built
    assets — `generateSW` mode doesn't produce the same thing against
    `script/dev-frontend` (`vite` dev server). Test against a real
    build: `npm run build` then `npm run preview` (`vite preview`),
    not the dev server.

## When you need real API detail

Vue/Vite/`vite-plugin-pwa`/Playwright's own docs aren't vendored
in-repo the way Tempest's are (`api/vendor/tempest/framework/docs/`) —
check `frontend/node_modules/<package>` for shipped `.d.ts`/docs
before guessing at an API shape, the same "read the source, don't
assume" discipline used for Tempest's `tempest/mcp` route-decorator
gotcha in `api/reference/tempest.md`.
