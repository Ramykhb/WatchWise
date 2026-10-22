import type { Movie } from '../../components/MovieTile/MovieTile.tsx'
import { MOVIES } from '../Home/homeData.ts'

// Mock content from the Figma admin frames. Replace with the admin API (stats, users, reviews, movies).

export const ADMIN_USER = { name: 'Ahmad A.', initials: 'AA', role: 'Administrator' }

export const RANGES = ['Last 7 days', 'Last 30 days', 'Last 90 days', 'This year'] as const

export const STATS = [
  { label: 'Registered users', value: '48,210', delta: '+12% this month', tone: 'up' as const },
  { label: 'Reviews', value: '126,904', delta: '+8% this month', tone: 'up' as const },
  { label: 'Active today', value: '6,382', delta: '+3% vs yesterday', tone: 'up' as const },
  { label: 'Flagged reviews', value: '37', delta: 'Needs moderation', tone: 'attention' as const },
]

export const ACTIVITY_LABELS = ['Feb 10', 'Feb 17', 'Feb 24', 'Mar 3', 'Mar 10']

export const POPULAR_GENRES = [
  { genre: 'Sci-fi', percent: 31, color: 'var(--accent-coral)' },
  { genre: 'Drama', percent: 24, color: 'var(--ground-violet)' },
  { genre: 'Thriller', percent: 17, color: 'var(--accent-yellow)' },
  { genre: 'Comedy', percent: 14, color: 'var(--accent-mint)' },
  { genre: 'Horror', percent: 8, color: 'var(--surface-violet-tint)' },
  { genre: 'Animation', percent: 6, color: 'var(--border-subtle)' },
]

export type RankedMovie = { movie: Movie; meta: string; value: string }

export const MOST_WATCHED: RankedMovie[] = [
  { movie: MOVIES.neonTide, meta: 'Sci-fi · 2026', value: '18.2k' },
  { movie: MOVIES.paperMoon, meta: 'Comedy · 2026', value: '12.7k' },
  { movie: MOVIES.deepBlue, meta: 'Thriller · 2025', value: '11.9k' },
  { movie: MOVIES.stardust, meta: 'Fantasy · 2025', value: '9.4k' },
  { movie: MOVIES.redSignal, meta: 'Action · 2026', value: '8.8k' },
]

export const HIGHEST_RATED: RankedMovie[] = [
  { movie: MOVIES.neonTide, meta: '12.4k ratings', value: '4.8' },
  { movie: MOVIES.stardust, meta: '8.1k ratings', value: '4.7' },
  { movie: MOVIES.paperMoon, meta: '9.9k ratings', value: '4.6' },
  { movie: MOVIES.lastLap, meta: '6.3k ratings', value: '4.5' },
  { movie: MOVIES.deepBlue, meta: '10.2k ratings', value: '4.4' },
]

// ---------- Users (Figma "Admin — Users") ----------

export type UserRole = 'Member' | 'Moderator' | 'Admin'
export type UserStatus = 'Active' | 'Unverified' | 'Suspended'

export type AdminUserRow = {
  id: string
  initials: string
  color: string
  name: string
  email: string
  role: UserRole
  status: UserStatus
  joined: string
  reviews: number
  lastActive: string
}

export const USER_ROLES: UserRole[] = ['Member', 'Moderator', 'Admin']
export const USER_STATUSES: UserStatus[] = ['Active', 'Unverified', 'Suspended']

/** Totals across all accounts (the table shows one page of them). */
export const USER_TOTALS = { total: 48210, active: 41002, unverified: 7080, suspended: 128, admins: 6, pages: 6888 }

export const USERS: AdminUserRow[] = [
  { id: 'u1', initials: 'MR', color: 'var(--accent-mint)', name: 'Maya Rivera', email: 'maya@watchwise.app', role: 'Member', status: 'Active', joined: 'Mar 2023', reviews: 142, lastActive: '2 min ago' },
  { id: 'u2', initials: 'LS', color: 'var(--accent-yellow)', name: 'Lina Saad', email: 'lina@gmail.com', role: 'Moderator', status: 'Active', joined: 'Jan 2023', reviews: 214, lastActive: '1 h ago' },
  { id: 'u3', initials: 'OK', color: 'var(--accent-coral)', name: 'Omar Khalil', email: 'omar.k@outlook.com', role: 'Member', status: 'Active', joined: 'Jun 2024', reviews: 88, lastActive: '3 h ago' },
  { id: 'u4', initials: 'JN', color: 'var(--accent-mint)', name: 'Jad Nassar', email: 'jadn@github.dev', role: 'Member', status: 'Unverified', joined: 'Mar 2026', reviews: 0, lastActive: 'Yesterday' },
  { id: 'u5', initials: 'RH', color: 'var(--surface-violet-tint)', name: 'Rana Haddad', email: 'rana.h@gmail.com', role: 'Member', status: 'Active', joined: 'Sep 2024', reviews: 63, lastActive: '2 days ago' },
  { id: 'u6', initials: 'SX', color: 'var(--accent-coral)', name: 'spam_bot_991', email: 'x91@tempmail.io', role: 'Member', status: 'Suspended', joined: 'Mar 2026', reviews: 27, lastActive: '5 days ago' },
  { id: 'u7', initials: 'AA', color: 'var(--accent-yellow)', name: 'Ahmad Assaf', email: 'ahmad@watchwise.app', role: 'Admin', status: 'Active', joined: 'Jan 2023', reviews: 12, lastActive: 'Now' },
]

// ---------- Reviews (Figma "Admin — Review Moderation") ----------

export type ReviewSentiment = 'positive' | 'neutral' | 'negative'
export type FlagReason = 'Spam' | 'Links' | 'Spoilers' | 'Offensive language'
export type ModerationStatus = 'flagged' | 'approved' | 'removed'

export type ModerationReview = {
  id: string
  initials: string
  color: string
  user: string
  movie: Movie
  time: string
  rating: number
  sentiment: ReviewSentiment
  text: string
  reasons: FlagReason[]
  reports: number
  status: ModerationStatus
}

export const REVIEW_SENTIMENTS: ReviewSentiment[] = ['positive', 'neutral', 'negative']
export const FLAG_REASONS: FlagReason[] = ['Spam', 'Links', 'Spoilers', 'Offensive language']

/** Totals across the whole site (the queue below is the first page of them). */
export const REVIEW_TOTALS = { flagged: 37, removed: 412 }

export const MODERATION_REVIEWS: ModerationReview[] = [
  { id: 'r1', initials: 'SX', color: 'var(--accent-coral)', user: 'spam_bot_991', movie: MOVIES.redSignal, time: '12 min ago', rating: 1, sentiment: 'negative', text: 'WORST MOVIE EVER!!! Watch it free at cheapmovies-xyz.biz instead, full HD no signup', reasons: ['Spam', 'Links'], reports: 14, status: 'flagged' },
  { id: 'r2', initials: 'DK', color: 'var(--accent-yellow)', user: 'darkknight_22', movie: MOVIES.neonTide, time: '1 h ago', rating: 2, sentiment: 'negative', text: "Can't believe she chooses the present and the lighthouse was a memory the whole time. Predictable.", reasons: ['Spoilers'], reports: 9, status: 'flagged' },
  { id: 'r3', initials: 'TM', color: 'var(--surface-violet-tint)', user: 'tomas.m', movie: MOVIES.deepBlue, time: '3 h ago', rating: 3, sentiment: 'neutral', text: 'Honestly the lead actor was ****ing awful, but the cinematography almost made up for it.', reasons: ['Offensive language'], reports: 4, status: 'flagged' },
  { id: 'r4', initials: 'MR', color: 'var(--accent-mint)', user: 'maya.r', movie: MOVIES.stardust, time: 'Yesterday', rating: 5, sentiment: 'positive', text: 'A gorgeous, gentle fantasy. The final shot of the comet over the harbour is pure magic.', reasons: ['Spoilers'], reports: 2, status: 'approved' },
  { id: 'r5', initials: 'XQ', color: 'var(--accent-coral)', user: 'promo_deals_x', movie: MOVIES.paperMoon, time: '2 days ago', rating: 1, sentiment: 'negative', text: 'Get 90% off streaming at best-deals-now.biz — limited time only!!!', reasons: ['Spam', 'Links'], reports: 21, status: 'removed' },
]

export const SENTIMENT_SHARE = [
  { type: 'positive' as const, percent: 64, count: '81,218', color: 'var(--accent-mint)' },
  { type: 'neutral' as const, percent: 24, count: '30,457', color: 'var(--surface-violet-tint)' },
  { type: 'negative' as const, percent: 12, count: '15,229', color: 'var(--accent-coral)' },
]

export const MODERATION_WEEK = { flagged: 124, removed: 58, approved: 61, avgResponse: '2h 14m' }

export const GUIDELINES = ['Spoilers must be tagged', 'No hate speech or slurs', 'No spam, ads or piracy links', 'Critique films, not people']

// ---------- Movies (Figma "Admin — Movie Data") ----------

export type MovieSource = 'TMDB' | 'Edited' | 'Local'

/** The fields an admin can change. Only the content ones (not visible / featured) make a TMDB movie "Edited". */
export type MovieFields = {
  title: string
  releaseDate: string
  runtime: number
  director: string
  genres: string[]
  description: string
  visible: boolean
  featured: boolean
}

export type AdminMovie = MovieFields & {
  id: string
  /** Set for movies cached from TMDB; `tmdb` keeps TMDB's own values for "Reset to TMDB". */
  tmdbId?: number
  tmdb?: MovieFields
  source: MovieSource
  rating: number | null
  gradient: Movie['gradient']
  poster?: string
}

export const MOVIE_GENRES = ['Action', 'Adventure', 'Animation', 'Comedy', 'Documentary', 'Drama', 'Fantasy', 'Horror', 'Romance', 'Sci-fi', 'Thriller']

export const MOVIE_SYNC = { lastSynced: '2 hours ago', cached: 12480, overrides: 34, nextSync: '4h' }

const tmdbMovie = (id: string, tmdbId: number, movie: Movie, rating: number, fields: MovieFields, overrides?: Partial<MovieFields>): AdminMovie => ({
  id,
  tmdbId,
  tmdb: fields,
  source: overrides ? 'Edited' : 'TMDB',
  rating,
  gradient: movie.gradient,
  ...fields,
  ...overrides,
})

export const ADMIN_MOVIES: AdminMovie[] = [
  tmdbMovie('m1', 81920, MOVIES.neonTide, 4.8,
    { title: 'Neon Tide', releaseDate: '2026-03-06', runtime: 134, director: 'Lena Okafor', genres: ['Sci-fi'], description: 'A marine biologist discovers a glowing current off the coast.', visible: true, featured: false },
    { genres: ['Sci-fi', 'Adventure'], description: 'When a marine biologist discovers a glowing current that rewrites memories, she has to choose which past to keep.', featured: true }),
  tmdbMovie('m2', 77341, MOVIES.paperMoon, 4.6,
    { title: 'Paper Moon', releaseDate: '2026-01-23', runtime: 102, director: 'Sami Haddad', genres: ['Comedy'], description: 'Two rival origami artists are forced to share a tiny studio.', visible: true, featured: false }),
  tmdbMovie('m3', 69012, MOVIES.deepBlue, 4.4,
    { title: 'Deep Blue', releaseDate: '2025-08-15', runtime: 118, director: 'Iris Chen', genres: ['Thriller'], description: 'A submarine crew loses contact with the surface during a routine dive.', visible: true, featured: false }),
  {
    id: 'm4', source: 'Local', rating: 4.1, gradient: ['#fa8cbf', '#9940b2', '#2b1a63'],
    title: 'Beirut Nights', releaseDate: '2026-04-10', runtime: 91, director: 'Nour Khoury', genres: ['Documentary'],
    description: 'The musicians, cooks and night-shift workers who keep the city awake.', visible: false, featured: false,
  },
  tmdbMovie('m5', 80455, MOVIES.redSignal, 4.1,
    { title: 'Red Signal', releaseDate: '2026-02-13', runtime: 114, director: 'Marco Vidal', genres: ['Action'], description: 'A train engineer races to stop a runaway freight line.', visible: false, featured: false }),
  tmdbMovie('m6', 65218, MOVIES.stardust, 4.7,
    { title: 'Stardust', releaseDate: '2025-11-21', runtime: 126, director: 'Ada Brennan', genres: ['Fantasy'], description: 'A lighthouse keeper catches a falling star.', visible: true, featured: true },
    { description: 'A lonely lighthouse keeper catches a falling star — and has one night to send it home.' }),
]
