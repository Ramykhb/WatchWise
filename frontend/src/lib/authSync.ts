/** Cross-tab session sync: login, logout and user changes are announced to the other tabs. */

export const AUTH_CHANNEL = 'watchwise-auth'
/** localStorage key used as a fallback transport; it only carries a message, never user data. */
export const AUTH_SYNC_KEY = 'watchwise-auth-sync'
export const HIDDEN_RECHECK_MS = 60_000

export type AuthSyncType = 'login' | 'logout' | 'user-change'
export type AuthSyncMessage = { type: AuthSyncType; origin: string; at: number }

export type SyncedUser = { id: number; verified: boolean }

/**
 * What a `setUser` call means to other tabs, or null when nothing they care about changed: only a sign-in,
 * sign-out, switch to another account (all 'login'/'logout') or a change of verified status ('user-change')
 * is announced. Other updates to the same account (profile saves, a focus re-check) are not.
 */
export function transitionType(previous: SyncedUser | null, next: SyncedUser | null): AuthSyncType | null {
  if (next === null) return previous === null ? null : 'logout'
  if (previous === null || previous.id !== next.id) return 'login'
  return previous.verified !== next.verified ? 'user-change' : null
}

/** A valid message from another tab; null for junk or for this tab's own echo. */
export function parseAuthMessage(raw: unknown, ownId: string): AuthSyncMessage | null {
  let value = raw
  if (typeof raw === 'string') {
    try { value = JSON.parse(raw) } catch { return null }
  }
  const message = value as Partial<AuthSyncMessage> | null
  if (!message || typeof message !== 'object') return null
  if (message.type !== 'login' && message.type !== 'logout' && message.type !== 'user-change') return null
  if (typeof message.origin !== 'string' || message.origin === ownId) return null
  return { type: message.type, origin: message.origin, at: typeof message.at === 'number' ? message.at : 0 }
}

/** True when the tab was hidden long enough that its session may have changed. */
export function shouldRecheckOnVisible(hiddenAt: number | null, now: number, threshold = HIDDEN_RECHECK_MS): boolean {
  return hiddenAt !== null && now - hiddenAt > threshold
}

type ChannelLike = {
  postMessage: (message: unknown) => void
  close: () => void
  onmessage: ((event: { data: unknown }) => void) | null
}
type StorageEventLike = { key: string | null; newValue: string | null }
export type AuthSyncEnv = {
  createChannel?: (name: string) => ChannelLike
  storage?: { setItem: (key: string, value: string) => void }
  addStorageListener?: (listener: (event: StorageEventLike) => void) => () => void
  now?: () => number
}

function browserEnv(): AuthSyncEnv {
  return {
    createChannel: typeof BroadcastChannel === 'undefined' ? undefined : (name) => new BroadcastChannel(name) as unknown as ChannelLike,
    storage: typeof localStorage === 'undefined' ? undefined : localStorage,
    addStorageListener: typeof window === 'undefined' ? undefined : (listener) => {
      const handler = (event: StorageEvent) => listener(event)
      window.addEventListener('storage', handler)
      return () => window.removeEventListener('storage', handler)
    },
  }
}

/**
 * Joins the sync channel. `onRemote` only ever sees messages from other tabs, so a tab that reacts to one
 * never rebroadcasts it (the handler must not call `broadcast`).
 */
export function createAuthSync(onRemote: (message: AuthSyncMessage) => void, env: AuthSyncEnv = browserEnv()) {
  const now = env.now ?? Date.now
  const ownId = `${Math.random().toString(36).slice(2)}${now().toString(36)}`
  let channel: ChannelLike | undefined
  let stopStorage: (() => void) | undefined
  try { channel = env.createChannel?.(AUTH_CHANNEL) } catch { channel = undefined }

  if (channel) {
    channel.onmessage = (event) => {
      const message = parseAuthMessage(event.data, ownId)
      if (message) onRemote(message)
    }
  } else {
    stopStorage = env.addStorageListener?.((event) => {
      if (event.key !== AUTH_SYNC_KEY || !event.newValue) return
      const message = parseAuthMessage(event.newValue, ownId)
      if (message) onRemote(message)
    })
  }

  return {
    ownId,
    broadcast(type: AuthSyncType) {
      const message: AuthSyncMessage = { type, origin: ownId, at: now() }
      try {
        if (channel) channel.postMessage(message)
        else env.storage?.setItem(AUTH_SYNC_KEY, JSON.stringify(message))
      } catch { /* Channel or storage unavailable: other tabs recheck when they regain focus. */ }
    },
    close() {
      if (channel) channel.onmessage = null
      channel?.close()
      stopStorage?.()
    },
  }
}
