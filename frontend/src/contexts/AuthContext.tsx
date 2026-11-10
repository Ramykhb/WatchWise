import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { authApi, type AuthUser } from '../lib/auth.ts'
import { clearApiCache, EMAIL_UNVERIFIED_EVENT, isStatus, SESSION_EXPIRED_EVENT, SESSION_REQUEST_TIMEOUT } from '../lib/api.ts'
import { clearMyLists } from '../pages/Lists/myListsStore.ts'
import { clearNights } from '../pages/MovieNights/nightsStore.ts'
import { clearNotifications } from '../pages/Notifications/notificationsStore.ts'
import { forgetSavedRooms } from '../pages/Rooms/roomHistory.ts'
import { createAuthSync, shouldRecheckOnVisible, transitionType } from '../lib/authSync.ts'
import { clearUserStorage } from '../lib/userStorage.ts'
import { AuthContext } from './useAuth.ts'

/** Everything kept in memory for one account; dropped whenever the signed-in account changes. */
function resetUserData() {
  clearApiCache()
  clearNotifications()
  clearMyLists()
  clearNights()
  clearUserStorage()
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUserState] = useState<AuthUser | null>(null)
  const [loading, setLoading] = useState(true)
  const [sessionError, setSessionError] = useState<string | null>(null)
  const [sessionAttempt, setSessionAttempt] = useState(0)
  const userId = useRef<number | null>(null)
  const verified = useRef(false)
  const sync = useRef<ReturnType<typeof createAuthSync> | null>(null)

  // Signing in as someone else, or out, never shows the previous account's lists, nights or cache.
  const applyUser = useCallback((next: AuthUser | null, announce: boolean) => {
    const previous = userId.current
    const type = transitionType(previous === null ? null : { id: previous, verified: verified.current }, next && { id: next.id, verified: Boolean(next.email_verified_at) })
    verified.current = Boolean(next?.email_verified_at)
    if ((next?.id ?? null) !== previous) {
      userId.current = next?.id ?? null
      resetUserData()
    }
    setUserState(next)
    setSessionError(null)
    if (announce && type) sync.current?.broadcast(type)
  }, [])
  // Changes made in this tab are announced to the others; results of a session check are not.
  const setUser = useCallback((next: AuthUser | null) => applyUser(next, true), [applyUser])

  const retrySession = useCallback(() => {
    setSessionError(null)
    setLoading(true)
    setSessionAttempt((attempt) => attempt + 1)
  }, [])

  // The API says this account is not verified: mark it so, and the route guards send it to Verify email.
  useEffect(() => {
    const markUnverified = () => setUserState((current) => (current?.email_verified_at ? { ...current, email_verified_at: null } : current))
    window.addEventListener(EMAIL_UNVERIFIED_EVENT, markUnverified)
    return () => window.removeEventListener(EMAIL_UNVERIFIED_EVENT, markUnverified)
  }, [])

  // Room conversations are no longer saved in the browser; drop any an earlier version left behind.
  useEffect(() => forgetSavedRooms(), [])

  useEffect(() => {
    let active = true
    const controller = new AbortController()
    const clearExpiredSession = () => applyUser(null, true)
    window.addEventListener(SESSION_EXPIRED_EVENT, clearExpiredSession)
    const loadSession = async () => {
      try {
        // `_probe`: a failed check must not log out or hard-redirect, so a guest's deep link reaches RequireAuth.
        const response = await authApi.get<{ user: AuthUser }>('/auth/me', {
          signal: controller.signal,
          timeout: SESSION_REQUEST_TIMEOUT,
          _probe: true,
        })
        if (active) applyUser(response.data.user, false)
      } catch (error) {
        if (!active) return
        if (isStatus(error, 401) || isStatus(error, 403)) applyUser(null, false)
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
  }, [applyUser, sessionAttempt])

  // Quietly re-check the session (no loading screen) when another tab changed it or this tab comes back.
  useEffect(() => {
    let controller: AbortController | null = null
    let hiddenAt: number | null = null
    const recheck = async () => {
      controller?.abort()
      const current = (controller = new AbortController())
      try {
        const response = await authApi.get<{ user: AuthUser }>('/auth/me', {
          signal: current.signal,
          timeout: SESSION_REQUEST_TIMEOUT,
          _probe: true,
        })
        applyUser(response.data.user, false)
      } catch (error) {
        if (current.signal.aborted) return
        if (isStatus(error, 401) || isStatus(error, 403)) applyUser(null, false)
        // Anything else (offline, server error) keeps the current state.
      }
    }
    const channel = createAuthSync((message) => {
      // A verification change keeps the account, so its caches stay; the re-check updates the user.
      if (message.type !== 'user-change') resetUserData()
      void recheck()
    })
    sync.current = channel
    const onVisibility = () => {
      if (document.visibilityState === 'hidden') hiddenAt = Date.now()
      else {
        if (shouldRecheckOnVisible(hiddenAt, Date.now())) void recheck()
        hiddenAt = null
      }
    }
    document.addEventListener('visibilitychange', onVisibility)
    return () => {
      document.removeEventListener('visibilitychange', onVisibility)
      controller?.abort()
      channel.close()
      if (sync.current === channel) sync.current = null
    }
  }, [applyUser])

  // Throws when the server could not end the session, so the caller keeps the user signed in and says so.
  const logout = useCallback(async () => {
    try {
      await authApi.post('/auth/logout')
    } catch (error) {
      // 401 means the session is already gone, which is what signing out wants.
      if (!isStatus(error, 401)) throw error
    }
    setUser(null)
  }, [setUser])
  const firstName = useMemo(() => user?.name.trim().split(/\s+/)[0] ?? '', [user?.name])
  const value = useMemo(
    () => ({ user, firstName, loading, sessionError, retrySession, setUser, logout }),
    [user, firstName, loading, sessionError, retrySession, setUser, logout],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
