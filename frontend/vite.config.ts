import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// Additional allowed hosts from environment (comma-separated)
const extraHosts = process.env.VITE_ALLOWED_HOSTS?.split(',').filter(Boolean) || []

export default defineConfig({
  plugins: [react()],
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    allowedHosts: [
      'localhost',
      ...extraHosts
    ],
    hmr: {
      clientPort: 443,
      protocol: 'wss'
    }
  }
})
