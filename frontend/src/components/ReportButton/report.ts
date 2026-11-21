export type ReportTarget =
  | { type: 'review' | 'list' | 'room'; id: number }
  | { type: 'message'; id: number; roomId: number }
  | { type: 'profile'; username: string }

export const REPORT_REASONS = {
  review: ['Spam', 'Links', 'Spoilers', 'Offensive language'],
  message: ['spam', 'harassment', 'hate', 'spoilers', 'other'],
  list: ['spam', 'harassment', 'inappropriate', 'other'],
  room: ['spam', 'harassment', 'inappropriate', 'other'],
  profile: ['spam', 'harassment', 'inappropriate', 'other'],
} satisfies Record<ReportTarget['type'], string[]>

export function reportEndpoint(target: ReportTarget): string {
  switch (target.type) {
    case 'profile': return `/users/${encodeURIComponent(target.username)}/report`
    case 'message': return `/rooms/${target.roomId}/messages/${target.id}/report`
    case 'review': return `/reviews/${target.id}/report`
    case 'list': return `/lists/${target.id}/report`
    case 'room': return `/rooms/${target.id}/report`
  }
}

export function reportPayload(type: ReportTarget['type'], reason: string, details: string) {
  if (!(REPORT_REASONS[type] as string[]).includes(reason)) throw new Error('Choose a report reason.')
  if (details.trim().length > 500) throw new Error('Use at most 500 characters for details.')
  return type === 'review' ? { reason } : { reason, details: details.trim() || null }
}
