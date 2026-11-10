import { accountDeletionNotice } from '../Settings/accountDeletion.ts'
import { emailChangeNotice } from '../EditProfile/emailChange.ts'

/** The links that need a click on the public page, and the cancel links that only report back. */
export type ConfirmKind = 'delete-confirm' | 'email-authorize' | 'email-confirm'
export type ResultKind = ConfirmKind | 'email-cancel' | 'delete-cancel'

const CONFIRM_PATHS: Record<ConfirmKind, string> = {
  'delete-confirm': '/me/delete/confirm',
  'email-authorize': '/me/email/authorize',
  'email-confirm': '/me/email/confirm',
}

const RESULT_KINDS: readonly string[] = ['delete-confirm', 'email-authorize', 'email-confirm', 'email-cancel', 'delete-cancel']

/** The outcome used when the request itself failed (network, server error, unknown reply); safe to retry. */
export const FAILED = 'failed'

export function isConfirmKind(value: string | undefined | null): value is ConfirmKind {
  return typeof value === 'string' && Object.hasOwn(CONFIRM_PATHS, value)
}

export function isResultKind(value: string | null | undefined): value is ResultKind {
  return typeof value === 'string' && RESULT_KINDS.includes(value)
}

export type ConfirmRequest = { path: string; query: string }

function rawParam(search: string, name: string): string | null {
  for (const part of search.replace(/^\?/, '').split('&')) {
    const eq = part.indexOf('=')
    const key = eq === -1 ? part : part.slice(0, eq)
    if (key === name) return eq === -1 ? '' : part.slice(eq + 1)
  }
  return null
}

/**
 * The POST target for a confirm link, or null when the link is incomplete. The signed `expires` and `signature`
 * are copied from the address bar untouched (no decode/encode round trip) so the signature still matches.
 */
export function buildConfirmRequest(kind: string | undefined, search: string): ConfirmRequest | null {
  if (!isConfirmKind(kind)) return null
  const id = rawParam(search, 'id')
  const hash = rawParam(search, 'hash')
  const expires = rawParam(search, 'expires')
  const signature = rawParam(search, 'signature')
  // Only plain digits and lowercase hex ever reach the path, so a crafted link cannot aim the POST at another endpoint.
  if (id === null || hash === null || expires === null || signature === null) return null
  if (!/^\d+$/.test(id) || !/^[a-f0-9]{64}$/.test(hash) || !/^\d+$/.test(expires) || !/^[a-f0-9]{64}$/.test(signature)) return null
  return { path: `${CONFIRM_PATHS[kind]}/${id}/${hash}`, query: `expires=${expires}&signature=${signature}` }
}

/** Path plus query string, relative to the API base URL. */
export function confirmRequestUrl(request: ConfirmRequest): string {
  return `${request.path}?${request.query}`
}

/** Where the page replaces its signed address once the POST is answered, so the signature leaves the URL and history. */
export function resultPath(kind: string, outcome: string): string {
  return `/account-link/result?kind=${encodeURIComponent(kind)}&outcome=${encodeURIComponent(outcome)}`
}

/** Turns a response (or failure) into an outcome string; anything unexpected is a retryable `failed`. */
export function outcomeFromResponse(status: number | undefined, data: unknown): string {
  const outcome = (data as { outcome?: unknown } | null | undefined)?.outcome
  if (status === 403) return 'invalid'
  if (status === 410) return 'expired'
  if (status === 200 && typeof outcome === 'string') return outcome
  return FAILED
}

export type Resolution = {
  title: string
  body: string
  /** `retry` stays on the confirm page; the others name where the next step leads. */
  next: 'signin' | 'edit-profile' | 'settings' | 'landing' | 'retry'
  nextLabel: string
  /** True when every session of the account was ended, so the app should forget any local sign-in. */
  endsSession?: boolean
}

const INVALID_EMAIL = 'This link is no longer valid. It may have been replaced by a newer one, cancelled, used already, or voided by a password change.'
const TAKEN = 'That email now belongs to another account, so the change was cancelled. Your sign-in email stays the same.'
const toEdit = { next: 'edit-profile', nextLabel: 'Go to Edit profile' } as const
const toSettings = { next: 'settings', nextLabel: 'Go to Settings' } as const

const TABLE: Record<ConfirmKind, Record<string, Resolution>> = {
  'email-confirm': {
    success: { title: 'New email confirmed.', body: 'Your new email is confirmed. For your security every device was signed out, so use the new address the next time you sign in.', next: 'signin', nextLabel: 'Sign in', endsSession: true },
    invalid: { title: 'Link not valid.', body: `${INVALID_EMAIL} Nothing was changed.`, ...toEdit },
    expired: { title: 'Link expired.', body: 'This link has expired. Sign in and ask for a new one from Edit profile.', ...toEdit },
    taken: { title: 'Email unavailable.', body: TAKEN, ...toEdit },
  },
  'email-authorize': {
    'check-new-inbox': { title: 'Change approved.', body: 'Thanks. We sent a confirmation email to your new address. Open the link in it to finish the change; until then your current email keeps working.', ...toEdit },
    invalid: { title: 'Link not valid.', body: `${INVALID_EMAIL} Nothing was changed.`, ...toEdit },
    expired: { title: 'Link expired.', body: 'This link has expired. Sign in and start the change again from Edit profile.', ...toEdit },
    taken: { title: 'Email unavailable.', body: TAKEN, ...toEdit },
  },
  'delete-confirm': {
    deleted: { title: 'Account deleted.', body: 'Your account and its data were deleted and every device was signed out. Thanks for watching with us.', next: 'landing', nextLabel: 'Back to WatchWise', endsSession: true },
    invalid: { title: 'Link not valid.', body: 'That deletion link is no longer valid. It may have been used, replaced by a newer one, cancelled, or voided by a password change. Your account was not deleted.', ...toSettings },
    expired: { title: 'Link expired.', body: 'That deletion link has expired and your account was not deleted. Ask for a new one with Delete account in Settings.', ...toSettings },
  },
}

const GENERIC_INVALID: Resolution = {
  title: 'Link not valid.',
  body: 'This link is incomplete or no longer valid. Nothing was changed.',
  next: 'signin',
  nextLabel: 'Sign in',
}

const FAILURE: Resolution = {
  title: 'Something went wrong.',
  body: 'We could not complete that, so nothing was changed. Check your connection and try again.',
  next: 'retry',
  nextLabel: 'Try again',
}

const CANCEL_OUTCOMES = ['cancelled', 'invalid', 'expired']

function cancelResolution(outcome: string, body: string | null, cancelledTitle: string, target: typeof toEdit | typeof toSettings): Resolution {
  if (!body || !CANCEL_OUTCOMES.includes(outcome)) return GENERIC_INVALID
  const title = outcome === 'cancelled' ? cancelledTitle : outcome === 'expired' ? 'Link expired.' : 'Link not valid.'
  return { title, body, ...target }
}

/** What the page shows for a link kind and outcome; unknown combinations fall back to a calm "not valid". */
export function resolveOutcome(kind: string | null | undefined, outcome: string | null | undefined): Resolution {
  if (outcome === FAILED) return FAILURE
  if (!isResultKind(kind) || !outcome) return GENERIC_INVALID
  if (kind === 'email-cancel') return cancelResolution(outcome, emailChangeNotice(outcome), 'Change cancelled.', toEdit)
  if (kind === 'delete-cancel') return cancelResolution(outcome, accountDeletionNotice(outcome), 'Deletion cancelled.', toSettings)
  return Object.hasOwn(TABLE[kind], outcome) ? TABLE[kind][outcome] : GENERIC_INVALID
}

const COPY: Record<ConfirmKind, { title: string; body: string; button: string }> = {
  'email-confirm': { title: 'Confirm your new email.', body: 'Confirming makes this your WatchWise sign-in email and signs you out on every device. Nothing changes until you press the button.', button: 'Confirm new email' },
  'email-authorize': { title: 'Approve the email change.', body: 'Someone asked to switch this account to a new email address. Approving sends a confirmation link to that new address; nothing changes yet. If this was not you, ignore this page and change your password.', button: 'Approve change' },
  'delete-confirm': { title: 'Delete your account?', body: 'This permanently removes your reviews, lists and history and signs you out everywhere. It cannot be undone. Nothing is deleted until you press the button.', button: 'Delete my account' },
}

export function confirmCopy(kind: ConfirmKind) {
  return COPY[kind]
}
