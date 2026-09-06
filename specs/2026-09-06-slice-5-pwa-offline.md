# Slice 5 — Real PWA app-shell offline

**Status:** locked. Grilled with the human (`grill-me` skill,
2026-09-06) against `specs/STATUS.md` § Next step. Self-contained —
restates what's needed from `frontend/vite.config.ts`'s existing
`vite-plugin-pwa` setup rather than pointing at it for the load-bearing
facts.

## Why this slice

`frontend/vite.config.ts` already ships `vite-plugin-pwa`
(`registerType: "autoUpdate"`, `generateSW` mode — confirmed working
in every `script/qa` build log, "5 entries precached") but with
`manifest.icons: []`. Without real icon files the app isn't
installable — a browser's install-prompt criteria require valid
manifest icons. Slices 2–4 deliberately deferred this ("explicitly out
of scope," Slice 3's spec). Data-level offline (cached questions, a
localStorage queue for ratings/app-feedback) already works from Slice
3/4 — this slice is specifically about the **app shell itself**: can
the app's own HTML/CSS/JS load with no network at all, and can it be
installed to a home screen.

**Second reason this slice now, not later:** a real deployment is
happening imminently (the human's own words, not an invented ops
fact — no hostname/target is assumed here). During the run-up to that,
the PWA needs to reliably pick up a new deployed version every time
it's opened, not rely on the browser's own throttled service-worker
update check (which can go up to ~24h between checks) — a stale
cached version during active iteration would be actively confusing.

## Scope

**In scope:**
- Real app icons: a simple generated placeholder (not final branding),
  192×192 and 512×512 PNG, `purpose: "any"` only.
- `apple-touch-icon` (180×180) + `apple-mobile-web-app-capable` meta
  tags — Safari/iOS ignores the standard web manifest for "Add to
  Home Screen," these are the separate mechanism it actually reads.
- An active update check on every app open/return — not just the
  browser's own default-throttled service-worker check.
- Live verification: the app shell must still render after a real
  browser goes offline and reloads.

**Explicitly out of scope:**
- **Real branding icon** — the placeholder is a generated heart icon
  (`💕` on a solid background), not final design work. A later,
  separate design task.
- **Maskable icon variant** (`purpose: "any maskable"`, Android
  adaptive icons) — extra design work (safe-zone padding) not worth
  doing on a placeholder that will be replaced anyway.
- **Visible update-reload prompt** — `registerType: "autoUpdate"`
  stays: a new version activates silently, no "new version available"
  banner. Two people sharing one device/browser don't benefit from an
  extra confirmation step for this.
- **Push notifications, Background Sync API** — no use case; the app
  already has its own online/offline queue logic (Slice 3/4), which
  covers what Background Sync would otherwise be for.
- **Any deployment-specific config** (real domain, HTTPS certs,
  hosting) — genuinely not decided yet; this slice only touches
  `frontend/`'s own build output, deployment-agnostic.

## Icons

Generated via a one-off Playwright screenshot (not a repo script — a
placeholder doesn't need repeatable tooling): an HTML page with a
`💕` emoji centered on a solid `#d6336c` background, screenshotted at
192×192, 512×512 (→ `frontend/public/icon-192.png`,
`frontend/public/icon-512.png`) and 180×180
(→ `frontend/public/apple-touch-icon.png`). `manifest.theme_color`
and `background_color` updated to the same `#d6336c` for a coherent
splash screen instead of the current mismatched white.

**Reasoning — why not a design tool/library dependency:** a single
placeholder image doesn't justify a new dependency (`sharp`, ImageMagick,
etc. aren't even installed in this environment) — Playwright (already a
project dependency for live smoke tests) rendering a static HTML page
to a screenshot is the smallest thing that works, and won't be reused
once real branding replaces this.

## Update check: active, not just browser-default

`vite-plugin-pwa`'s Vue integration (`virtual:pwa-register/vue`'s
`useRegisterSW()`) wires a `document.visibilitychange` listener via
`onRegisteredSW`: every time the tab becomes visible again (app
opened, switched back to), it calls `registration.update()` to force
an immediate check against the deployed service worker script —
instead of relying on the browser's own update algorithm, which only
checks on navigation and caps how often (up to ~24h). Combined with
`registerType: "autoUpdate"` (already configured, unchanged): a found
update activates immediately and silently, no user action needed.

**Reasoning:** the human's own framing — "gerade in der Testphase ist
wichtig, dass die PWA standardmäßig nach neuem Stand guckt bei jeder
Nutzung" (during the test phase, the PWA must check for a new version
by default on every use). A once-a-navigation, browser-throttled check
isn't "every use." This is a deliberate, temporary-leaning emphasis
for the active test/deployment phase, not a permanent product
decision to revisit later if it turns out to be excessive.

## Testing approach

**Not testable against `script/dev-frontend`** (`vite` dev server) —
`vite-plugin-pwa` only precaches real built assets in `generateSW`
mode; dev mode doesn't produce the same service worker. Verification
needs the actual production build, served via `npm run preview`
(`vite preview`, already an existing `package.json` script — no new
one needed).

**Live Playwright check** (same discipline as Slice 3/4 — CORS and MCP
route bugs were only ever caught by a real browser, never by
`script/qa`'s automated suites), via `script/lib/playwright-launch.mjs`:
1. Build (`npm run build`) and serve (`npm run preview`).
2. Load the page, wait for the service worker to be **ready and its
   precache actually populated** — poll `caches.keys()` /
   `cache.keys()` for an `index.html` entry, not `reg.active`/
   `navigator.serviceWorker.controller`. Found empirically that the
   registration reports an active, controlling worker *before*
   Workbox's precache-install step finishes writing every entry —
   `FRICTION.md`, "A service worker's `active`/`controller` state
   doesn't mean Workbox's precache install has finished."
3. `context.setOffline(true)`, reload the page.
4. Assert the app shell still renders (the question text or the
   loading/end-state fallback — not a blank page or a browser offline
   error page).

## Done

- `manifest.icons` has real 192×192/512×512 entries; `apple-touch-icon`
  + `apple-mobile-web-app-capable` present in `index.html`.
- `useRegisterSW()` wired with a `visibilitychange`-triggered
  `registration.update()`.
- `script/qa` green (build succeeds, no new lint/type errors).
- Live Playwright verification (above) passes: app shell renders after
  going offline and reloading against the built+previewed app.

## Explicitly deferred (not decided here — see `specs/STATUS.md` § Open decisions)

- Real branding icon (replaces the placeholder).
- Maskable icon variant.
- Deployment target itself (hosting, domain, HTTPS) — the human
  provides this when it happens; not invented here per `CLAUDE.md`.
- Whether the "check on every use" emphasis should relax after the
  active test/deployment phase settles down.
