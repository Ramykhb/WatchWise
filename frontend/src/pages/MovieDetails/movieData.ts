import type { Movie } from '../../components/MovieTile/MovieTile.tsx'
import type { Review } from '../../components/ReviewCard/ReviewCard.tsx'
import { MOVIES, type Gradient } from '../Home/homeData.ts'

// Mock content from the Figma "Movie Details — Neon Tide" frame. The design's mock movies (/movies/neon-tide)
// still use it; TMDB movies (/movies/438631) load their details through movieApi.ts.

/** `slug` is empty for people the Person page can't open yet; `photoUrl` replaces the initials. */
export type CastMember = { slug: string; initials: string; name: string; role: string; gradient: Gradient; photoUrl?: string }

export type Cinema = { name: string; distance: string; times: string[] }

/**
 * WatchWise's own data about a movie: reviews and ratings, premiere, showtimes and the live room.
 * No API serves it yet, so only the design's mock movies have it; TMDB movies leave it out and
 * the page hides those sections rather than show sample data.
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

const NEON_TIDE: Omit<MovieDetails, 'movie' | 'similar' | 'community'> & Community = {
  tags: [{ label: '#1 Trending', highlight: true }, { label: 'Sci-fi' }, { label: 'Adventure' }, { label: 'PG-13' }],
  year: '2026',
  runtime: '2h 14m',
  language: 'English',
  director: 'Lena Okafor',
  ratingCount: '12.4k WatchWise ratings',
  tmdbScore: '8.1 / 10',
  overview:
    'When a marine biologist discovers a glowing current that rewrites memories, she must choose between the past she lost and the future the ocean is offering. A luminous, big-hearted adventure about grief, wonder and the tides that carry us home.',
  cast: [
    { slug: 'lena-okafor', initials: 'LO', name: 'Lena Okafor', role: 'Director', gradient: ['#fa8cbf', '#9940b2', '#2b1a63'] },
    { slug: 'amara-mensah', initials: 'AM', name: 'Amara Mensah', role: 'Dr. Ines Vale', gradient: ['#7de0c2', '#338cb2', '#211a5c'] },
    { slug: 'jonah-reyes', initials: 'JR', name: 'Jonah Reyes', role: 'Kai', gradient: ['#8cccff', '#4066d9', '#1a144d'] },
    { slug: 'sofia-park', initials: 'SP', name: 'Sofia Park', role: 'Mara', gradient: ['#ffe073', '#ff8c59', '#99264d'] },
    { slug: 'dev-bhatt', initials: 'DB', name: 'Dev Bhatt', role: 'Captain Ruiz', gradient: ['#bfeb8c', '#4d9973', '#143338'] },
    { slug: 'elena-lind', initials: 'EL', name: 'Elena Lind', role: 'Young Ines', gradient: ['#ccbfff', '#8c66d9', '#331a66'] },
  ],
  trailer: { label: 'Official trailer · 2:31', gradient: ['#ccbfff', '#8c66d9', '#331a66'] },
  reviewSummary: {
    average: '4.6',
    count: '1,284 reviews',
    distribution: [72, 18, 6, 3, 1],
    sentiment: { positive: 68, neutral: 22, negative: 10 },
  },
  yourReview: {
    id: 'you',
    initials: 'MR',
    color: 'var(--accent-mint)',
    name: 'Maya Rivera (you)',
    date: 'Mar 8, 2026',
    rating: 5,
    sentiment: 'positive',
    body: 'A gorgeous, big-hearted sci-fi ride. The ocean sequences are stunning and the soundtrack stays with you for days.',
    helpful: 24,
  },
  reviews: [
    {
      id: 'omar',
      initials: 'OK',
      color: 'var(--accent-coral)',
      name: 'Omar Khalil',
      date: 'Mar 7, 2026',
      rating: 3,
      sentiment: 'neutral',
      body: 'Beautiful to look at, but the middle act drifts. Worth seeing on the biggest screen you can find.',
      helpful: 9,
    },
    {
      id: 'rana',
      initials: 'RH',
      color: 'var(--accent-yellow)',
      name: 'Rana Haddad',
      date: 'Mar 6, 2026',
      rating: 2,
      sentiment: 'negative',
      body: "Felt manipulative and way too long. The visuals can't save a script this thin.",
      helpful: 3,
    },
  ],
  facts: [
    { label: 'Release date', value: 'March 6, 2026' },
    { label: 'Runtime', value: '2h 14m' },
    { label: 'Genres', value: 'Sci-fi, Adventure' },
    { label: 'Director', value: 'Lena Okafor' },
    { label: 'Original language', value: 'English' },
    { label: 'Budget', value: '$85M' },
  ],
  premiere: {
    title: 'Streaming premiere',
    when: 'Sat, Apr 18, 2026 · 8:00 PM',
    start: '20260418T200000',
    end: '20260418T221400',
  },
  city: 'Beirut',
  cinemas: [
    { name: 'Grand Cinemas ABC', distance: '1.2 km', times: ['6:15', '8:40', '10:20'] },
    { name: 'VOX Cinemas City Centre', distance: '3.4 km', times: ['7:00', '9:30'] },
    { name: 'Empire Sodeco', distance: '4.1 km', times: ['8:15'] },
  ],
  room: {
    title: 'ending explained',
    text: '128 fans are discussing right now. Spoilers welcome.',
    guests: [
      { initials: 'JN', color: 'var(--accent-mint)' },
      { initials: 'LS', color: 'var(--accent-coral)' },
      { initials: 'OK', color: 'var(--accent-yellow)' },
    ],
    extra: 125,
  },
}

// Order of the Figma "More like this" row.
const SIMILAR = [
  MOVIES.stardust,
  MOVIES.deepBlue,
  MOVIES.lastLap,
  MOVIES.wildFern,
  MOVIES.velvetHour,
  MOVIES.paperMoon,
  MOVIES.neonTide,
]

const BY_SLUG = new Map<string, Movie>(Object.values(MOVIES).map((movie) => [movie.slug, movie]))

/** Details for a movie slug, or undefined when the slug is unknown. */
export function getMovieDetails(slug: string | undefined): MovieDetails | undefined {
  const movie = slug ? BY_SLUG.get(slug) : undefined
  if (!movie) return undefined
  const { ratingCount, reviewSummary, reviews, yourReview, premiere, city, cinemas, room, ...core } = NEON_TIDE
  return {
    ...core,
    movie,
    similar: SIMILAR.filter((m) => m.slug !== movie.slug).slice(0, 6),
    community: { ratingCount, reviewSummary, reviews, yourReview, premiere, city, cinemas, room },
  }
}
