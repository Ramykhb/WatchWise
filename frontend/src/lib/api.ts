import axios, { type AxiosError, type AxiosResponse, type InternalAxiosRequestConfig } from 'axios'
import { useCallback, useEffect, useState } from 'react'

const backendUrl = (import.meta.env.VITE_BACKEND_URL ?? 'http://localhost:8000').replace(/\/$/, '')

/** The shared Laravel API client (`/api/...`) for both application and auth requests. */
export const api = axios.create({
  baseURL: `${backendUrl}/api`,
  withCredentials: true,
  headers: { Accept: 'application/json' },
})

type RetryableRequestConfig = InternalAxiosRequestConfig & { _retried?: boolean }

const publicAuthEndpoints = [
  '/auth/login',
  '/auth/register',
  '/auth/refresh',
  '/auth/logout',
  '/auth/forgot-password',
  '/auth/reset-password',
  '/auth/resend-verification',
]
const guestRoutes = ['/', '/signin', '/signup', '/forgot-password', '/auth/callback']
export const SESSION_EXPIRED_EVENT = 'watchwise:session-expired'
export const SESSION_REQUEST_TIMEOUT = 10_000

function shouldSkipRefresh(url?: string): boolean {
  if (!url) return false
  const path = url.startsWith('http') ? new URL(url).pathname : url.split('?')[0]
  return publicAuthEndpoints.some((endpoint) => path.endsWith(endpoint))
}

let refreshing = false
let clearingSession: Promise<void> | null = null
let failedRequests: Array<{
  config: RetryableRequestConfig
  resolve: (response: AxiosResponse) => void
  reject: (reason: unknown) => void
}> = []

function settleFailedRequests(error?: unknown) {
  failedRequests.forEach(({ config, resolve, reject }) => {
    if (error) reject(error)
    else void api(config).then(resolve, reject)
  })
  failedRequests = []
}

function clearSessionAndRedirect(): Promise<void> {
  if (clearingSession) return clearingSession

  // Auth cookies are HttpOnly; only the backend can revoke and expire them.
  clearingSession = api.post('/auth/logout', undefined, { timeout: SESSION_REQUEST_TIMEOUT })
    .catch(() => undefined)
    .then(() => {
      window.dispatchEvent(new Event(SESSION_EXPIRED_EVENT))
      if (!guestRoutes.includes(window.location.pathname)) window.location.replace('/')
    })
    .finally(() => {
      clearingSession = null
    })

  return clearingSession
}

api.interceptors.response.use(undefined, async (error: AxiosError) => {
  const config = error.config as RetryableRequestConfig | undefined
  const status = error.response?.status

  if (!config || config.signal?.aborted || ![401, 403].includes(status ?? 0) || shouldSkipRefresh(config.url)) {
    return Promise.reject(error)
  }

  if (config._retried) {
    if (status === 401) await clearSessionAndRedirect()
    return Promise.reject(error)
  }

  config._retried = true

  if (refreshing) {
    return new Promise<AxiosResponse>((resolve, reject) => {
      failedRequests.push({ config, resolve, reject })
    })
  }

  refreshing = true

  try {
    await api.post('/auth/refresh', undefined, { timeout: SESSION_REQUEST_TIMEOUT })
  } catch (refreshError) {
    refreshing = false
    settleFailedRequests(refreshError)
    // A slow or unavailable server does not mean the refresh token is invalid.
    if (axios.isAxiosError(refreshError) && [401, 403].includes(refreshError.response?.status ?? 0)) {
      await clearSessionAndRedirect()
    }
    return Promise.reject(refreshError)
  }

  refreshing = false
  settleFailedRequests()
  return api(config)
})

// Responses by path, so a page opened before shows at once while it refreshes in the background,
// and requests for the same path that are already on their way are shared (React StrictMode
// mounts twice in development). Cleared on sign-out.
const cache = new Map<string, unknown>()
const inFlight = new Map<string, Promise<unknown>>()

// Bumped on every account change, so a response for the previous account is not cached.
let generation = 0

export function clearApiCache() {
  generation++
  cache.clear()
  inFlight.clear()
}

function fetchShared<T>(path: string): Promise<T> {
  let request = inFlight.get(path) as Promise<T> | undefined
  if (!request) {
    const started = generation
    const shared: Promise<T> = api
      .get<T>(path)
      .then((response) => {
        if (started === generation) cache.set(path, response.data)
        return response.data
      })
      .finally(() => {
        // Only remove our own entry; after an account change the path may belong to a new request.
        if (inFlight.get(path) === shared) inFlight.delete(path)
      })
    request = shared
    inFlight.set(path, request)
  }
  return request
}

/** `key` says which request the data belongs to, so loading is derived instead of stored. */
type ApiState<T> = { key: string; data: T | undefined; error: unknown }

/**
 * Loads `path` from the API and keeps it in state. Cached data for the path shows immediately
 * and is refreshed. `setData` updates the value after a mutation; `reload` fetches again (the
 * previous data stays on screen meanwhile).
 */
/** `enabled: false` sends nothing (e.g. a signed-out visitor, whose 401 would end the session). */
export function useApi<T>(path: string, { enabled = true }: { enabled?: boolean } = {}) {
  const [version, setVersion] = useState(0)
  const key = `${path}#${version}`
  const [state, setState] = useState<ApiState<T>>(() => ({ key: '', data: cache.get(path) as T | undefined, error: undefined }))
  // Data from another path (e.g. moving between profiles) is never shown for this one.
  const [shownPath, setShownPath] = useState(path)
  if (shownPath !== path) {
    setShownPath(path)
    setState({ key: '', data: cache.get(path) as T | undefined, error: undefined })
  }

  useEffect(() => {
    if (!enabled) return
    let active = true
    fetchShared<T>(path)
      .then((data) => active && setState({ key, data, error: undefined }))
      .catch((error: unknown) => active && setState({ key, data: undefined, error }))
    return () => {
      active = false
    }
  }, [path, key, enabled])

  const setData = useCallback(
    (update: T | ((current: T) => T)) => {
      setState((current) => {
        if (current.data === undefined) return current
        const data = typeof update === 'function' ? (update as (value: T) => T)(current.data) : update
        cache.set(path, data)
        return { ...current, data }
      })
    },
    [path],
  )
  const reload = useCallback(() => {
    cache.delete(path)
    setVersion((v) => v + 1)
  }, [path])

  return { data: state.data, error: state.error, loading: enabled && state.key !== key, setData, reload }
}

/** True when the request failed with this HTTP status (e.g. 404 → "not found" page). */
export const isStatus = (error: unknown, status: number) => axios.isAxiosError(error) && error.response?.status === status
