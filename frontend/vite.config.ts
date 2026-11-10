import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import vueDevTools from 'vite-plugin-vue-devtools'

// Laravel API during development (php artisan serve).
const API_TARGET = process.env.VITE_API_PROXY_TARGET ?? 'http://localhost:8000'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue(), tailwindcss(), vueDevTools()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    // Same-origin in development: the SPA calls /api and /sanctum on its own origin and Vite
    // forwards them, so session and XSRF cookies work without cross-site CORS.
    proxy: {
      '/api': { target: API_TARGET },
      '/sanctum': { target: API_TARGET },
    },
  },
})
