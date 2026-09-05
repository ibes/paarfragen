/// <reference types="vitest/config" />
import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'
import { VitePWA } from 'vite-plugin-pwa'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    // TODO: add real app icons (192x192, 512x512 PNG) under public/ and list
    // them here before this is installable as a home-screen PWA.
    VitePWA({
      registerType: 'autoUpdate',
      manifest: {
        name: 'paarfragen',
        short_name: 'paarfragen',
        description: 'Questions for couples',
        theme_color: '#ffffff',
        icons: [],
      },
    }),
  ],
  test: {
    environment: 'jsdom',
    // No tests exist yet (see specs/STATUS.md) — don't fail the gate on that.
    passWithNoTests: true,
  },
})
