import { useEffect, useSyncExternalStore } from 'react'
import { api } from '../../lib/api.ts'
import { look, shortName } from '../../lib/format.ts'
import { itemKey, type FilmKey } from '../../lib/movies.ts'
import type { ApiNotification } from '../../lib/types.ts'
import type { NotificationKind } from './notificationKinds.ts'
import { openNotificationSocket, type NotificationSocket, type PushedNotification } from './notificationSocket.ts'

// Notifications shared by the header bell (unread badge), the pop-ups and the Notifications page, loaded from
// GET /api/notifications and kept live over Reverb: other members' actions push new ones as they happen, and the
// list reloads when it changes elsewhere. One socket stays open per signed-in session (the header mounts on
// every page); while it is down the header polls every minute instead.

export type FollowState = 'none' | 'following' | 'requested'

export type AppNotification = {
  id: number
  kind: NotificationKind
  /** Who did it, shown in bold, with the signed-in user's follow state towards them. */
  actor: { name: string; initials: string; color: string; handle: string; follow: FollowState }
  /** Sentence after the actor, e.g. "invited you to". */
  text: string
  /** Bold object of the sentence, e.g. "Sci-fi Saturday". */
  target?: string
  /** Optional quote under the sentence. */
  detail?: string
  /** The film it is about; its title is looked up where it is shown. */
  film?: FilmKey
  createdAt: string
  to: string
  /** Label of the action link, e.g. "Vote". */
  action: string
  read: boolean
}

type State = { items: AppNotification[]; loaded: boolean; failed: boolean }

let state: State = { items: [], loaded: false, failed: false }
const listeners = new Set<() => void>()

function notify() {
  listeners.forEach((listener) => listener())
}

function emit(items: AppNotification[]) {
  state = { items, loaded: true, failed: false }
  notify()
}

function subscribe(listener: () => void) {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

const getState = () => state

const toItem = (n: ApiNotification): AppNotification => ({
  id: n.id,
  kind: n.kind,
  actor: {
    name: shortName(n.actor.name),
    handle: n.actor.username,
    follow: n.actor_followed ? 'following' : n.actor_requested ? 'requested' : 'none',
    ...look(n.actor),
  },
  text: n.text,
  target: n.target ?? undefined,
  detail: n.detail ?? undefined,
  film: n.film ? itemKey(n.film) : undefined,
  createdAt: n.created_at,
  to: n.to,
  action: n.action,
  read: n.read,
})

// Bumped on every account change, so a response for the previous account is ignored.
let generation = 0

export async function loadNotifications() {
  const started = generation
  if (state.failed) {
    state = { ...state, loaded: false, failed: false }
    notify()
  }
  try {
    const response = await api.get<{ notifications: ApiNotification[] }>('/notifications')
    if (started === generation) emit(response.data.notifications.map(toItem))
  } catch {
    // Keep existing data visible, but settle the initial loader on failure.
    if (started === generation && !state.loaded) {
      state = { ...state, loaded: true, failed: true }
      notify()
    }
  }
}

export function useNotifications() {
  return useSyncExternalStore(subscribe, getState)
}

export function useUnreadCount() {
  return useNotifications().items.filter((n) => !n.read).length
}

// ---------- Pop-ups ----------

/** Newest first; a few at most, each closes itself after a while (NotificationPopups). */
let popups: AppNotification[] = []
const popupListeners = new Set<() => void>()
const POPUPS_SHOWN = 3

function setPopups(next: AppNotification[]) {
  popups = next
  popupListeners.forEach((listener) => listener())
}

export function usePopups() {
  return useSyncExternalStore(
    (listener) => {
      popupListeners.add(listener)
      return () => popupListeners.delete(listener)
    },
    () => popups,
  )
}

export function closePopup(id: number) {
  if (popups.some((popup) => popup.id === id)) setPopups(popups.filter((popup) => popup.id !== id))
}

/** A notification about the page you are on (e.g. the room you have open) needs no pop-up. */
const isOnPage = (to: string) => window.location.pathname === to.split('#')[0]

function receive({ notification, silent }: PushedNotification) {
  const item = toItem(notification)
  // Pushed ones are new or brought up to date, so they go to the top.
  emit([item, ...state.items.filter((n) => n.id !== item.id)])
  if (!silent && !item.read && !isOnPage(item.to)) {
    setPopups([item, ...popups.filter((popup) => popup.id !== item.id)].slice(0, POPUPS_SHOWN))
  }
}

// ---------- Live connection ----------

const POLL_MS = 60_000
let liveUser: number | null = null
let socket: NotificationSocket | null = null
let connected = false
let pollTimer: number | undefined

/**
 * Keeps the list live while signed in. Every page renders the header, which calls this; the socket opens on the
 * first call for an account and stays open across pages until sign-out.
 */
export function useLiveNotifications(userId: number | undefined) {
  useEffect(() => {
    if (userId !== undefined) startLive(userId)
  }, [userId])
}

function startLive(userId: number) {
  if (liveUser === userId) return
  stopLive()
  liveUser = userId
  const started = generation

  void loadNotifications()
  // Until (and whenever) the socket is down, the list still refreshes every minute.
  pollTimer = window.setInterval(() => {
    if (!connected) void loadNotifications()
  }, POLL_MS)

  // A socket still connecting when its account signed out must not touch the next account's list.
  const current = () => started === generation && liveUser === userId
  openNotificationSocket(userId, {
    onPushed: (pushed) => current() && receive(pushed),
    onChanged: () => current() && void loadNotifications(),
    onReady: () => current() && void loadNotifications(),
    onConnected: (isConnected) => {
      if (current()) connected = isConnected
    },
  })
    .then((opened) => {
      // Signed out (or into another account) while it was connecting.
      if (started !== generation || liveUser !== userId) opened.disconnect()
      else socket = opened
    })
    .catch(() => undefined)
}

function stopLive() {
  window.clearInterval(pollTimer)
  socket?.disconnect()
  socket = null
  connected = false
  liveUser = null
}

/** Drops everything on sign-out so the next account starts clean. */
export function clearNotifications() {
  generation++
  stopLive()
  state = { items: [], loaded: false, failed: false }
  notify()
  setPopups([])
}

// ---------- Changes ----------
// Changes show at once and are saved in the background; a failed save reloads the real state.

export function markRead(id: number, read = true) {
  emit(state.items.map((n) => (n.id === id ? { ...n, read } : n)))
  api.patch(`/notifications/${id}`, { read }).catch(() => void loadNotifications())
}

export function markAllRead() {
  emit(state.items.map((n) => ({ ...n, read: true })))
  api.post('/notifications/read-all').catch(() => void loadNotifications())
}

/** After a follow, unfollow or request, every notification from that person shows the new state. */
export function setActorFollow(handle: string, follow: FollowState) {
  emit(state.items.map((n) => (n.actor.handle === handle ? { ...n, actor: { ...n.actor, follow } } : n)))
}

export function dismiss(id: number) {
  emit(state.items.filter((n) => n.id !== id))
  api.delete(`/notifications/${id}`).catch(() => void loadNotifications())
}

/** Accepting turns the request into "started following you" (with Follow back), as the server does. */
export function acceptFollowRequest(item: AppNotification) {
  emit(state.items.map((n) => (n.id === item.id ? { ...n, kind: 'follow', text: 'started following you', read: true } : n)))
  api.post(`/me/follow-requests/${encodeURIComponent(item.actor.handle)}/accept`).catch(() => void loadNotifications())
}

export function declineFollowRequest(item: AppNotification) {
  emit(state.items.filter((n) => n.id !== item.id))
  api.delete(`/me/follow-requests/${encodeURIComponent(item.actor.handle)}`).catch(() => void loadNotifications())
}
