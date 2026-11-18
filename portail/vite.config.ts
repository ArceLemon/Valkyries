import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    // En développement, les appels /api sont relayés vers le backend Laravel.
    proxy: {
      '/api': 'http://127.0.0.1:8000',
    },
  },
})
