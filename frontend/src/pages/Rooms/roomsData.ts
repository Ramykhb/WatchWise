import type { Movie } from '../../components/MovieTile/MovieTile.tsx'
import { MOVIES } from '../Home/homeData.ts'

// Mock content from the Figma "Discussion Rooms — Live Chat" frame. Replace with the rooms /
// messages API and a real-time channel (e.g. Laravel Reverb) once the backend is connected.

export type Member = { name: string; initials: string; color: string }

export type Message =
  | { id: string; kind: 'system'; text: string }
  | { id: string; kind: 'message'; from: Member; time: string; text: string; agree?: number; extra?: number; seenBy?: number }

export type Room = {
  id: string
  name: string
  /** Name in the room list when the full one is too long. */
  short?: string
  movie: Movie
  online: number
  unread: number
  spoilers: boolean
  host: Member
  members: Member[]
  messages: Message[]
  /** Last-message preview in the room list. */
  preview: string
  rules: string[]
}

export const YOU: Member = { name: 'You', initials: 'MR', color: 'var(--accent-mint)' }
const JAD: Member = { name: 'Jad N.', initials: 'JN', color: 'var(--accent-mint)' }
const LINA: Member = { name: 'Lina S.', initials: 'LS', color: 'var(--accent-yellow)' }
const OMAR: Member = { name: 'Omar K.', initials: 'OK', color: 'var(--accent-coral)' }
const RANA: Member = { name: 'Rana H.', initials: 'RH', color: 'var(--surface-violet-tint)' }
const SAM: Member = { name: 'Sam T.', initials: 'ST', color: 'var(--accent-coral)' }

const RULES = (spoilers: boolean) => [
  'Be kind — critique films, not people',
  spoilers ? 'Spoilers allowed in this room' : 'No spoilers — use spoiler tags',
  'No links to pirated content',
]

export const ROOMS: Room[] = [
  {
    id: 'neon-tide-ending',
    name: 'Neon Tide — ending explained',
    short: 'Neon Tide — ending',
    movie: MOVIES.neonTide,
    online: 128,
    unread: 0,
    spoilers: true,
    host: JAD,
    members: [JAD, LINA, OMAR, RANA],
    preview: 'Jad: the lighthouse scene…',
    rules: RULES(true),
    messages: [
      { id: 'n0', kind: 'system', text: 'Today' },
      {
        id: 'n1',
        kind: 'message',
        from: JAD,
        time: '8:41 PM',
        text: 'Okay so the lighthouse scene — was she actually back in 2019 or is the current just replaying the memory?',
        agree: 12,
        extra: 4,
      },
      {
        id: 'n2',
        kind: 'message',
        from: LINA,
        time: '8:42 PM',
        text: "Replaying! The colour grading switches to the cooler palette every time it's a memory.",
        agree: 31,
      },
      { id: 'n3', kind: 'message', from: YOU, time: '8:43 PM', text: 'YES. And the final shot is warm again, which means she chose the present.', seenBy: 24 },
      { id: 'n4', kind: 'system', text: 'Omar K. joined the room' },
      { id: 'n5', kind: 'message', from: OMAR, time: '8:44 PM', text: "Just finished it. Not crying, you're crying." },
    ],
  },
  {
    id: 'thriller-thursday',
    name: 'Thriller Thursday',
    movie: MOVIES.deepBlue,
    online: 64,
    unread: 3,
    spoilers: false,
    host: OMAR,
    members: [OMAR, LINA, SAM],
    preview: 'Omar: Deep Blue tonight?',
    rules: RULES(false),
    messages: [
      { id: 't0', kind: 'system', text: 'Today' },
      { id: 't1', kind: 'message', from: LINA, time: '7:58 PM', text: 'Who is still up for a thriller this week?', agree: 6 },
      { id: 't2', kind: 'message', from: SAM, time: '8:05 PM', text: 'Always. Something slow-burn please.' },
      { id: 't3', kind: 'message', from: OMAR, time: '8:12 PM', text: 'Deep Blue tonight?', agree: 9 },
    ],
  },
  {
    id: 'oscars-2026',
    name: 'Oscars 2026 picks',
    movie: MOVIES.paperMoon,
    online: 312,
    unread: 12,
    spoilers: false,
    host: RANA,
    members: [RANA, JAD, LINA, OMAR],
    preview: 'Rana: Best Director is locked',
    rules: RULES(false),
    messages: [
      { id: 'o0', kind: 'system', text: 'Today' },
      { id: 'o1', kind: 'message', from: JAD, time: '6:30 PM', text: 'Paper Moon for Best Picture, fight me.', agree: 44, extra: 9 },
      { id: 'o2', kind: 'message', from: RANA, time: '6:34 PM', text: 'Best Director is locked. Nobody else came close this year.', agree: 58 },
    ],
  },
  {
    id: 'comfort-rewatch',
    name: 'Comfort Rewatch Club',
    movie: MOVIES.velvetHour,
    online: 22,
    unread: 0,
    spoilers: true,
    host: LINA,
    members: [LINA, RANA],
    preview: 'Lina: Velvet Hour again',
    rules: RULES(true),
    messages: [
      { id: 'c0', kind: 'system', text: 'Yesterday' },
      { id: 'c1', kind: 'message', from: RANA, time: '9:10 PM', text: 'Rainy evening, what are we rewatching?' },
      { id: 'c2', kind: 'message', from: LINA, time: '9:12 PM', text: 'Velvet Hour again. No notes.', agree: 7 },
    ],
  },
  {
    id: 'horror-night',
    name: 'Horror Night Crew',
    movie: MOVIES.redSignal,
    online: 41,
    unread: 0,
    spoilers: false,
    host: SAM,
    members: [SAM, OMAR, JAD],
    preview: "Sam: who's in for Friday",
    rules: RULES(false),
    messages: [
      { id: 'h0', kind: 'system', text: 'Today' },
      { id: 'h1', kind: 'message', from: SAM, time: '5:20 PM', text: "Who's in for Friday? Lina is hosting Friday Frights.", agree: 5 },
    ],
  },
]

/** "#neon-tide-ending" for the composer placeholder. */
export const channelName = (room: Room) => `#${(room.short ?? room.name).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')}`

export const EMOJIS = ['😂', '😭', '🔥', '👏', '🍿', '🎬']

/** "8:45 PM" */
export const clockTime = (date = new Date()) => date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
