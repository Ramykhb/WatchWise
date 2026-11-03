import type { Movie } from '../components/MovieTile/MovieTile.tsx'
/** A neutral placeholder until the title's TMDB details load. Every numeric ID belongs to TMDB. */
export function movieFor(tmdbId: number): Movie {
  return {
    slug: String(tmdbId),
    title: `Film #${tmdbId}`,
    posterTitle: ['FILM', `#${tmdbId}`],
    meta: 'Details unavailable',
    score: '–',
    gradient: ['#d9d2c5', '#9a8f80', '#3d362e'],
  }
}

export function tmdbIdOf(movie: Movie): number {
  return Number(movie.slug)
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
