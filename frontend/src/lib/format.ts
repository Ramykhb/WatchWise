import type { ApiMember } from './types.ts'

/** Avatar colours, picked from the user id so a member keeps the same colour everywhere. */
const AVATAR_COLORS = ['var(--accent-mint)', 'var(--accent-yellow)', 'var(--accent-coral)', 'var(--surface-violet-tint)']

export function initialsOf(name: string) {
  const parts = name.trim().split(/\s+/)
  return ((parts[0]?.[0] ?? '') + (parts.length > 1 ? parts.at(-1)![0] : '')).toUpperCase() || '?'
}

export const colorOf = (id: number) => AVATAR_COLORS[Math.abs(id) % AVATAR_COLORS.length]

/** "Lina Saad" → "Lina S." for compact rows. */
export const shortName = (name: string) => {
  const [first, ...rest] = name.trim().split(/\s+/)
  return rest.length ? `${first} ${rest.at(-1)![0]}.` : first
}

/** What the avatar circles in the UI need from a member. */
export const look = (member: ApiMember) => ({ initials: initialsOf(member.name), color: colorOf(member.id) })

const MINUTE = 60_000
const DAY = 86_400_000

/** "just now", "12 min ago", "3 h ago", "Yesterday", "Mon", "Mar 8". */
export function timeAgo(iso: string, now = Date.now()) {
  const date = new Date(iso)
  const diff = now - date.getTime()
  if (diff < MINUTE) return 'just now'
  if (diff < 60 * MINUTE) return `${Math.floor(diff / MINUTE)} min ago`
  if (diff < DAY && date.getDate() === new Date(now).getDate()) return `${Math.floor(diff / (60 * MINUTE))} h ago`
  if (diff < 2 * DAY && dayGroup(iso, now) === 'Yesterday') return 'Yesterday'
  if (diff < 7 * DAY) return date.toLocaleDateString('en-US', { weekday: 'short' })
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

/** Heading a notification sits under. */
export function dayGroup(iso: string, now = Date.now()): 'Today' | 'Yesterday' | 'This week' | 'Earlier' {
  const startOfToday = new Date(now)
  startOfToday.setHours(0, 0, 0, 0)
  const time = new Date(iso).getTime()
  if (time >= startOfToday.getTime()) return 'Today'
  if (time >= startOfToday.getTime() - DAY) return 'Yesterday'
  if (time >= startOfToday.getTime() - 6 * DAY) return 'This week'
  return 'Earlier'
}

/** "Mar 8, 2026" */
export const longDate = (iso: string) => new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })

/** "updated 2d ago" style suffix for list cards. */
export function updatedLabel(iso: string) {
  const days = Math.floor((Date.now() - new Date(iso).getTime()) / DAY)
  if (days < 1) return 'updated today'
  if (days < 7) return `updated ${days}d ago`
  if (days < 30) return `updated ${Math.floor(days / 7)}w ago`
  return `updated ${new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`
}
