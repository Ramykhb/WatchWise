/** Pure helpers behind the API client's session refresh, kept apart so they can be tested without a network. */

export const REFRESH_LOCK_NAME = 'watchwise-refresh'

/** Only an expired session (401) is refreshed; 403 means email_unverified, policy or a disabled account. */
export function shouldRefreshStatus(status: number | undefined): boolean {
  return status === 401
}

export type RefreshDecision = 'reject' | 'refresh' | 'clear'

/** What the response interceptor does with a failed request. */
export function decideRefresh(input: {
  status: number | undefined
  hasConfig: boolean
  aborted: boolean
  skipUrl: boolean
  retried: boolean
}): RefreshDecision {
  if (!input.hasConfig || input.aborted || input.skipUrl || !shouldRefreshStatus(input.status)) return 'reject'
  return input.retried ? 'clear' : 'refresh'
}

/**
 * Whether a failed refresh/retry ends the session (clears cookies, leaves the page). The initial session
 * probe (`probe`) never does: a guest on a deep link just stays a guest and the route guard handles it.
 */
export function shouldEndSession(probe: boolean | undefined): boolean {
  return !probe
}

export type LockManagerLike = {
  request: <T>(name: string, callback: () => Promise<T>) => Promise<T>
}

/** The browser's Web Locks, when it has them. */
export function getLocks(nav: unknown = typeof navigator === 'undefined' ? undefined : navigator): LockManagerLike | undefined {
  const locks = (nav as { locks?: LockManagerLike } | undefined)?.locks
  return locks && typeof locks.request === 'function' ? locks : undefined
}

/** Runs `task` exclusively across tabs when Web Locks exist, otherwise straight away (in-tab behaviour). */
export function withRefreshLock<T>(task: () => Promise<T>, locks: LockManagerLike | undefined = getLocks()): Promise<T> {
  if (!locks) return task()
  return locks.request(REFRESH_LOCK_NAME, task)
}

/**
 * Refreshes the session once across tabs: inside the lock, `isSessionValid` checks whether another tab
 * already refreshed (then nothing is posted), otherwise `refresh` runs.
 */
export function refreshAcrossTabs(
  { isSessionValid, refresh }: { isSessionValid: () => Promise<boolean>; refresh: () => Promise<unknown> },
  locks: LockManagerLike | undefined = getLocks(),
): Promise<'already' | 'refreshed'> {
  return withRefreshLock(async () => {
    if (await isSessionValid().catch(() => false)) return 'already' as const
    await refresh()
    return 'refreshed' as const
  }, locks)
}
