import { useCallback, useEffect } from 'react'
import { useAuth } from '../contexts/useAuth.ts'
import { api, useApi } from './api.ts'
import type { ApiMe } from './types.ts'

// The Settings › Integrations switches (saved in GET/PATCH /api/me/preferences), for the features they
// control. Settings writes the same cached /me, so a change there applies on other pages at once.

export type Integration = keyof ApiMe['preferences']['integrations']

/** What a new account starts with (backend AccountResponse); a guest gets these too. */
const DEFAULTS: Record<Integration, boolean> = { calendar: true, location: true, weather: true, digest: false }

/** How long after a failed load the settings are asked for again. */
const RETRY_MS = 10_000

export function useIntegrations() {
  const { user } = useAuth()
  // A guest has no settings (and its 401 would end the session), so nothing is requested.
  const { data, error, setData, reload } = useApi<ApiMe>('/me', { enabled: user !== null })
  const integrations = user === null ? DEFAULTS : data?.preferences.integrations
  const failed = integrations === undefined && error !== undefined

  // Pages wait for the settings, so a failed load is tried again on its own (each failure is a new error).
  useEffect(() => {
    if (!failed) return
    const timer = window.setTimeout(reload, RETRY_MS)
    return () => window.clearTimeout(timer)
  }, [failed, error, reload])

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

  /** `failed`: the settings couldn't be loaded (retried automatically; `retry` asks again now). */
  return { loaded: integrations !== undefined, failed, retry: reload, isOn, save }
}
