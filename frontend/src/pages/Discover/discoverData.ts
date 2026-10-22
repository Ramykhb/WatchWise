import type { Movie } from '../../components/MovieTile/MovieTile.tsx'
import { MOVIES } from '../Home/homeData.ts'

// Mock content from the Figma Discover frame. Replace with TMDB search results once the backend is connected.

export type SearchType = 'movies' | 'people' | 'lists'

export const SEARCH_TYPES: { id: SearchType; label: string; count: number }[] = [
  { id: 'movies', label: 'Movies', count: 248 },
  { id: 'people', label: 'People', count: 31 },
  { id: 'lists', label: 'Lists', count: 19 },
]

export const GENRES = ['Sci-fi', 'Adventure', 'Drama', 'Comedy', 'Thriller', 'Horror', 'Animation', 'Romance']

export const RUNTIMES = ['< 90m', '90–120m', '> 2h']

export const LANGUAGES = ['English', 'Arabic', 'French', 'Spanish', 'Korean', 'Japanese', 'Any language']

export const SORTS = ['Popularity', 'Rating', 'Release date', 'Title']

export const YEAR_BOUNDS = { min: 2010, max: 2030 }

export type Filters = {
  genres: string[]
  years: [number, number]
  minRating: number
  runtime: string | null
  language: string
  availability: { cinemas: boolean; streaming: boolean; comingSoon: boolean }
}

export const DEFAULT_FILTERS: Filters = {
  genres: [],
  years: [YEAR_BOUNDS.min, YEAR_BOUNDS.max],
  minRating: 0,
  runtime: null,
  language: 'English',
  availability: { cinemas: false, streaming: false, comingSoon: false },
}

/** The filters already applied in the Figma frame. */
export const INITIAL_FILTERS: Filters = {
  genres: ['Sci-fi', 'Adventure'],
  years: [2015, 2026],
  minRating: 4,
  runtime: '90–120m',
  language: 'English',
  availability: { cinemas: true, streaming: true, comingSoon: false },
}

export const RESULTS: Movie[] = [
  MOVIES.neonTide,
  MOVIES.stardust,
  MOVIES.deepBlue,
  MOVIES.wildFern,
  MOVIES.lastLap,
  MOVIES.redSignal,
  MOVIES.paperMoon,
  MOVIES.velvetHour,
  MOVIES.stardust,
  MOVIES.neonTide,
  MOVIES.wildFern,
  MOVIES.deepBlue,
]

export const TOTAL_PAGES = 21
