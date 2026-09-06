/// <reference types="vitest/config" />
import vue from "@vitejs/plugin-vue";
import { defineConfig } from "vite";
import { VitePWA } from "vite-plugin-pwa";

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    // Icons are a generated placeholder (a heart emoji on a solid
    // background), not final branding — specs/2026-09-06-slice-5-pwa-offline.md.
    VitePWA({
      registerType: "autoUpdate",
      manifest: {
        name: "paarfragen",
        short_name: "paarfragen",
        description: "Questions for couples",
        theme_color: "#d6336c",
        background_color: "#d6336c",
        icons: [
          {
            src: "/icon-192.png",
            sizes: "192x192",
            type: "image/png",
            purpose: "any",
          },
          {
            src: "/icon-512.png",
            sizes: "512x512",
            type: "image/png",
            purpose: "any",
          },
        ],
      },
    }),
  ],
  test: {
    environment: "jsdom",
    // No tests exist yet (see specs/STATUS.md) — don't fail the gate on that.
    passWithNoTests: true,
  },
});
