import { api, useApi } from './api.ts'

// Typed client for /api/admin/* (the final backend contract).

export type AdminPageMeta = { current_page: number; last_page: number; per_page: number; total: number }
export type AdminPage<T> = { data: T[]; meta: AdminPageMeta }

// ---------- Stats ----------

export type AdminRankedMovie = {
  tmdb_id: number
  title: string | null
  poster_path: string | null
  count: number
  average: number | null
}
export type AdminGenreShare = { id: number; name: string; count: number; share: number }
export type AdminActivityPoint = {
  date: string
  signups: number
  ratings: number
  reviews: number
  lists: number
  watches: number
  messages: number
}
export type AdminRangeKey = '7' | '30' | '90' | 'year'
export type AdminStats = {
  users_total: number
  new_users: { last_7_days: number; last_30_days: number }
  reviews_total: number
  reviews_pending: number
  flagged_reviews: number
  new_reviews: { last_7_days: number; last_30_days: number }
  active_today: number
  active_yesterday: number
  /** The chosen window; `previous_*` count the equally long window before it. */
  range: {
    name: string
    days: number
    new_users: number
    previous_new_users: number
    new_reviews: number
    previous_new_reviews: number
  }
  most_watched: AdminRankedMovie[]
  top_rated: AdminRankedMovie[]
  popular_genres: AdminGenreShare[]
  activity: AdminActivityPoint[]
}

// ---------- Users ----------

export type AdminUserRole = 'user' | 'moderator' | 'admin'

/** Role checks for the admin area: admins can do everything, moderators only read and suspend plain users. */
export const isAdminRole = (role: string | undefined) => role === 'admin'
export const isModeratorRole = (role: string | undefined) => role === 'moderator'

/** Whether the viewer may suspend or reinstate this account (moderators: plain users only, never themselves). */
export function canSuspendUser(viewer: { id: number; role: string } | null | undefined, target: Pick<AdminUser, 'id' | 'role'>) {
  if (!viewer || viewer.id === target.id) return false
  if (isAdminRole(viewer.role)) return true
  return isModeratorRole(viewer.role) && target.role === 'user'
}

export type AdminUserStatus ='active' | 'unverified' | 'inactive'
export type AdminUser = {
  id: number
  name: string
  username: string | null
  email: string
  avatar: string | null
  role: AdminUserRole
  is_active: boolean
  status: AdminUserStatus
  reviews_count: number
  email_verified_at: string | null
  last_active_at: string | null
  created_at: string | null
}
export type AdminUserSummary = {
  total: number
  active: number
  unverified: number
  inactive: number
  admins: number
  moderators: number
}
export type AdminUserPage = AdminPage<AdminUser> & { summary: AdminUserSummary }
export type AdminUserFilters = {
  q?: string
  role?: AdminUserRole
  status?: AdminUserStatus
  page?: number
  per_page?: number
}
export type AdminUserChanges = { role?: AdminUserRole; is_active?: boolean }

// ---------- Reviews ----------

export type AdminReviewStatus = 'pending' | 'approved' | 'flagged' | 'removed'
export type AdminReviewSentiment = 'positive' | 'neutral' | 'negative'
export type AdminReview = {
  id: number
  tmdb_id: number
  media_type: string
  content: string | null
  rating: number
  sentiment: AdminReviewSentiment | null
  has_spoilers: boolean
  is_approved: boolean
  status: AdminReviewStatus
  reports_count: number
  reasons: string[]
  author: { id: number | null; name: string | null; username: string | null }
  movie: { tmdb_id: number; title: string | null; poster_path: string | null; release_date: string | null }
  created_at: string | null
}
export type AdminReviewSummary = {
  flagged: number
  removed: number
  sentiment_total: number
  sentiment: Record<AdminReviewSentiment, { count: number; percent: number }>
}
export type AdminReviewPage = AdminPage<AdminReview> & { summary: AdminReviewSummary }
export type AdminReviewFilters = { status?: AdminReviewStatus; page?: number; per_page?: number }

// ---------- Movies ----------

export type AdminMovie = {
  tmdb_id: number
  title: string
  overview: string | null
  poster_path: string | null
  release_date: string | null
  runtime: number | null
  director: string | null
  genre_ids: number[]
  is_hidden: boolean
  is_featured: boolean
  is_edited: boolean
  synced_at: string | null
  edited_at: string | null
}
export type AdminMovieSync = { cached: number; overrides: number; last_synced_at: string | null }
export type AdminMoviePage = AdminPage<AdminMovie> & { summary: { sync: AdminMovieSync } }
export type AdminMovieFilters = { q?: string; page?: number; per_page?: number }
export type AdminMovieChanges = {
  title?: string
  overview?: string | null
  director?: string | null
  runtime?: number | null
  release_date?: string | null
  genre_ids?: number[]
  is_hidden?: boolean
  is_featured?: boolean
}

// ---------- Requests ----------

function withQuery(path: string, params: Record<string, string | number | undefined>) {
  const search = new URLSearchParams()
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== '') search.set(key, String(value))
  }
  const text = search.toString()
  return text ? `${path}?${text}` : path
}

export const useAdminStats = (range: AdminRangeKey = '30') => useApi<AdminStats>(withQuery('/admin/stats', { range }))
export const useAdminUsers = (filters: AdminUserFilters) => useApi<AdminUserPage>(withQuery('/admin/users', filters))
export const useAdminReviews = (filters: AdminReviewFilters) => useApi<AdminReviewPage>(withQuery('/admin/reviews', filters))
export const useAdminMovies = (filters: AdminMovieFilters) => useApi<AdminMoviePage>(withQuery('/admin/movies', filters))

export const adminApi = {
  updateUser: (id: number, changes: AdminUserChanges) =>
    api.patch<{ user: AdminUser }>(`/admin/users/${id}`, changes).then((response) => response.data.user),
  deleteUser: (id: number) => api.delete(`/admin/users/${id}`).then(() => undefined),
  moderateReview: (id: number, status: 'approved' | 'rejected') =>
    api.patch<{ review: AdminReview }>(`/admin/reviews/${id}`, { status }).then((response) => response.data.review),
  deleteReview: (id: number) => api.delete(`/admin/reviews/${id}`).then(() => undefined),
  updateMovie: (tmdbId: number, changes: AdminMovieChanges) =>
    api.patch<{ movie: AdminMovie }>(`/admin/movies/${tmdbId}`, changes).then((response) => response.data.movie),
}

/** A TMDB poster thumbnail, or null when the movie has none. */
export const adminPosterUrl = (path: string | null | undefined) => (path ? `https://image.tmdb.org/t/p/w154${path}` : null)

/** TMDB movie genres (id and name), for naming the `genre_ids` of a catalogue movie. */
export const useMovieGenres = () => useApi<{ genres: { id: number; name: string }[] }>('/genres')
