import type { AdminRangeKey, AdminUserRole } from '../../lib/adminApi.ts'

// UI constants for the admin pages. The content itself comes from the admin API (lib/adminApi.ts).

/** Date ranges of the Overview page; `key` is the `range` GET /admin/stats takes. */
export const RANGES: { label: string; key: AdminRangeKey }[] = [
  { label: 'Last 7 days', key: '7' },
  { label: 'Last 30 days', key: '30' },
  { label: 'Last 90 days', key: '90' },
  { label: 'This year', key: 'year' },
]

export type UserStatus = 'Active' | 'Unverified' | 'Suspended'

export const USER_ROLE_LABEL: Record<AdminUserRole, string> = { user: 'Member', moderator: 'Moderator', admin: 'Admin' }

export const USER_ROLES: AdminUserRole[] = ['user', 'moderator', 'admin']

export const REVIEW_SENTIMENTS = ['positive', 'neutral', 'negative'] as const

export const GUIDELINES = ['Spoilers must be tagged', 'No hate speech or slurs', 'No spam, ads or piracy links', 'Critique films, not people']
