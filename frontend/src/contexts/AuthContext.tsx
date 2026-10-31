import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { authApi, type AuthUser } from '../lib/auth.ts'
import { clearApiCache, isStatus, SESSION_EXPIRED_EVENT, SESSION_REQUEST_TIMEOUT } from '../lib/api.ts'
import { clearMyLists } from '../pages/Lists/myListsStore.ts'
import { clearNights } from '../pages/MovieNights/nightsStore.ts'
import { clearNotifications } from '../pages/Notifications/notificationsStore.ts'
import { forgetSavedRooms } from '../pages/Rooms/roomHistory.ts'
import { AuthContext } from './useAuth.ts'

/** Everything kept in memory for one account; dropped whenever the signed-in account changes. */
function resetUserData() {
  clearApiCache()
  clearNotifications()
  clearMyLists()
  clearNights()
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUserState] = useState<AuthUser | null>(null)
  const [loading, setLoading] = useState(true)
  const [sessionError, setSessionError] = useState<string | null>(null)
  const [sessionAttempt, setSessionAttempt] = useState(0)
  const userId = useRef<number | null>(null)

  // Signing in as someone else, or out, never shows the previous account's lists, nights or cache.
  const setUser = useCallback((next: AuthUser | null) => {
    const previous = userId.current
    if ((next?.id ?? null) !== previous) {
      userId.current = next?.id ?? null
      resetUserData()
    }
    setUserState(next)
    setSessionError(null)
  }, [])

  const retrySession = useCallback(() => {
    setSessionError(null)
    setLoading(true)
    setSessionAttempt((attempt) => attempt + 1)
  }, [])

  // Room conversations are no longer saved in the browser; drop any an earlier version left behind.
  useEffect(() => forgetSavedRooms(), [])

  useEffect(() => {
    let active = true
    const controller = new AbortController()
    const clearExpiredSession = () => setUser(null)
    window.addEventListener(SESSION_EXPIRED_EVENT, clearExpiredSession)
    const loadSession = async () => {
      try {
        const response = await authApi.get<{ user: AuthUser }>('/auth/me', {
          signal: controller.signal,
          timeout: SESSION_REQUEST_TIMEOUT,
        })
        if (active) setUser(response.data.user)
      } catch (error) {
        if (!active) return
        if (isStatus(error, 401) || isStatus(error, 403)) setUser(null)
        else setSessionError("We couldn't reach WatchWise. Please try again.")
      } finally {
        if (active) setLoading(false)
      }
    }
    void loadSession()
    return () => {
      active = false
      controller.abort()
      window.removeEventListener(SESSION_EXPIRED_EVENT, clearExpiredSession)
    }
  }, [setUser, sessionAttempt])

  const logout = useCallback(async () => {
    try {
      await authApi.post('/auth/logout')
    } finally {
      setUser(null)
    }
  }, [setUser])
  const firstName = useMemo(() => user?.name.trim().split(/\s+/)[0] ?? '', [user?.name])
  const value = useMemo(
    () => ({ user, firstName, loading, sessionError, retrySession, setUser, logout }),
    [user, firstName, loading, sessionError, retrySession, setUser, logout],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
