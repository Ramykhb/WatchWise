import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'
import { authApi, type AuthPayload, type AuthUser } from '../lib/auth.ts'

type AuthContextValue = {
  user: AuthUser | null
  loading: boolean
  setUser: (user: AuthUser | null) => void
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue | null>(null)

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
    try { await authApi.post('/logout') } finally { setUser(null) }
  }, [])
  const value = useMemo(() => ({ user, loading, setUser, logout }), [user, loading, logout])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth must be used within AuthProvider')
  return context
}
