import axios from 'axios'
import { api } from './api.ts'

/** Auth requests share the same base URL, cookies and refresh handling as every other API call. */
export const authApi = api

export type AuthUser = {
  id: number
  name: string
  username: string
  email: string
  avatar: string | null
  bio: string | null
  location: string | null
  role: string
  profile_visibility: string
  is_active: boolean
  email_verified_at: string | null
}

export type AuthPayload = { user: AuthUser; expires_at: string }

export function apiErrorMessage(error: unknown, fallback: string): string {
  if (axios.isAxiosError(error)) {
    const response = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined
    const firstValidationError = response?.errors ? Object.values(response.errors).flat()[0] : undefined
    return firstValidationError ?? response?.message ?? fallback
  }
  return fallback
}

/** Field errors from a Laravel 422 response, e.g. { username: 'The username has already been taken.' }. */
export function fieldErrors(error: unknown): Record<string, string> {
  if (!axios.isAxiosError(error)) return {}
  const errors = (error.response?.data as { errors?: Record<string, string[]> } | undefined)?.errors ?? {}
  return Object.fromEntries(Object.entries(errors).map(([key, messages]) => [key, messages[0]]))
}
