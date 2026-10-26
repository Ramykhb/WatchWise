import type { Movie } from '../../components/MovieTile/MovieTile.tsx'
import { look, shortName } from '../../lib/format.ts'
import { movieFor } from '../../lib/movies.ts'
import type { ApiMember, ApiMessage, ApiRoom } from '../../lib/types.ts'

// Turns GET /api/rooms and the room's messages (this browser's copy, the server and the socket) into what the
// Rooms page draws.

export type Member = { id: number; handle: string; name: string; initials: string; color: string }

export type Message =
  | { id: string; kind: 'system'; text: string }
  | { id: string; kind: 'message'; messageId: number; from: Member; time: string; text: string; receipt: string }

export type Room = {
  id: string
  name: string
  movie: Movie
  /** The host and everyone who joined; a message waits until all of them have read it. */
  members: Member[]
  host: Member | null
  spoilers: boolean
  /** Private rooms are seen only by their members and the people they invite. */
  isPrivate: boolean
  joined: boolean
  /** You are invited to this private room: you can read it, then join or decline. */
  invited: boolean
  invitedBy: Member | null
  /** People invited who have not answered yet. */
  invitees: Member[]
  /** Messages still waiting for you to read them. */
  unread: number
  /** Last-message preview in the room list. */
  preview: string
  /** The newest stored message's time, else the room's creation (ISO). */
  lastActivity: string
  rules: string[]
}

export const toRoomMember = (m: ApiMember): Member => ({ id: m.id, handle: m.username, name: shortName(m.name), ...look(m) })

/** Room-list filters: every room you can see, or only yours; yours can be narrowed to public or private ones. */
export type RoomScope = 'all' | 'mine'
export type RoomKind = 'any' | 'public' | 'private'

export const ROOM_SCOPES: { id: RoomScope; label: string }[] = [
  { id: 'all', label: 'All rooms' },
  { id: 'mine', label: 'My rooms' },
]

export const ROOM_KINDS: { id: RoomKind; label: string }[] = [
  { id: 'any', label: 'All' },
  { id: 'public', label: 'Public' },
  { id: 'private', label: 'Private' },
]

/** The list's sections: invitations to answer, rooms you are in, and public rooms you can join. */
export type RoomSection = 'invites' | 'mine' | 'public'

export const sectionOf = (room: Room): RoomSection => (room.joined ? 'mine' : room.invited ? 'invites' : 'public')

export const matchesFilters = (room: Room, scope: RoomScope, kind: RoomKind) =>
  scope === 'all' || (room.joined && (kind === 'any' || room.isPrivate === (kind === 'private')))

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
  isPrivate: room.is_private,
  joined: room.joined,
  invited: room.invited,
  invitedBy: room.invited_by ? toRoomMember(room.invited_by) : null,
  invitees: room.invitees.map(toRoomMember),
  unread: room.unread,
  preview: room.last_message
    ? `${room.last_message.author?.split(' ')[0] ?? 'Someone'}: ${room.last_message.content}`
    : room.invited
      ? `${room.invited_by?.name.split(' ')[0] ?? 'A member'} invited you`
      : 'No new messages',
  lastActivity: room.last_message?.created_at ?? room.created_at,
  rules: rulesFor(room.spoilers),
})

/**
 * A message pushed to the room list (`message.sent` on `rooms.public` / `rooms.user.{id}`): it becomes the
 * room's latest message, and counts as unread when it waits for you.
 */
export const withNewMessage = (rooms: ApiRoom[], message: ApiMessage, viewerId: number): ApiRoom[] =>
  rooms.map((room) =>
    room.id !== message.room_id
      ? room
      : {
          ...room,
          message_count: room.message_count + 1,
          unread: room.unread + (message.unread_by.some((member) => member.id === viewerId) ? 1 : 0),
          last_message: { author: message.user.name, content: message.content, created_at: message.created_at },
        },
  )

/** "Lina: see you at 9" for the room list. */
export const previewOf = (message: ApiMessage) => `${message.user.name.split(' ')[0]}: ${message.content}`

/** Messages with a "Today" / "Yesterday" / date line wherever the day changes, and who has read each one. */
export function toMessages(messages: ApiMessage[], viewerId: number | undefined): Message[] {
  const out: Message[] = []
  let lastDay = ''
  for (const message of messages) {
    const date = new Date(message.created_at)
    const day = dayLabel(date)
    if (day !== lastDay) {
      out.push({ id: `day-${message.id}`, kind: 'system', text: day })
      lastDay = day
    }
    out.push({
      id: String(message.id),
      kind: 'message',
      messageId: message.id,
      from: toRoomMember(message.user),
      time: clockTime(date),
      text: message.content,
      receipt: receiptLabel(message, viewerId),
    })
  }
  return out
}

/** "Read by Lina S. and you", "Seen by everyone" once no member is left to read it, or "Not read yet". */
export function receiptLabel(message: ApiMessage, viewerId: number | undefined): string {
  const readers = message.read_by.filter((member) => member.id !== message.user.id)
  if (message.unread_by.length === 0) return readers.length > 0 ? 'Seen by everyone' : ''
  if (readers.length === 0) return 'Not read yet'
  const names = readers.map((member) => (member.id === viewerId ? 'you' : shortName(member.name)))
  if (names.length > 3) return `Read by ${names.slice(0, 2).join(', ')} and ${names.length - 2} others`
  return `Read by ${names.length > 1 ? `${names.slice(0, -1).join(', ')} and ${names.at(-1)}` : names[0]}`
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
