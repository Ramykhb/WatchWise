import axios from 'axios'

const backendUrl = (import.meta.env.VITE_BACKEND_URL ?? 'http://localhost:8000').replace(/\/$/, '')

export const authApi = axios.create({
  baseURL: `${backendUrl}/api/auth`,
  withCredentials: true,
  headers: { Accept: 'application/json' },
})

export type AuthUser = {
  id: number
  name: string
  email: string
  role: string
  is_active: boolean
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
