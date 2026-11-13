import { api, useApi } from './api.ts'
import type { AdminPage, AdminReview, AdminUser } from './adminApi.ts'
export type Member = { id: number; name: string; username: string | null }
export type Report = {
  id: number
  status: string
  reason: string
  details: string | null
  reporter: Member | null
  target: {
    type: string
    id: number
    label: string
    excerpt: string | null
    owner: Member | null
    removed: boolean
  }
  resolved_by: Member | null
  resolution_note: string | null
  created_at: string
  resolved_at: string | null
}
export type ContentReport = Pick<Report, 'id' | 'reason' | 'details' | 'reporter' | 'created_at'> &
  Partial<Pick<Report, 'status' | 'resolution_note'>>
export type StaffList = {
  id: number
  title: string
  description: string | null
  visibility: string
  owner: Member | null
  items_count: number
  open_reports_count: number
  created_at: string
  updated_at: string
}
export type ListDetail = StaffList & {
  items: { tmdb_id: number; media_type: string; title: string | null }[]
  reports: ContentReport[]
}
export type StaffRoom = {
  id: number
  name: string
  tmdb_id: number
  media_type: string
  film: string | null
  is_private: boolean
  is_locked: boolean
  spoilers: boolean
  creator: Member | null
  members_count: number
  messages_count: number
  open_reports_count: number
  open_message_reports_count: number
  created_at: string
  last_activity_at: string
}
export type RoomDetail = StaffRoom & {
  members: (Member & { joined_at: string })[]
  open_reports: ContentReport[]
}
export type StaffMessage = {
  id: number
  author: Member | null
  body: string
  created_at: string
  open_reports_count: number
}
export type Rating = {
  id: number
  tmdb_id: number
  media_type: string
  rating: number
  moderated: boolean
  author: Member
  film: { title: string } | null
  created_at: string
}
export type RatingFilm = {
  total: number
  average: number | null
  distribution: Record<string, number>
  with_text_count: number
  first_rated_at: string | null
  last_rated_at: string | null
  film: { title: string } | null
}
export type Burst = {
  user: Member & { created_at: string }
  count: number
  window_start: string
  window_end: string
}
export type Genre = {
  tmdb_id: number
  tmdb_name: string
  name: string
  name_override: string | null
  is_hidden: boolean
  position: number | null
  movie_count: number
  updated_at: string | null
}
export type Audit = {
  id: number
  action: string
  actor: Member & { role: string }
  subject: { type: string; id: number | null; label: string | null }
  changes: Record<string, unknown> | null
  created_at: string
}
export type UserDetail = AdminUser & {
  profile: {
    bio: string | null
    avatar: string | null
    profile_visibility: string
  }
  counts: Record<string, number>
  social_providers: string[]
  active_sessions: number
  suspension: { reason: string | null; until: string | null } | null
  notes_count: number
  recent_audit: Audit[] | null
}
export type UserNote = {
  id: number
  body: string
  author: (Member & { role: string }) | null
  created_at: string
}
export type ReviewDetail = AdminReview & {
  removed_at: string | null
  helpful_votes: number
  author: AdminReview['author'] & {
    role: string | null
    is_active: boolean | null
    reviews_with_text: number
    removed_reviews: number
    open_reports_against_their_reviews: number
  }
  reports: ContentReport[]
}
export type Announcement = {
  enabled: boolean
  message: string
  level: string
  expires_at: string | null
}
export type SettingEntry = {
  value: boolean | Announcement
  updated_at: string | null
  updated_by: Member | null
}
export type BulkResult = { id: number; status: string; reason: string | null }
export const bulkSummary = (results: BulkResult[]) =>
  results.map((r) => `#${r.id}: ${r.status}${r.reason ? ` (${r.reason.replaceAll('_', ' ')})` : ''}`).join('; ')
export const queryPath = (path: string, filters: Record<string, string | number | undefined>) => {
  const params = new URLSearchParams()
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== undefined && value !== '') params.set(key, String(value))
  })
  return `${path}${params.size ? `?${params}` : ''}`
}
export type StaffPage<T> = AdminPage<T> & { summary?: Record<string, unknown> }
export const useStaffPage = <T>(resource: string, filters: Record<string, string | number | undefined>, enabled = true) =>
  useApi<StaffPage<T>>(queryPath(`/admin/${resource}`, filters), { enabled })
export async function mutate(path: string, method: 'post' | 'patch' | 'delete', body?: unknown) {
  return (
    await api.request<{ message?: string; results?: BulkResult[] }>({
      url: `/admin/${path}`,
      method,
      data: body,
    })
  ).data
}
export const reportDestination = (target: Report['target']) => {
  if (target.removed) return null
  const paths: Record<string, string> = {
    review: 'reviews',
    profile: 'users',
    list: 'lists',
    room: 'rooms',
  }
  return paths[target.type] ? `/admin/${paths[target.type]}/${target.id}` : '/admin/rooms?reported=1'
}
