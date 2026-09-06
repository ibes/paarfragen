import { useRegisterSW } from "virtual:pwa-register/vue";

/**
 * registerType: "autoUpdate" (vite.config.ts) already activates a
 * found update silently, no user prompt. What it doesn't do on its
 * own is *check* for one often — the browser's own service-worker
 * update algorithm only runs on navigation and caps how often
 * (up to ~24h), which isn't "every use" during an active test/
 * deployment phase (specs/2026-09-06-slice-5-pwa-offline.md).
 * Forcing `registration.update()` whenever the tab becomes visible
 * again covers "app opened/switched back to" directly.
 */
export function registerServiceWorker(): void {
  useRegisterSW({
    immediate: true,
    onRegisteredSW(_swUrl, registration) {
      if (!registration) {
        return;
      }

      document.addEventListener("visibilitychange", () => {
        if (document.visibilityState === "visible") {
          void registration.update();
        }
      });
    },
  });
}
