import { useCallback } from 'react'
import { useAuth } from '../contexts/useAuth.ts'
import { api, useApi } from './api.ts'
import type { ApiMe } from './types.ts'

// The Settings › Integrations switches (saved in GET/PATCH /api/me/preferences), for the features they
// control. Settings writes the same cached /me, so a change there applies on other pages at once.

export type Integration = keyof ApiMe['preferences']['integrations']

/** What a new account starts with (backend AccountResponse); a guest gets these too. */
const DEFAULTS: Record<Integration, boolean> = { calendar: true, location: true, weather: true, digest: false }

export function useIntegrations() {
  const { user } = useAuth()
  // A guest has no settings (and its 401 would end the session), so nothing is requested.
  const { data, setData } = useApi<ApiMe>('/me', { enabled: user !== null })
  const integrations = user === null ? DEFAULTS : data?.preferences.integrations

  /** Shows at once and saves; a failed save puts the switch back. */
  const save = useCallback(
    async (key: Integration, on: boolean) => {
      const change = (value: boolean) =>
        setData((me) => ({ ...me, preferences: { ...me.preferences, integrations: { ...me.preferences.integrations, [key]: value } } }))
      change(on)
      try {
        await api.patch('/me/preferences', { integrations: { [key]: on } })
      } catch (error) {
        change(!on)
        throw error
      }
    },
    [setData],
  )

  /** Until the settings load, nothing counts as switched on, so no feature starts before it's allowed. */
  const isOn = (key: Integration) => integrations?.[key] === true

  return { loaded: integrations !== undefined, isOn, save }
}
