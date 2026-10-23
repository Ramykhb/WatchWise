import { useSyncExternalStore } from 'react'

// In-memory notifications shared by the header bell (unread badge) and the Notifications page.
// No Figma frame exists for this screen; content follows the app's other mocks.
// TODO: load from the notifications API (the backend already has a notifications table) and
// push new ones over the real-time channel.

export type NotificationKind = 'invite' | 'vote' | 'mention' | 'follow' | 'like' | 'list' | 'release' | 'watchlist'

export type AppNotification = {
  id: string
  kind: NotificationKind
  /** Who did it, shown in bold; omitted for system notices. */
  actor?: { name: string; initials: string; color: string; handle?: string }
  /** Sentence after the actor, e.g. "invited you to". */
  text: string
  /** Bold object of the sentence, e.g. "Sci-fi Saturday". */
  target?: string
  /** Optional quote or detail line under the sentence. */
  detail?: string
  time: string
  group: 'Today' | 'Yesterday' | 'This week'
  to: string
  /** Label of the action link, e.g. "Vote". */
  action: string
  read: boolean
}

const OMAR = { name: 'Omar K.', initials: 'OK', color: 'var(--accent-coral)', handle: 'omark' }
const LINA = { name: 'Lina Saad', initials: 'LS', color: 'var(--accent-yellow)', handle: 'linasaad' }
const JAD = { name: 'Jad N.', initials: 'JN', color: 'var(--accent-mint)', handle: 'jadn' }
const RANA = { name: 'Rana H.', initials: 'RH', color: 'var(--surface-violet-tint)', handle: 'ranah' }

const INITIAL: AppNotification[] = [
  { id: 'n1', kind: 'invite', actor: OMAR, text: 'invited you to', target: 'Sci-fi Saturday', detail: 'Sat, Mar 15 · 9:00 PM · Online', time: '12 min ago', group: 'Today', to: '/movie-nights', action: 'Respond', read: false },
  { id: 'n2', kind: 'mention', actor: JAD, text: 'mentioned you in', target: 'Neon Tide — ending explained', detail: '“@Maya you called the lighthouse thing before anyone.”', time: '40 min ago', group: 'Today', to: '/rooms/neon-tide-ending', action: 'Reply', read: false },
  { id: 'n3', kind: 'vote', text: 'Voting closes in 18h for', target: 'Friday Frights', detail: 'Deep Blue leads with 5 votes — you voted for it.', time: '2 h ago', group: 'Today', to: '/movie-nights/friday-frights', action: 'See votes', read: false },
  { id: 'n4', kind: 'follow', actor: LINA, text: 'started following you', time: '5 h ago', group: 'Today', to: '/profile/linasaad', action: 'View profile', read: false },
  { id: 'n5', kind: 'like', actor: RANA, text: 'and 12 others found your review of', target: 'Neon Tide', detail: 'helpful', time: 'Yesterday', group: 'Yesterday', to: '/movies/neon-tide', action: 'View review', read: true },
  { id: 'n6', kind: 'list', actor: JAD, text: 'liked your list', target: 'Comfort Rewatches', time: 'Yesterday', group: 'Yesterday', to: '/lists/comfort-rewatches', action: 'Open list', read: true },
  { id: 'n7', kind: 'release', text: 'New in cinemas near you:', target: 'Velvet Hour II', detail: 'Showing at Grand Cinemas ABC from Mar 14.', time: 'Mon', group: 'This week', to: '/discover/cinemas', action: 'Showtimes', read: true },
  { id: 'n8', kind: 'watchlist', text: 'From your watchlist:', target: 'Stardust', detail: 'is now streaming.', time: 'Sun', group: 'This week', to: '/movies/stardust', action: 'Watch now', read: true },
]

let items = INITIAL
const listeners = new Set<() => void>()

function emit(next: AppNotification[]) {
  items = next
  listeners.forEach((listener) => listener())
}

function subscribe(listener: () => void) {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

const getItems = () => items

export function useNotifications() {
  return useSyncExternalStore(subscribe, getItems)
}

export function useUnreadCount() {
  return useNotifications().filter((n) => !n.read).length
}

export function markRead(id: string, read = true) {
  emit(items.map((n) => (n.id === id ? { ...n, read } : n)))
}

export function markAllRead() {
  emit(items.map((n) => ({ ...n, read: true })))
}

export function dismiss(id: string) {
  emit(items.filter((n) => n.id !== id))
}
