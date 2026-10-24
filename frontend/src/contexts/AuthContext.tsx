import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { authApi, type AuthPayload, type AuthUser } from '../lib/auth.ts'
import { clearApiCache } from '../lib/api.ts'
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
    const loadSession = async () => {
      try {
        const response = await authApi.get<{ user: AuthUser }>('/me')
        if (active) setUser(response.data.user)
      } catch {
        try {
          const refreshed = await authApi.post<AuthPayload>('/refresh')
          if (active) setUser(refreshed.data.user)
        } catch {
          if (active) setUser(null)
        }
      } finally {
        if (active) setLoading(false)
      }
    }
    void loadSession()
    return () => { active = false }
  }, [setUser])

  const logout = useCallback(async () => {
    try {
      await authApi.post('/logout')
    } finally {
      setUser(null)
    }
  }, [setUser])
  const value = useMemo(() => ({ user, loading, setUser, logout }), [user, loading, setUser, logout])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
