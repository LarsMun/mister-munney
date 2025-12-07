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
  },
  build: {
    rollupOptions: {
      output: {
        manualChunks: {
          // Vendor chunks - split large dependencies for better caching
          'vendor-react': ['react', 'react-dom', 'react-router-dom'],
          'vendor-ui': ['lucide-react', 'react-hot-toast'],
          'vendor-charts': ['recharts'],
          'vendor-utils': ['axios', 'zod', 'date-fns']
        }
      }
    },
    // Generate source maps for production debugging
    sourcemap: true,
    // Chunk size warning limit (in kB)
    chunkSizeWarningLimit: 500
  }
})
