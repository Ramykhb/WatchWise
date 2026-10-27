import { useCallback, useMemo } from 'react'
import { api, useApi } from './api.ts'
import type { ApiWatchItem } from './types.ts'

// Every member has one Watchlist (the `watchlists` table): it can't be renamed or deleted, only
// filled. The Movie page's "Add to watchlist", Home's Tonight's pick and the Lists page read it here.

export type WatchlistResponse = { items: ApiWatchItem[]; ratings: Record<string, number> }

/** On the watchlist = queued to watch (a watched film has moved to the history). */
const isQueued = (item: ApiWatchItem) => item.status !== 'watched'

/** `enabled: false` (signed out) loads nothing and every film reads as not on the watchlist. */
export function useWatchlist({ enabled = true }: { enabled?: boolean } = {}) {
  const { data, setData, reload } = useApi<WatchlistResponse>('/watchlist', { enabled })
  const items = useMemo(() => data?.items ?? [], [data])
  const queued = useMemo(() => items.filter(isQueued), [items])

  const has = useCallback((tmdbId: number) => items.some((item) => item.tmdb_id === tmdbId && isQueued(item)), [items])

  /** Adds the film (or puts a watched one back in the queue); shows at once, undone if the request fails. */
  const add = useCallback(
    async (tmdbId: number) => {
      const placeholder: ApiWatchItem = { id: -tmdbId, tmdb_id: tmdbId, status: 'to_watch', watched_at: null, created_at: new Date().toISOString() }
      setData((current) => ({ ...current, items: [placeholder, ...current.items.filter((item) => item.tmdb_id !== tmdbId)] }))
      try {
        const response = await api.post<{ item: ApiWatchItem }>('/watchlist', { tmdb_id: tmdbId, status: 'to_watch' })
        setData((current) => ({ ...current, items: current.items.map((item) => (item.tmdb_id === tmdbId ? response.data.item : item)) }))
      } catch (error) {
        reload()
        throw error
      }
    },
    [setData, reload],
  )

  /** Takes the film off the watchlist; shows at once, undone if the request fails. */
  const remove = useCallback(
    async (tmdbId: number) => {
      const entry = items.find((item) => item.tmdb_id === tmdbId)
      if (!entry) return
      setData((current) => ({ ...current, items: current.items.filter((item) => item.tmdb_id !== tmdbId) }))
      try {
        await api.delete(`/watchlist/${entry.id}`)
      } catch (error) {
        reload()
        throw error
      }
    },
    [items, setData, reload],
  )

  return { loaded: data !== undefined, queued, has, add, remove }
}
