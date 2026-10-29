import type { ReactNode } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router'
import { useAuth } from '../../contexts/useAuth.ts'
import { isAdminRole, isModeratorRole } from '../../lib/adminApi.ts'

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

/**
 * Administrator pages: admins open all of them, moderators everything except Movies, everyone else goes
 * Home. The API checks the role too.
 */
export function RequireAdmin() {
  const { user, loading } = useAuth()
  const location = useLocation()
  if (loading) return null
  if (!user) return <Navigate to="/signin" replace state={{ from: location }} />
  if (isAdminRole(user.role)) return <Outlet />
  if (isModeratorRole(user.role)) {
    return location.pathname.startsWith('/admin/movies') ? <Navigate to="/admin" replace /> : <Outlet />
  }
  return <Navigate to="/home-page" replace />
}
