/** What the sign-in callback page shows when the backend redirects with `status=error&reason=<reason>`. */
export type CallbackFailure = { title: string; body: string }

const MESSAGES: Record<string, CallbackFailure> = {
  email_required: {
    title: 'We need an email address.',
    body: 'Your provider did not give us a verified email address. On GitHub, your account needs a verified primary email (GitHub Settings, Emails). On Discord, add and verify an email in your account settings. Then try again, or sign up with your email instead.',
  },
  email_unverified: {
    title: 'Email not verified.',
    body: 'Your provider has not verified the email on that account yet. Verify it with the provider, then try again, or sign in with your email and password.',
  },
  account_disabled: {
    title: 'Account disabled.',
    body: 'This WatchWise account has been disabled, so you cannot sign in with it. Contact support if you think this is a mistake.',
  },
  invalid_state: {
    title: 'Sign-in expired.',
    body: 'That sign-in link expired or was already used. This happens when sign-in takes longer than 10 minutes or is opened twice. Please start again.',
  },
  provider_error: {
    title: 'Sign-in failed.',
    body: 'We could not complete sign-in with your provider. Please try again in a moment.',
  },
  provider_unavailable: {
    title: 'Sign-in option unavailable.',
    body: 'This sign-in option is not available right now. Please use another option or sign in with your email.',
  },
  too_many_attempts: {
    title: 'Too many attempts.',
    body: 'There were too many sign-in attempts from your network. Wait a minute, then try again.',
  },
  access_denied: {
    title: 'Sign-in cancelled.',
    body: 'You cancelled the sign-in with your provider, so nothing changed. You can try again whenever you like.',
  },
}

const UNKNOWN: CallbackFailure = {
  title: 'Sign-in failed.',
  body: 'Social sign-in failed. Please try again.',
}

/** The message for a failure reason; a missing or unknown reason gets a generic one. */
export function callbackFailureMessage(reason: string | null | undefined): CallbackFailure {
  return typeof reason === 'string' && Object.hasOwn(MESSAGES, reason) ? MESSAGES[reason] : UNKNOWN
}

/** Shown when the backend reported success but the session could not be loaded afterwards. */
export const SESSION_FAILURE: CallbackFailure = {
  title: 'Could not complete sign-in.',
  body: 'Could not complete sign-in. Please try again.',
}
