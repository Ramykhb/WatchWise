import type { Movie } from '../../components/MovieTile/MovieTile.tsx'
import { MOVIES } from '../Home/homeData.ts'

// Mock content from the Figma "Watchlist & History" frame. Replace with the user's watchlist and
// watch history from the API once auth is connected.

export type Availability = 'cinemas' | 'streaming' | 'soon'

export type QueueItem = {
  movie: Movie
  details: string
  runtimeMinutes: number
  availability: Availability
  availabilityLabel: string
  added: string
  /** Higher = added more recently (for "Recently added"). */
  addedOrder: number
  watched: boolean
}

export type HistoryEntry = { month: string; date: string; movie: Movie; rating: number }

export const TOTALS = { toWatch: 18, watched: 142, queuedHours: 38 }

export const FILTERS: { id: Availability | 'all'; label: string }[] = [
  { id: 'all', label: 'All' },
  { id: 'cinemas', label: 'In cinemas' },
  { id: 'streaming', label: 'Streaming' },
  { id: 'soon', label: 'Coming soon' },
]

export const QUEUE: QueueItem[] = [
  { movie: MOVIES.deepBlue, details: '2025 · Thriller · 1h 58m', runtimeMinutes: 118, availability: 'cinemas', availabilityLabel: 'In cinemas', added: 'Added 2 days ago', addedOrder: 5, watched: false },
  { movie: MOVIES.stardust, details: '2025 · Fantasy · 2h 06m', runtimeMinutes: 126, availability: 'streaming', availabilityLabel: 'Streaming', added: 'Added 5 days ago', addedOrder: 4, watched: false },
  { movie: MOVIES.velvetHour, details: '2026 · Romance · 1h 49m', runtimeMinutes: 109, availability: 'streaming', availabilityLabel: 'Streaming', added: 'Added Mar 1', addedOrder: 3, watched: false },
  { movie: MOVIES.wildFern, details: '2024 · Adventure · 2h 11m', runtimeMinutes: 131, availability: 'streaming', availabilityLabel: 'Streaming', added: 'Added Feb 24', addedOrder: 2, watched: true },
  { movie: MOVIES.redSignal, details: '2026 · Action · 1h 54m', runtimeMinutes: 114, availability: 'soon', availabilityLabel: 'Coming Apr 2', added: 'Added Feb 20', addedOrder: 1, watched: false },
]

export const HISTORY: HistoryEntry[] = [
  { month: 'MARCH 2026', date: 'Mar 9', movie: MOVIES.neonTide, rating: 5 },
  { month: 'MARCH 2026', date: 'Mar 6', movie: MOVIES.paperMoon, rating: 4 },
  { month: 'MARCH 2026', date: 'Mar 2', movie: MOVIES.lastLap, rating: 4 },
  { month: 'FEBRUARY 2026', date: 'Feb 26', movie: MOVIES.wildFern, rating: 3 },
  { month: 'FEBRUARY 2026', date: 'Feb 18', movie: MOVIES.stardust, rating: 5 },
]

export const TASTE = [
  { genre: 'Sci-fi', percent: 38, color: 'var(--accent-coral)' },
  { genre: 'Drama', percent: 27, color: 'var(--accent-yellow)' },
  { genre: 'Thriller', percent: 18, color: 'var(--accent-mint)' },
  { genre: 'Comedy', percent: 11, color: 'var(--surface-violet-tint)' },
]
