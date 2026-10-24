// Discover's filter options. Results come from TMDB through the backend (see discoverApi.ts).

export type SearchType = 'movies' | 'people' | 'lists'

/** People and Lists aren't connected yet, so their counts are still the design's. */
export const SEARCH_TYPES: { id: SearchType; label: string; count: number }[] = [
  { id: 'movies', label: 'Movies', count: 0 },
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
