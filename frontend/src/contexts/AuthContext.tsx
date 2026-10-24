import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { authApi, type AuthPayload, type AuthUser } from '../lib/auth.ts'
import { clearNotifications } from '../pages/Notifications/notificationsStore.ts'
import { clearApiCache } from '../lib/api.ts'
import { AuthContext } from './useAuth.ts'

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null)
  const [loading, setLoading] = useState(true)

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
  }, [])

  const logout = useCallback(async () => {
    try {
      await authApi.post('/logout')
    } finally {
      clearNotifications()
      clearApiCache()
      setUser(null)
    }
  }, [])
  const value = useMemo(() => ({ user, loading, setUser, logout }), [user, loading, logout])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
