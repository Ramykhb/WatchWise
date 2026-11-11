import axios from 'axios'
import { fieldErrors } from '../../lib/auth.ts'

export type ResetLink = { token: string; email: string }

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

/** The token and email from the emailed link's query string, or null when either is missing or implausible. */
export function parseResetLink(search: string): ResetLink | null {
  const params = new URLSearchParams(search)
  const token = params.get('token') ?? ''
  const email = (params.get('email') ?? '').trim()
  if (!token || token.length > 512 || /\s/.test(token)) return null
  if (!email || email.length > 255 || !EMAIL_PATTERN.test(email)) return null
  return { token, email }
}

export type ResetBody = { email: string; token: string; password: string; password_confirmation: string }

export const resetBody = (link: ResetLink, password: string, confirmation: string): ResetBody => ({
  email: link.email, token: link.token, password, password_confirmation: confirmation,
})

export type ResetFailure =
  | { kind: 'invalid-link' }
  | { kind: 'fields'; errors: Record<string, string>; message: string }
  | { kind: 'other'; message: string }

const FALLBACK = 'Unable to reset your password. Please try again.'

/** Sorts a failed reset: a 422 without field errors means the token is wrong, used or expired. */
export function classifyResetFailure(error: unknown): ResetFailure {
  if (!axios.isAxiosError(error)) return { kind: 'other', message: FALLBACK }
  const data = error.response?.data as { message?: string } | undefined
  if (error.response?.status === 422) {
    const errors = fieldErrors(error)
    if (Object.keys(errors).length === 0 || errors.token) return { kind: 'invalid-link' }
    return { kind: 'fields', errors, message: Object.values(errors)[0] ?? FALLBACK }
  }
  if (error.response?.status === 429) return { kind: 'other', message: 'Too many attempts. Wait a minute and try again.' }
  return { kind: 'other', message: data?.message ?? FALLBACK }
}
