import { createContext, useContext } from 'react'
import type { AuthUser } from '../lib/auth.ts'

// Kept apart from AuthProvider so AuthContext.tsx only exports a component (React Fast Refresh).

export type AuthContextValue = {
  user: AuthUser | null
  firstName: string
  loading: boolean
  setUser: (user: AuthUser | null) => void
  logout: () => Promise<void>
}

export const AuthContext = createContext<AuthContextValue | null>(null)

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth must be used within AuthProvider')
  return context
}
