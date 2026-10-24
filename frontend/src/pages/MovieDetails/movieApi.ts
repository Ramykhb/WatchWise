import axios from 'axios'
import { api } from '../../lib/api.ts'
import { genreLabel, toTileMovie, type TmdbMovie } from '../Discover/discoverApi.ts'
import type { CastMember, MovieDetails } from './movieData.ts'

/** GET /api/movies/{id}: a movie with everything the details page needs from TMDB. */
export type TmdbMovieDetails = TmdbMovie & {
  genres: { id: number; name: string }[]
  tagline: string | null
  runtime: number | null
  status: string | null
  tmdb_score: number
  original_language: { code: string; name: string } | null
  certification: string | null
  budget: number | null
  revenue: number | null
  directors: { id: number; name: string }[]
  cast: { id: number; name: string; character: string; profile_url: string | null }[]
  trailer: { name: string; youtube_key: string; url: string; embed_url: string } | null
  similar: TmdbMovie[]
}

/** The design shows six people and six similar movies. */
const PEOPLE_SHOWN = 6
const SIMILAR_SHOWN = 6

/** Initials and a gradient for people TMDB has no photo of. */
const PERSON_GRADIENTS: CastMember['gradient'][] = [
  ['#fa8cbf', '#9940b2', '#2b1a63'],
  ['#7de0c2', '#338cb2', '#211a5c'],
  ['#8cccff', '#4066d9', '#1a144d'],
  ['#ffe073', '#ff8c59', '#99264d'],
  ['#bfeb8c', '#4d9973', '#143338'],
  ['#ccbfff', '#8c66d9', '#331a66'],
]

const initials = (name: string) =>
  name
    .split(/\s+/)
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()

/** 155 → "2h 35m". */
const formatRuntime = (minutes: number | null) =>
  minutes ? `${Math.floor(minutes / 60)}h ${String(minutes % 60).padStart(2, '0')}m` : '—'

/** 165000000 → "$165M", 1250000000 → "$1.3B". */
const formatMoney = (amount: number) => {
  if (amount >= 1e9) return `$${(amount / 1e9).toFixed(1).replace(/\.0$/, '')}B`
  if (amount >= 1e6) return `$${Math.round(amount / 1e6)}M`
  return `$${amount.toLocaleString('en-US')}`
}

/** "2021-09-15" → "September 15, 2021". */
const formatDate = (date: string) =>
  new Date(`${date}T00:00:00`).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })

/** Fetches a TMDB movie or TV show ("438631" or "tv-1399") through the backend. Resolves to null when TMDB doesn't know the id. */
export async function fetchMovieDetails(slug: string, signal: AbortSignal): Promise<TmdbMovieDetails | null> {
  const isTv = slug.startsWith('tv-')
  const id = isTv ? slug.slice('tv-'.length) : slug
  try {
    const { data } = await api.get<TmdbMovieDetails>(`/movies/${id}`, { params: isTv ? { type: 'tv' } : undefined, signal })
    return data
  } catch (error) {
    if (axios.isAxiosError(error) && error.response?.status === 404) return null
    throw error
  }
}

/**
 * Shapes the API movie for the page. `genres` (the /api/genres list) only names the genres on the
 * "More like this" tiles, so it can still be empty when the movie arrives.
 * WatchWise's own data (reviews, premiere, showtimes, room) has no API yet, so `community` is left out.
 */
export function toDetails(api: TmdbMovieDetails, genres: Map<number, string>): MovieDetails {
  // The details payload names its genres itself, so these don't wait for /api/genres.
  const genreLabels = api.genres.map((genre) => genreLabel(genre.name))
  const directorNames = api.directors.map((person) => person.name).join(', ')

  const people = [
    ...api.directors.map((person) => ({ ...person, role: 'Director', photo: null as string | null })),
    ...api.cast.map((person) => ({ id: person.id, name: person.name, role: person.character, photo: person.profile_url })),
  ]
  const cast: CastMember[] = people.slice(0, PEOPLE_SHOWN).map((person, index) => ({
    // No slug: the Person page doesn't load TMDB people yet, so these aren't links.
    slug: '',
    initials: initials(person.name),
    name: person.name,
    role: person.role,
    gradient: PERSON_GRADIENTS[index % PERSON_GRADIENTS.length],
    photoUrl: person.photo ?? undefined,
  }))

  const facts = [
    api.release_date && { label: 'Release date', value: formatDate(api.release_date) },
    api.runtime && { label: 'Runtime', value: formatRuntime(api.runtime) },
    genreLabels.length > 0 && { label: 'Genres', value: genreLabels.join(', ') },
    directorNames && {
      // A TV show's people are its creators.
      label: `${api.media_type === 'tv' ? 'Creator' : 'Director'}${api.directors.length > 1 ? 's' : ''}`,
      value: directorNames,
    },
    api.original_language && { label: 'Original language', value: api.original_language.name },
    api.budget && { label: 'Budget', value: formatMoney(api.budget) },
    api.revenue && { label: 'Box office', value: formatMoney(api.revenue) },
  ].filter((fact): fact is { label: string; value: string } => Boolean(fact))

  const tile = toTileMovie(api, genres)

  return {
    movie: tile,
    tags: [...genreLabels.map((label) => ({ label })), ...(api.certification ? [{ label: api.certification }] : [])],
    year: api.year ? String(api.year) : '—',
    runtime: formatRuntime(api.runtime),
    language: api.original_language?.name ?? '—',
    director: directorNames || 'Unknown',
    tmdbScore: api.vote_count ? `${api.tmdb_score.toFixed(1)} / 10` : 'No votes yet',
    overview: api.overview || 'No overview yet.',
    cast,
    trailer: {
      label: api.trailer?.name ?? 'No trailer yet',
      gradient: tile.gradient,
      youtubeKey: api.trailer?.youtube_key,
      backdropUrl: api.backdrop_url ?? undefined,
    },
    facts,
    similar: api.similar.slice(0, SIMILAR_SHOWN).map((movie) => toTileMovie(movie, genres)),
  }
}
