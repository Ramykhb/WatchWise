import { useCallback, useMemo, useState } from 'react'
import { api, useApi } from './api.ts'
import type { ApiWatchItem } from './types.ts'

// Every member has one Watchlist (the `watchlists` table): it can't be renamed or deleted, only
// filled. The Movie page's "Add to watchlist", Home's Tonight's pick and the Lists page read it here.

export type WatchlistResponse = { items: ApiWatchItem[]; ratings: Record<string, number> }

/** On the watchlist = queued to watch (a watched film has moved to the history). */
const isQueued = (item: ApiWatchItem) => item.status !== 'watched'

/**
 * Films with an add or remove still on its way, shared by every page using the hook. While a film is
 * here its button ignores clicks, so a remove never runs before the add has returned the real row id
 * (the instant placeholder has none) and two requests for one film never race.
 */
const pending = new Set<number>()

/** `enabled: false` (signed out) loads nothing and every film reads as not on the watchlist. */
export function useWatchlist({ enabled = true }: { enabled?: boolean } = {}) {
  const { data, setData, reload } = useApi<WatchlistResponse>('/watchlist', { enabled })
  const items = useMemo(() => data?.items ?? [], [data])
  const queued = useMemo(() => items.filter(isQueued), [items])
  // Re-renders this page when one of its films starts or stops saving.
  const [, setSaving] = useState(0)

  const has = useCallback((tmdbId: number) => items.some((item) => item.tmdb_id === tmdbId && isQueued(item)), [items])
  const isPending = (tmdbId: number) => pending.has(tmdbId)

  /** Runs one change for a film unless another is still on its way; false when it was skipped. */
  const exclusive = useCallback(async (tmdbId: number, change: () => Promise<void>) => {
    if (pending.has(tmdbId)) return false
    pending.add(tmdbId)
    setSaving((n) => n + 1)
    try {
      await change()
      return true
    } finally {
      pending.delete(tmdbId)
      setSaving((n) => n + 1)
    }
  }, [])

  /** Adds the film (or puts a watched one back in the queue); shows at once, undone if the request fails. */
  const add = useCallback(
    (tmdbId: number) =>
      exclusive(tmdbId, async () => {
        const placeholder: ApiWatchItem = { id: -tmdbId, tmdb_id: tmdbId, status: 'to_watch', watched_at: null, created_at: new Date().toISOString() }
        setData((current) => ({ ...current, items: [placeholder, ...current.items.filter((item) => item.tmdb_id !== tmdbId)] }))
        try {
          const response = await api.post<{ item: ApiWatchItem }>('/watchlist', { tmdb_id: tmdbId, status: 'to_watch' })
          setData((current) => ({ ...current, items: current.items.map((item) => (item.tmdb_id === tmdbId ? response.data.item : item)) }))
        } catch (error) {
          reload()
          throw error
        }
      }),
    [exclusive, setData, reload],
  )

  /** Takes the film off the watchlist; shows at once, undone if the request fails. */
  const remove = useCallback(
    (tmdbId: number) =>
      exclusive(tmdbId, async () => {
        const entry = items.find((item) => item.tmdb_id === tmdbId)
        // Only rows the server has created have an id that can be deleted.
        if (!entry || entry.id < 0) return
        setData((current) => ({ ...current, items: current.items.filter((item) => item.tmdb_id !== tmdbId) }))
        try {
          await api.delete(`/watchlist/${entry.id}`)
        } catch (error) {
          reload()
          throw error
        }
      }),
    [exclusive, items, setData, reload],
  )

  return { loaded: data !== undefined, queued, has, isPending, add, remove }
}
