import type { Movie } from '../../components/MovieTile/MovieTile.tsx'
import { look, shortName } from '../../lib/format.ts'
import { movieFor } from '../../lib/movies.ts'
import type { ApiMember, ApiMessage, ApiRoom } from '../../lib/types.ts'

// Turns GET /api/rooms and /api/rooms/{id}/messages into what the Rooms page draws.
// TODO: replace polling with a real-time channel (e.g. Laravel Reverb).

export type Member = { id: number; handle: string; name: string; initials: string; color: string }

export type Message =
  | { id: string; kind: 'system'; text: string }
  | { id: string; kind: 'message'; messageId: number; from: Member; time: string; text: string }

export type Room = {
  id: string
  name: string
  movie: Movie
  /** People who have posted in the room. */
  members: Member[]
  host: Member | null
  spoilers: boolean
  joined: boolean
  /** Last-message preview in the room list. */
  preview: string
  rules: string[]
}

export const toRoomMember = (m: ApiMember): Member => ({ id: m.id, handle: m.username, name: shortName(m.name), ...look(m) })

/** A room's rules; `rulesFor(false)` is also shown before any room exists. */
export const rulesFor = (spoilers: boolean) => [
  'Be kind — critique films, not people',
  spoilers ? 'Spoilers allowed in this room' : 'No spoilers — use spoiler tags',
  'No links to pirated content',
]

export const toRoom = (room: ApiRoom): Room => ({
  id: String(room.id),
  name: room.name,
  movie: movieFor(room.tmdb_id),
  members: room.members.map(toRoomMember),
  host: room.host ? toRoomMember(room.host) : null,
  spoilers: room.spoilers,
  joined: room.joined,
  preview: room.last_message
    ? `${room.last_message.author?.split(' ')[0] ?? 'Someone'}: ${room.last_message.content}`
    : 'No messages yet — say hi!',
  rules: rulesFor(room.spoilers),
})

/** Messages with a "Today" / "Yesterday" / date line wherever the day changes. */
export function toMessages(messages: ApiMessage[]): Message[] {
  const out: Message[] = []
  let lastDay = ''
  for (const message of messages) {
    const date = new Date(message.created_at)
    const day = dayLabel(date)
    if (day !== lastDay) {
      out.push({ id: `day-${message.id}`, kind: 'system', text: day })
      lastDay = day
    }
    out.push({ id: String(message.id), kind: 'message', messageId: message.id, from: toRoomMember(message.user), time: clockTime(date), text: message.content })
  }
  return out
}

function dayLabel(date: Date) {
  const today = new Date()
  const yesterday = new Date(today.getTime() - 86_400_000)
  if (date.toDateString() === today.toDateString()) return 'Today'
  if (date.toDateString() === yesterday.toDateString()) return 'Yesterday'
  return date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' })
}

/** "#neon-tide-ending" for the composer placeholder. */
export const channelName = (room: Room) => `#${room.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 28)}`

export const EMOJIS = ['😂', '😭', '🔥', '👏', '🍿', '🎬']

/** "8:45 PM" */
export const clockTime = (date = new Date()) => date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
