import type { Movie } from '../../components/MovieTile/MovieTile.tsx'
import type { Review } from '../../components/ReviewCard/ReviewCard.tsx'
import { type Gradient } from '../Home/homeData.ts'

/** `slug` builds the link to /people/{slug} (a TMDB person id for real movies); `photoUrl` replaces the initials. */
export type CastMember = { slug: string; initials: string; name: string; role: string; gradient: Gradient; photoUrl?: string }

export type Cinema = { name: string; distance: string; times: string[] }

/**
 * WatchWise's own data about a movie: reviews and ratings, premiere, showtimes and the live room.
 * Optional sections stay hidden until backed by application data.
 */
export type Community = {
  ratingCount: string
  reviewSummary: {
    average: string
    count: string
    /** Percent of reviews per star, 5 → 1. */
    distribution: number[]
    sentiment: { positive: number; neutral: number; negative: number }
  }
  reviews: Review[]
  yourReview: Review
  premiere: { title: string; when: string; start: string; end: string }
  city: string
  cinemas: Cinema[]
  room: { title: string; text: string; guests: { initials: string; color: string }[]; extra: number }
}

export type MovieDetails = {
  movie: Movie
  tags: { label: string; highlight?: boolean }[]
  year: string
  runtime: string
  /** For a TV show, one episode's length; missing when TMDB has none. */
  runtimeMinutes?: number | null
  /** "2026-11-20" (first air date for a TV show); missing when TMDB has none. */
  releaseDate?: string | null
  language: string
  director: string
  tmdbScore: string
  overview: string
  cast: CastMember[]
  /** With a YouTube key the trailer plays on the page; the backdrop replaces the gradient art. */
  trailer: { label: string; gradient: Gradient; youtubeKey?: string; backdropUrl?: string }
  facts: { label: string; value: string }[]
  similar: Movie[]
  community?: Community
}
