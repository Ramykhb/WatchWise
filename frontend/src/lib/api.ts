import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios'
import { useCallback, useEffect, useState } from 'react'
import { authApi } from './auth.ts'

const backendUrl = (import.meta.env.VITE_BACKEND_URL ?? 'http://localhost:8000').replace(/\/$/, '')

/** The Laravel API (`/api/...`). Auth cookies ride along; an expired access token is refreshed once. */
export const api = axios.create({
  baseURL: `${backendUrl}/api`,
  withCredentials: true,
  headers: { Accept: 'application/json' },
})

// The access cookie lives 15 minutes. On a 401, rotate the refresh token once and retry the request;
// parallel requests share the same refresh call.
let refreshing: Promise<unknown> | null = null
api.interceptors.response.use(undefined, async (error: AxiosError) => {
  const config = error.config as (InternalAxiosRequestConfig & { _retried?: boolean }) | undefined
  if (error.response?.status !== 401 || !config || config._retried) throw error
  config._retried = true
  refreshing ??= authApi.post('/refresh').finally(() => {
    refreshing = null
  })
  await refreshing
  return api(config)
})

// Responses by path, so a page opened before shows at once while it refreshes in the background,
// and requests for the same path that are already on their way are shared (React StrictMode
// mounts twice in development). Cleared on sign-out.
const cache = new Map<string, unknown>()
const inFlight = new Map<string, Promise<unknown>>()

export function clearApiCache() {
  cache.clear()
  inFlight.clear()
}

function fetchShared<T>(path: string): Promise<T> {
  let request = inFlight.get(path) as Promise<T> | undefined
  if (!request) {
    request = api
      .get<T>(path)
      .then((response) => {
        cache.set(path, response.data)
        return response.data
      })
      .finally(() => inFlight.delete(path))
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
export function useApi<T>(path: string) {
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
    let active = true
    fetchShared<T>(path)
      .then((data) => active && setState({ key, data, error: undefined }))
      .catch((error: unknown) => active && setState({ key, data: undefined, error }))
    return () => {
      active = false
    }
  }, [path, key])

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

  return { data: state.data, error: state.error, loading: state.key !== key, setData, reload }
}

/** True when the request failed with this HTTP status (e.g. 404 → "not found" page). */
export const isStatus = (error: unknown, status: number) => axios.isAxiosError(error) && error.response?.status === status
