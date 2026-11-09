/** The `?email_change=` flag the API adds when the confirmation link sends someone back here. */
export type EmailChangeStatus = 'success' | 'invalid' | 'expired' | 'taken'

const NOTICES: Record<EmailChangeStatus, string> = {
  success: 'Your new email is confirmed. Use it the next time you sign in.',
  invalid: 'That confirmation link is no longer valid. It may have been replaced by a newer one or cancelled.',
  expired: 'That confirmation link has expired. Send a new one below.',
  taken: 'That email now belongs to another account, so the change was cancelled.',
}

/** The message for a returning confirmation link, or null when the flag is missing or unknown. */
export function emailChangeNotice(status: string | null): string | null {
  return status !== null && Object.hasOwn(NOTICES, status) ? NOTICES[status as EmailChangeStatus] : null
}
