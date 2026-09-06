// Bootstraps a Chromium instance for ad-hoc, throwaway live-browser
// smoke checks (a scratchpad .mjs script, not part of the repo's own
// test suite — see api/README.md/frontend's own automated tests for
// those). Import this instead of hand-rolling `chromium.launch()`
// again: this sandbox's global Playwright install isn't resolvable
// via plain `import { chromium } from "playwright"` (Node's ESM
// resolver ignores NODE_PATH), and its Chromium binary lives under a
// version-suffixed directory that isn't guessable ahead of time.
// FRICTION.md, "Ad-hoc Playwright smoke script needed manual
// path-hunting" (now resolved by this file).
//
// Usage from a scratchpad script:
//   import { launchChromium } from "/home/user/paarfragen/script/lib/playwright-launch.mjs";
//   const browser = await launchChromium();
//   const page = await browser.newPage();
//   // waitUntil: "load", not the default "networkidle" — Vite's dev
//   // server keeps an HMR WebSocket open, so a page against it never
//   // actually goes network-idle and the navigation just hangs.
//   await page.goto("http://127.0.0.1:5173/", { waitUntil: "load" });
//   ...
//   await browser.close();

import { readdirSync } from "node:fs";
import { join } from "node:path";
import { chromium } from "/opt/node22/lib/node_modules/playwright/index.mjs";

const BROWSERS_DIR = "/opt/pw-browsers";

function resolveChromiumExecutable() {
  const versionedDir = readdirSync(BROWSERS_DIR).find((name) =>
    /^chromium-\d+$/.test(name),
  );
  if (!versionedDir) {
    throw new Error(
      `No versioned chromium-* directory found under ${BROWSERS_DIR} — ` +
        "the environment's Playwright browser layout may have changed; " +
        "run `find /opt/pw-browsers -iname '*chrome*'` and update this file.",
    );
  }
  return join(BROWSERS_DIR, versionedDir, "chrome-linux", "chrome");
}

/**
 * @param {import("playwright").LaunchOptions} [options] Merged over the
 *   defaults below; pass e.g. `{ headless: false }` to override one.
 */
export async function launchChromium(options = {}) {
  return chromium.launch({
    executablePath: resolveChromiumExecutable(),
    args: ["--no-sandbox"],
    ...options,
  });
}
