import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    allowedHosts: [
      'devmunney.home.munne.me',
      'localhost',
      '.home.munne.me'
    ],
    hmr: {
      clientPort: 443,
      protocol: 'wss'
    }
  }
})
