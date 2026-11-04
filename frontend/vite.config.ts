import { fileURLToPath, URL } from 'node:url'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vitest/config'

const hmrClientPort = Number(process.env.VITE_HMR_CLIENT_PORT) || undefined

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    // Behind the edge nginx the browser reaches HMR through APP_PORT.
    hmr: hmrClientPort ? { clientPort: hmrClientPort } : undefined,
  },
  build: {
    sourcemap: false,
    chunkSizeWarningLimit: 1200,
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/test/setup.ts'],
    css: false,
    restoreMocks: true,
    // Mantine + user-event interaction tests are slow on a loaded container.
    testTimeout: 15000,
  },
})
