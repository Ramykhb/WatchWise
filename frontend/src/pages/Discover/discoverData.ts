// Discover's filter options. Results come from TMDB through the backend (see discoverApi.ts).

export type SearchType = 'movies' | 'people' | 'lists'

/** Search tabs; their counts are filled from live results. */
export const SEARCH_TYPES: { id: SearchType; label: string }[] = [
  { id: 'movies', label: 'Movies & TV' },
  { id: 'people', label: 'People' },
  { id: 'lists', label: 'Lists' },
]

export const GENRES = ['Sci-fi', 'Adventure', 'Drama', 'Comedy', 'Thriller', 'Horror', 'Animation', 'Romance']

export const RUNTIMES = ['< 90m', '90–120m', '> 2h']

export const LANGUAGES = ['English', 'Arabic', 'French', 'Spanish', 'Korean', 'Japanese', 'Any language']

export const SORTS = ['Popularity', 'Rating', 'Release date', 'Title']

export const YEAR_BOUNDS = { min: 2010, max: 2030 }

/** Which kind of titles to list: both, or just one. */
export type MediaFilter = 'all' | 'movie' | 'tv'

export const MEDIA_FILTERS: { id: MediaFilter; label: string }[] = [
  { id: 'all', label: 'All' },
  { id: 'movie', label: 'Movies' },
  { id: 'tv', label: 'TV shows' },
]

export type Filters = {
  mediaType: MediaFilter
  genres: string[]
  years: [number, number]
  minRating: number
  runtime: string | null
  language: string
  availability: { cinemas: boolean; streaming: boolean; comingSoon: boolean }
}

export const DEFAULT_FILTERS: Filters = {
  mediaType: 'all',
  genres: [],
  years: [YEAR_BOUNDS.min, YEAR_BOUNDS.max],
  minRating: 0,
  runtime: null,
  language: 'English',
  availability: { cinemas: false, streaming: false, comingSoon: false },
}
