/** The `?account_deletion=` flag the API adds when an account-deletion link sends someone back to the app. */
export type AccountDeletionStatus = 'deleted' | 'cancelled' | 'invalid' | 'expired'

const NOTICES: Record<AccountDeletionStatus, string> = {
  deleted: 'Your account was deleted. Thanks for watching with us.',
  cancelled: 'The account deletion was cancelled. Your account stays as it is.',
  invalid: 'That deletion link is no longer valid. It may have been used, replaced by a newer one, cancelled, or voided by a password change.',
  expired: 'That deletion link has expired. Ask for a new one with Delete account.',
}

/** The message for a returning account-deletion link, or null when the flag is missing or unknown. */
export function accountDeletionNotice(status: string | null): string | null {
  return status !== null && Object.hasOwn(NOTICES, status) ? NOTICES[status as AccountDeletionStatus] : null
}

/**
 * What the confirm dialog says before deleting. Accounts whose password proves nothing (`deletion_by_email`)
 * confirm from their inbox instead of typing a password.
 */
export function deleteAccountPrompt(byEmail: boolean, email: string): string {
  const consequences = 'Your reviews, lists and history will be removed. This cannot be undone.'
  return byEmail
    ? `Delete your WatchWise account? We'll email you a confirmation link at ${email}; your account is deleted once you open it. ${consequences}`
    : `Delete your WatchWise account? ${consequences}`
}

/** What Settings shows while a deletion link mailed to the account is still valid. */
export function pendingDeletionMessage(email: string): string {
  return `We sent a link to ${email} to confirm deleting your account. Nothing is deleted until you open it; the link expires in an hour.`
}
