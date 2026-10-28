import type { Movie } from '../components/MovieTile/MovieTile.tsx'
import { MOVIES } from '../pages/Home/homeData.ts'

// The database stores films as TMDB ids. Until the TMDB integration is merged, ids 1001–1008 stand
// for the eight demo movies (the backend's DemoSocialSeeder uses the same ids); any other id gets a
// neutral placeholder poster so real ids still render.
// TODO: replace with movie details from the backend's TMDB endpoints.

const DEMO_IDS: Record<string, number> = {
  'neon-tide': 1001,
  stardust: 1002,
  'deep-blue': 1003,
  'last-lap': 1004,
  'velvet-hour': 1005,
  'wild-fern': 1006,
  'paper-moon': 1007,
  'red-signal': 1008,
}

const BY_ID = new Map<number, Movie>(Object.values(MOVIES).map((movie) => [DEMO_IDS[movie.slug], movie]))

/** Movies the pickers can offer until TMDB search exists. */
export const PICKABLE_MOVIES: Movie[] = Object.values(MOVIES)

export function movieFor(tmdbId: number): Movie {
  return (
    BY_ID.get(tmdbId) ?? {
      slug: String(tmdbId),
      title: `Film #${tmdbId}`,
      posterTitle: ['FILM', `#${tmdbId}`],
      meta: 'Details coming soon',
      score: '–',
      gradient: ['#d9d2c5', '#9a8f80', '#3d362e'],
    }
  )
}

/** True for the eight demo movies' ids (1001–1008), which have no TMDB details to load. */
export const isDemoId = (tmdbId: number) => BY_ID.has(tmdbId)

export function tmdbIdOf(movie: Movie): number {
  return DEMO_IDS[movie.slug] ?? Number(movie.slug)
}

export type MediaType = 'movie' | 'tv'

/**
 * The TMDB id and type of a title. TV shows have "tv-1399" slugs, since TMDB numbers shows and movies
 * separately (1399 is both); null when the slug names neither.
 */
export function titleOf(movie: Movie): { tmdbId: number; mediaType: MediaType } | null {
  const isTv = movie.slug.startsWith('tv-')
  const tmdbId = isTv ? Number(movie.slug.slice('tv-'.length)) : tmdbIdOf(movie)
  return Number.isInteger(tmdbId) && tmdbId > 0 ? { tmdbId, mediaType: isTv ? 'tv' : 'movie' } : null
}

/** A title as lists save it: 550 for a movie, "tv-1399" for a TV show (the same "tv-" form as the slugs). */
export type FilmKey = number | string

export function filmKeyOf(movie: Movie): FilmKey {
  const title = titleOf(movie)
  return title?.mediaType === 'tv' ? `tv-${title.tmdbId}` : tmdbIdOf(movie)
}

/** The key of a saved title from the API (a list item, a cover entry). */
export const itemKey = (item: { tmdb_id: number; media_type?: MediaType }): FilmKey =>
  item.media_type === 'tv' ? `tv-${item.tmdb_id}` : item.tmdb_id

/** `movieFor` for any key: a TV show's placeholder keeps its "tv-" slug so it links to the show. */
export function movieForKey(key: FilmKey): Movie {
  if (typeof key === 'number') return movieFor(key)
  if (!key.startsWith('tv-')) return movieFor(Number(key))
  const tmdbId = Number(key.slice('tv-'.length))
  return { ...movieFor(tmdbId), slug: key, title: `Show #${tmdbId}`, posterTitle: ['SHOW', `#${tmdbId}`] }
}
