import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { authApi, type AuthUser } from '../lib/auth.ts'
import { clearApiCache, SESSION_EXPIRED_EVENT } from '../lib/api.ts'
import { clearMyLists } from '../pages/Lists/myListsStore.ts'
import { clearNights } from '../pages/MovieNights/nightsStore.ts'
import { clearNotifications } from '../pages/Notifications/notificationsStore.ts'
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
  const userId = useRef<number | null>(null)

  // Signing in as someone else, or out, never shows the previous account's lists, nights or cache.
  const setUser = useCallback((next: AuthUser | null) => {
    if ((next?.id ?? null) !== userId.current) {
      userId.current = next?.id ?? null
      resetUserData()
    }
    setUserState(next)
  }, [])

  useEffect(() => {
    let active = true
    const clearExpiredSession = () => setUser(null)
    window.addEventListener(SESSION_EXPIRED_EVENT, clearExpiredSession)
    const loadSession = async () => {
      try {
        const response = await authApi.get<{ user: AuthUser }>('/auth/me')
        if (active) setUser(response.data.user)
      } catch {
        if (active) setUser(null)
      } finally {
        if (active) setLoading(false)
      }
    }
    void loadSession()
    return () => {
      active = false
      window.removeEventListener(SESSION_EXPIRED_EVENT, clearExpiredSession)
    }
  }, [setUser])

  const logout = useCallback(async () => {
    try {
      await authApi.post('/auth/logout')
    } finally {
      setUser(null)
    }
  }, [setUser])
  const firstName = useMemo(() => user?.name.trim().split(/\s+/)[0] ?? '', [user?.name])
  const value = useMemo(() => ({ user, firstName, loading, setUser, logout }), [user, firstName, loading, setUser, logout])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
