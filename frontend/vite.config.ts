import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

const hmrClientPort = Number(process.env.VITE_HMR_CLIENT_PORT) || undefined

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    // Behind the edge nginx the browser reaches HMR through APP_PORT.
    hmr: hmrClientPort ? { clientPort: hmrClientPort } : undefined,
  },
})
