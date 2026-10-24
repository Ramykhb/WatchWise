import type { ReactNode } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router'
import { useAuth } from '../../contexts/useAuth.ts'

/** Signed-in pages: guests go to Sign in and come back here afterwards. */
export function RequireAuth() {
  const { user, loading } = useAuth()
  const location = useLocation()
  if (loading) return null
  if (!user) return <Navigate to="/signin" replace state={{ from: location }} />
  return <Outlet />
}

/** Landing and auth pages: signed-in users go straight to Home. */
export function RequireGuest({ children }: { children: ReactNode }) {
  const { user, loading } = useAuth()
  if (loading) return null
  if (user) return <Navigate to="/home-page" replace />
  return children
}
