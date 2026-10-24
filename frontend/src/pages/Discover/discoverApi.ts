import { api } from '../../lib/api.ts'
import type { Movie } from '../../components/MovieTile/MovieTile.tsx'
import type { Filters } from './discoverData.ts'

/** A movie or TV show as the backend's TMDB endpoints return it. */
export type TmdbMovie = {
  id: number
  media_type: 'movie' | 'tv'
  title: string
  overview: string
  release_date: string | null
  year: number | null
  rating: number
  vote_count: number
  genre_ids: number[]
  poster_url: string | null
  backdrop_url: string | null
}

export type MoviePage = {
  page: number
  total_pages: number
  total_results: number
  movies: TmdbMovie[]
}

/** TMDB genre names the design shortens. */
const GENRE_LABELS: Record<string, string> = {
  'Science Fiction': 'Sci-fi',
  'Sci-Fi & Fantasy': 'Sci-fi',
  'Action & Adventure': 'Adventure',
}

/** A TMDB genre name as the design writes it ("Science Fiction" → "Sci-fi"). */
export const genreLabel = (name: string) => GENRE_LABELS[name] ?? name

/**
 * TMDB's ids for the design's genre chips. They never change, so filtering by genre keeps working
 * even when /api/genres is slow or down (that list is only needed for the names on the tiles).
 */
export const GENRE_IDS: Record<string, number> = {
  'Sci-fi': 878,
  Adventure: 12,
  Drama: 18,
  Comedy: 35,
  Thriller: 53,
  Horror: 27,
  Animation: 16,
  Romance: 10749,
}

/** TMDB's TV genres are a different list: no Horror or Romance, and Sci-fi / Adventure are combined genres. */
export const TV_GENRE_IDS: Record<string, number> = {
  'Sci-fi': 10765,
  Adventure: 10759,
  Drama: 18,
  Comedy: 35,
  Thriller: 9648,
  Animation: 16,
}

const RUNTIME_PARAMS: Record<string, { runtime_min?: number; runtime_max?: number }> = {
  '< 90m': { runtime_max: 89 },
  '90–120m': { runtime_min: 90, runtime_max: 120 },
  '> 2h': { runtime_min: 121 },
}

/** "Any language" has no code, so no language filter is sent. */
const LANGUAGE_CODES: Record<string, string> = {
  English: 'en',
  Arabic: 'ar',
  French: 'fr',
  Spanish: 'es',
  Korean: 'ko',
  Japanese: 'ja',
}

const SORT_PARAMS: Record<string, string> = {
  Popularity: 'popularity',
  Rating: 'rating',
  'Release date': 'release_date',
  Title: 'title',
}

/** Shown on tiles while the poster loads, and for movies TMDB has no poster for. */
const FALLBACK_GRADIENT: Movie['gradient'] = ['#ccbfff', '#8c66d9', '#331a66']

let genresRequest: Promise<Map<number, string>> | null = null

/** Genre id → display name (e.g. 878 → "Sci-fi"), for movies and TV shows. Fetched once and shared while the app is open. */
export function loadGenres(): Promise<Map<number, string>> {
  const names = (type: 'movie' | 'tv') =>
    api.get<{ genres: { id: number; name: string }[] }>('/genres', { params: { type } }).then(({ data }) => data.genres)

  genresRequest ??= Promise.all([names('movie'), names('tv')])
    .then((lists) => new Map(lists.flat().map((g) => [g.id, genreLabel(g.name)])))
    .catch((error: unknown) => {
      // Let the next call try again instead of keeping the failure.
      genresRequest = null
      throw error
    })
  return genresRequest
}

type MovieQuery = {
  /** Title search. When set, TMDB can't also apply the filters or sort. */
  query: string
  filters: Filters
  sort: string
  page: number
  signal: AbortSignal
}

/** The ids TMDB knows for the selected genre chips; chips TMDB has no id for are skipped. */
const idsFor = (labels: string[], ids: Record<string, number>) =>
  labels.map((label) => ids[label]).filter((id) => id !== undefined)

/** One page of results: a title search, or Discover's filtered browse, over movies, TV shows or both. */
export async function fetchMovies({ query, filters, sort, page, signal }: MovieQuery): Promise<MoviePage> {
  if (query) {
    const { data } = await api.get<MoviePage>('/movies/search', {
      params: { q: query, page, type: filters.mediaType },
      signal,
    })
    return data
  }

  // TV has no Horror or Romance, so when one is selected only movies can match.
  const tvCantMatch = filters.genres.some((label) => !(label in TV_GENRE_IDS))
  const type = filters.mediaType === 'all' && tvCantMatch ? 'movie' : filters.mediaType

  const movieGenres = idsFor(filters.genres, GENRE_IDS)
  const tvGenres = idsFor(filters.genres, TV_GENRE_IDS)

  const { data } = await api.get<MoviePage>('/movies/discover', {
    params: {
      type,
      // TV genre ids differ from movie ones: a TV-only browse sends those as `genres`, a combined one sends both.
      genres: (type === 'tv' ? tvGenres : movieGenres).join(',') || undefined,
      tv_genres: type === 'all' ? tvGenres.join(',') || undefined : undefined,
      year_from: filters.years[0],
      year_to: filters.years[1],
      min_rating: filters.minRating || undefined,
      ...(filters.runtime ? RUNTIME_PARAMS[filters.runtime] : {}),
      language: LANGUAGE_CODES[filters.language],
      sort: SORT_PARAMS[sort],
      page,
    },
    signal,
  })
  return data
}

/** "Neon Tide" → ["NEON", "TIDE"], for the gradient tile when there is no poster. */
function posterTitle(title: string): [string, string] {
  const words = title.toUpperCase().split(/\s+/)
  const middle = Math.ceil(words.length / 2)
  return [words.slice(0, middle).join(' '), words.slice(middle).join(' ')]
}

/** Shapes a TMDB movie or TV show like the design's mock movies, so MovieTile and the list view can show it. */
export function toTileMovie(movie: TmdbMovie, genres: Map<number, string>): Movie {
  const genreNames = movie.genre_ids.map((id) => genres.get(id)).filter(Boolean).slice(0, 2)
  return {
    // TV ids overlap movie ids, so a show's slug says what it is ("tv-1399"); the details page reads it back.
    slug: movie.media_type === 'tv' ? `tv-${movie.id}` : String(movie.id),
    title: movie.title,
    posterTitle: posterTitle(movie.title),
    meta: [movie.year, genreNames.join(', ')].filter(Boolean).join(' · '),
    // Unreleased films have no votes yet; a dash reads better than "0.0".
    score: movie.vote_count ? movie.rating.toFixed(1) : '–',
    gradient: FALLBACK_GRADIENT,
    posterUrl: movie.poster_url ?? undefined,
  }
}
