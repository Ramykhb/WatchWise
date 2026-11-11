/** The backend origin without a trailing slash (`VITE_BACKEND_URL`, defaulting to the local dev server). */
export const backendUrl = (import.meta.env?.VITE_BACKEND_URL ?? 'http://localhost:8000').replace(/\/+$/, '')

/** Where the browser is sent to start signing in with a social provider (a full-page redirect, not an XHR). */
export const socialAuthUrl = (provider: string, base: string = backendUrl) => `${base.replace(/\/+$/, '')}/api/auth/social/${encodeURIComponent(provider)}`
