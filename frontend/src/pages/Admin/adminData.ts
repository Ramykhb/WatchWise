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
