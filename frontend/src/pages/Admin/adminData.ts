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
