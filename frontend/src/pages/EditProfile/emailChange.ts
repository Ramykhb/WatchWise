/** The `?email_change=` flag the API adds when an email-change link sends someone back here. */
export type EmailChangeStatus = 'success' | 'check-new-inbox' | 'cancelled' | 'invalid' | 'expired' | 'taken'

const NOTICES: Record<EmailChangeStatus, string> = {
  success: 'Your new email is confirmed. Use it the next time you sign in.',
  'check-new-inbox': 'Change approved. Now open the link we just sent to your new address to finish.',
  cancelled: 'The email change was cancelled. Your sign-in email stays the same.',
  invalid: 'That link is no longer valid. It may have been replaced by a newer one, cancelled, or voided by a password change.',
  expired: 'That link has expired. Send a new one below.',
  taken: 'That email now belongs to another account, so the change was cancelled.',
}

/** The message for a returning email-change link, or null when the flag is missing or unknown. */
export function emailChangeNotice(status: string | null): string | null {
  return status !== null && Object.hasOwn(NOTICES, status) ? NOTICES[status as EmailChangeStatus] : null
}

/**
 * What the pending-change box says. When the password proves nothing, the current address first approves the
 * change (`awaitingCurrent`); only then is the new address mailed.
 */
export function pendingEmailMessage(pending: string, awaitingCurrent: boolean): string {
  return awaitingCurrent
    ? `You asked to switch to ${pending}. We sent a link to your current address. Confirm it first; then we'll email the new address.`
    : `We sent a link to ${pending}. The change applies once you click it; until then, keep signing in with your current email.`
}
