import axios from 'axios'
import { api } from './api.ts'
import { apiErrorMessage, fieldErrors } from './auth.ts'

export const PASSWORD_PATTERN = /^(?=.*\d).{8,}$/

export type ChangePasswordBody = { current_password: string; password: string; password_confirmation: string }
export type SetPasswordBody = { password: string; password_confirmation: string }

/** Codes the API puts on a 409 when the request does not fit the account's password state. */
export type PasswordConflictCode = 'no_password_set' | 'password_already_set'

/** PUT /api/me/password: for accounts that already have a password. */
export const changePassword = (body: ChangePasswordBody) => api.put('/me/password', body)

/** PUT /api/me/password/set: for accounts without a password (Google/GitHub sign-ups). */
export const setPassword = (body: SetPasswordBody) => api.put('/me/password/set', body)

/** The 409 conflict code of a failed password request, or null for any other failure. */
export function passwordConflictCode(error: unknown): PasswordConflictCode | null {
  if (!axios.isAxiosError(error) || error.response?.status !== 409) return null
  const code = (error.response.data as { code?: string } | undefined)?.code
  return code === 'no_password_set' || code === 'password_already_set' ? code : null
}

export const PASSWORD_CONFLICT_MESSAGES: Record<PasswordConflictCode, string> = {
  no_password_set: "This account doesn't have a password yet, so you can set one instead.",
  password_already_set: 'This account already has a password. Use Change password instead.',
}

export type PasswordResult =
  | { ok: true }
  | { ok: false; conflict: PasswordConflictCode; message: string }
  | { ok: false; conflict: null; message: string; errors: Record<string, string> }

/** Runs a password request and sorts the outcome: done, a 409 state mismatch, or validation/other errors. */
export async function settlePassword(request: Promise<unknown>, fallback: string): Promise<PasswordResult> {
  try {
    await request
    return { ok: true }
  } catch (error) {
    const conflict = passwordConflictCode(error)
    if (conflict) return { ok: false, conflict, message: PASSWORD_CONFLICT_MESSAGES[conflict] }
    return { ok: false, conflict: null, message: apiErrorMessage(error, fallback), errors: fieldErrors(error) }
  }
}
