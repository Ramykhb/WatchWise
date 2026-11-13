import type { ReactNode } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router'
import { useAuth } from '../../contexts/useAuth.ts'
import { canEnterAdminPath } from '../../lib/adminApi.ts'
import SessionStatus from './SessionStatus.tsx'

/**
 * The signed-in pages are keyed by account id: when another tab switches accounts, every mounted page is rebuilt,
 * so none keeps showing the previous account's loaded data while its requests now run as the new one.
 */
/** Signed-in pages: guests go to Sign in and come back here afterwards; unverified accounts go to Verify email. */
export function RequireAuth() {
  const { user, loading, sessionError } = useAuth()
  const location = useLocation()
  if (loading || sessionError) return <SessionStatus />
  if (!user) return <Navigate to="/signin" replace state={{ from: location }} />
  if (!user.email_verified_at) return <Navigate to="/verify-email" replace />
  return <Outlet key={user.id} />
}

/** Landing and auth pages: signed-in users go straight to Home. */
export function RequireGuest({ children }: { children: ReactNode }) {
  const { user, loading, sessionError } = useAuth()
  if (loading || sessionError) return <SessionStatus />
  if (user) return <Navigate to={user.email_verified_at ? "/home-page" : "/verify-email"} replace />
  return children
}

/**
 * Administrator pages: admins open all of them, moderators share moderation tools; Movies, Settings and Audit history stay with admins, everyone else goes
 * Home. The API checks the role too.
 */
export function RequireAdmin() {
  const { user, loading, sessionError } = useAuth()
  const location = useLocation()
  if (loading || sessionError) return <SessionStatus />
  if (!user) return <Navigate to="/signin" replace state={{ from: location }} />
  if (!user.email_verified_at) return <Navigate to="/verify-email" replace />
  if (canEnterAdminPath(user.role, location.pathname)) return <Outlet key={user.id} />
  if (user.role === 'moderator') return <Navigate to="/admin" replace />
  return <Navigate to="/home-page" replace />
}
