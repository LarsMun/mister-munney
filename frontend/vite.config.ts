import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  server: {
    host: '0.0.0.0',   // luister op alle interfaces in de container
    port: 5173,
    strictPort: true,
    // HMR via de HOST-poort waarop je mapped (zie compose: 3000:5173)
    hmr: {
      host: 'localhost',
      clientPort: 5173,
    },
    proxy: {
      // Optie A: backend via host-poort (Docker Desktop/WSL)
      '/api': {
        target: 'http://host.docker.internal:8686',
        changeOrigin: true,
        secure: false,
      },

      // --- Optie B: als je via de compose-servicenaam naar Nginx wilt ---
      // '/api': {
      //   target: 'http://nginx:80', // vervang 'nginx' door de servicenaam uit compose
      //   changeOrigin: true,
      // },
    },
  },
})
