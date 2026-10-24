import axios from 'axios'
import type { Movie } from '../../components/MovieTile/MovieTile.tsx'
import type { Filters } from './discoverData.ts'

const BACKEND_URL = import.meta.env.VITE_BACKEND_URL

/** A movie as the backend's TMDB endpoints return it. */
export type TmdbMovie = {
  id: number
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
const GENRE_LABELS: Record<string, string> = { 'Science Fiction': 'Sci-fi' }

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

/** Genre id → display name (e.g. 878 → "Sci-fi"). Fetched once and shared while the app is open. */
export function loadGenres(): Promise<Map<number, string>> {
  genresRequest ??= axios
    .get<{ genres: { id: number; name: string }[] }>(`${BACKEND_URL}/api/genres`)
    .then(({ data }) => new Map(data.genres.map((g) => [g.id, genreLabel(g.name)])))
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
  /** Ids of the selected genres. */
  genreIds: number[]
  signal: AbortSignal
}

/** One page of results: a title search, or Discover's filtered browse. */
export async function fetchMovies({ query, filters, sort, page, genreIds, signal }: MovieQuery): Promise<MoviePage> {
  if (query) {
    const { data } = await axios.get<MoviePage>(`${BACKEND_URL}/api/movies/search`, { params: { q: query, page }, signal })
    return data
  }

  const { data } = await axios.get<MoviePage>(`${BACKEND_URL}/api/movies/discover`, {
    params: {
      genres: genreIds.length ? genreIds.join(',') : undefined,
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

/** Shapes a TMDB movie like the design's mock movies, so MovieTile and the list view can show it. */
export function toTileMovie(movie: TmdbMovie, genres: Map<number, string>): Movie {
  const genreNames = movie.genre_ids.map((id) => genres.get(id)).filter(Boolean).slice(0, 2)
  return {
    slug: String(movie.id),
    title: movie.title,
    posterTitle: posterTitle(movie.title),
    meta: [movie.year, genreNames.join(', ')].filter(Boolean).join(' · '),
    // Unreleased films have no votes yet; a dash reads better than "0.0".
    score: movie.vote_count ? movie.rating.toFixed(1) : '–',
    gradient: FALLBACK_GRADIENT,
    posterUrl: movie.poster_url ?? undefined,
  }
}
