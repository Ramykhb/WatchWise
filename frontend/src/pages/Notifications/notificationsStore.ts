import { useEffect, useSyncExternalStore } from 'react'
import { api } from '../../lib/api.ts'
import { dayGroup, look, shortName, timeAgo } from '../../lib/format.ts'
import type { ApiNotification } from '../../lib/types.ts'

// Notifications shared by the header bell (unread badge) and the Notifications page, loaded from
// GET /api/notifications. Other members' actions create them on the backend (follows, invites,
// RSVPs, votes, @mentions); the header polls every minute since there is no real-time channel yet.

export type NotificationKind = ApiNotification['kind']

export type AppNotification = {
  id: number
  kind: NotificationKind
  /** Who did it, shown in bold; omitted for system notices. */
  actor?: { name: string; initials: string; color: string; handle: string }
  /** Sentence after the actor, e.g. "invited you to". */
  text: string
  /** Bold object of the sentence, e.g. "Sci-fi Saturday". */
  target?: string
  /** Optional quote or detail line under the sentence. */
  detail?: string
  time: string
  group: 'Today' | 'Yesterday' | 'This week' | 'Earlier'
  to: string
  /** Label of the action link, e.g. "Vote". */
  action: string
  read: boolean
}

type State = { items: AppNotification[]; loaded: boolean }

let state: State = { items: [], loaded: false }
const listeners = new Set<() => void>()

function emit(items: AppNotification[]) {
  state = { items, loaded: true }
  listeners.forEach((listener) => listener())
}

function subscribe(listener: () => void) {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

const getState = () => state

const toItem = (n: ApiNotification): AppNotification => ({
  id: n.id,
  kind: n.kind,
  actor: n.actor ? { name: shortName(n.actor.name), handle: n.actor.username, ...look(n.actor) } : undefined,
  text: n.text,
  target: n.target ?? undefined,
  detail: n.detail ?? undefined,
  time: timeAgo(n.created_at),
  group: dayGroup(n.created_at),
  to: n.to,
  action: n.action,
  read: n.read,
})

export async function loadNotifications() {
  try {
    const response = await api.get<{ notifications: ApiNotification[] }>('/notifications')
    emit(response.data.notifications.map(toItem))
  } catch {
    // Keep what is on screen; the next poll tries again.
  }
}

/** Drops everything on sign-out so the next account starts clean. */
export function clearNotifications() {
  state = { items: [], loaded: false }
  listeners.forEach((listener) => listener())
}

export function useNotifications() {
  return useSyncExternalStore(subscribe, getState)
}

export function useUnreadCount() {
  return useNotifications().items.filter((n) => !n.read).length
}

/** Loads now and every minute while mounted (the header is on every signed-in page). */
export function useNotificationPolling() {
  useEffect(() => {
    void loadNotifications()
    const timer = window.setInterval(() => void loadNotifications(), 60_000)
    return () => window.clearInterval(timer)
  }, [])
}

// Changes show at once and are saved in the background; a failed save reloads the real state.

export function markRead(id: number, read = true) {
  emit(state.items.map((n) => (n.id === id ? { ...n, read } : n)))
  api.patch(`/notifications/${id}`, { read }).catch(() => void loadNotifications())
}

export function markAllRead() {
  emit(state.items.map((n) => ({ ...n, read: true })))
  api.post('/notifications/read-all').catch(() => void loadNotifications())
}

export function dismiss(id: number) {
  emit(state.items.filter((n) => n.id !== id))
  api.delete(`/notifications/${id}`).catch(() => void loadNotifications())
}
