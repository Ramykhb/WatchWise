import react from '@vitejs/plugin-react'
import { defineConfig, loadEnv, type ProxyOptions } from 'vite'

export default defineConfig(({ command, mode, isPreview }) => {
  const env = loadEnv(mode, process.cwd(), 'VITE_')
  const useLocalProxy = command === 'serve' && !isPreview
  const backendUrl = env.VITE_BACKEND_URL || 'http://localhost:8000'

  const apiProxy: ProxyOptions = {
    target: backendUrl,
    changeOrigin: true,
    cookieDomainRewrite: '',
    configure(proxy) {
      proxy.on('proxyRes', (response, request) => {
        const host = request.headers.host ?? ''
        const loopback = /^(localhost|127\.0\.0\.1|\[::1\])(?::\d+)?$/i.test(host)

        // Render sends Secure cookies. HTTP localhost development needs local
        // cookies, while the connection from Vite to Render still uses HTTPS.
        if (loopback && response.headers['set-cookie']) {
          response.headers['set-cookie'] = response.headers['set-cookie'].map(
            (cookie) => cookie.replace(/;\s*Secure\b/gi, ''),
          )
        }
      })
    },
  }

  return {
    plugins: [react()],
    // The browser calls /api on localhost; only Vite connects to the backend.
    // Production builds keep their configured VITE_BACKEND_URL.
    define: useLocalProxy
      ? { 'import.meta.env.VITE_BACKEND_URL': JSON.stringify('') }
      : undefined,
    server: {
      port: 5173,
      strictPort: true,
      proxy: { '/api': apiProxy },
    },
  }
})
