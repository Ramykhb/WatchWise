import type { Movie } from '../../components/MovieTile/MovieTile.tsx'
import { timeAgo } from '../../lib/format.ts'
import { itemKey, movieForKey, type FilmKey } from '../../lib/movies.ts'
import type { ApiWatchItem } from '../../lib/types.ts'

// Turns GET /api/watchlist into the queue, history and taste bars the Watchlist page draws.
// `movieOf` gives each film's details (the page passes `useMovies`, which loads them from TMDB).
// Availability (in cinemas / streaming) is not provided. Runtimes come from full movie details.

type MovieOf = (key: FilmKey) => Movie

export type QueueItem = {
  id: number
  movie: Movie
  details: string
  runtimeMinutes: number | null
  added: string
  /** Higher = added more recently (for "Recently added"). */
  addedOrder: number
  watched: boolean
}

export type HistoryEntry = { id: number; month: string; date: string; movie: Movie; rating: number }

export const toQueueItem = (item: ApiWatchItem, movieOf: MovieOf = movieForKey): QueueItem => {
  const movie = movieOf(itemKey(item))
  const runtime = typeof movie.runtimeMinutes === 'number' && Number.isFinite(movie.runtimeMinutes) && movie.runtimeMinutes > 0 ? movie.runtimeMinutes : null
  const isShow = item.media_type === 'tv'
  return {
    id: item.id,
    movie,
    details: `${movie.meta} · ${runtime === null ? 'Runtime unavailable' : `${runtime} min${isShow ? '/episode' : ''}`}`,
    runtimeMinutes: isShow ? null : runtime,
    added: `Added ${timeAgo(item.created_at)}`,
    addedOrder: new Date(item.created_at).getTime(),
    watched: item.status === 'watched',
  }
}

/** Watched films, newest first, with the user's rating from their reviews (0 = not rated). */
export function toHistory(items: ApiWatchItem[], ratings: Record<string, number>, movieOf: MovieOf = movieForKey): HistoryEntry[] {
  return items
    .filter((item) => item.status === 'watched' && item.watched_at)
    .sort((a, b) => b.watched_at!.localeCompare(a.watched_at!))
    .map((item) => {
      const date = new Date(item.watched_at!)
      return {
        id: item.id,
        month: date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' }).toUpperCase(),
        date: date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
        movie: movieOf(itemKey(item)),
        // Ratings are keyed like titles: "550" for a movie, "tv-1399" for a show.
        rating: ratings[itemKey(item)] ?? 0,
      }
    })
}

const TASTE_COLORS = ['var(--accent-coral)', 'var(--accent-yellow)', 'var(--accent-mint)', 'var(--surface-violet-tint)']

/** Share of each genre among watched films (genre = the part after "·" in the movie meta). */
export function tasteOf(movies: Movie[]) {
  const counts = new Map<string, number>()
  for (const movie of movies) {
    const genre = movie.meta.split('·')[1]?.trim()
    if (genre) counts.set(genre, (counts.get(genre) ?? 0) + 1)
  }
  const total = [...counts.values()].reduce((sum, n) => sum + n, 0)
  return [...counts.entries()]
    .sort((a, b) => b[1] - a[1])
    .slice(0, 4)
    .map(([genre, n], index) => ({ genre, percent: Math.round((n / total) * 100), color: TASTE_COLORS[index] }))
}

/** Unknown film runtimes and TV series totals never masquerade as complete durations. */
export const compareRuntime = (a: QueueItem, b: QueueItem) => (a.runtimeMinutes ?? Number.MAX_SAFE_INTEGER) - (b.runtimeMinutes ?? Number.MAX_SAFE_INTEGER)

export function queuedRuntime(items: QueueItem[]) {
  return {
    minutes: items.reduce((sum, item) => sum + (item.runtimeMinutes ?? 0), 0),
    unknown: items.filter((item) => item.runtimeMinutes === null).length,
  }
}
