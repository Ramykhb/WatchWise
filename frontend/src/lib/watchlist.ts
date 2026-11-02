import { useCallback, useMemo, useState } from 'react'
import { api, useApi } from './api.ts'
import { itemKey, type FilmKey } from './movies.ts'
import type { ApiWatchItem } from './types.ts'

// Every member has one Watchlist (the `watchlists` table): it can't be renamed or deleted, only
// filled. The Movie page's "Add to watchlist" and "Mark as watched", Home's Tonight's pick and the
// Lists page read it here. Titles are keyed like lists: 550 for a movie, "tv-1399" for a TV show.

export type WatchlistResponse = { items: ApiWatchItem[]; ratings: Record<string, number> }

/** On the watchlist = queued to watch (a watched film has moved to the history). */
const isQueued = (item: ApiWatchItem) => item.status !== 'watched'

const sameTitle = (key: FilmKey) => (item: ApiWatchItem) => String(itemKey(item)) === String(key)

/** The API's id and type for a key ("tv-1399" → 1399, tv). */
function titleOfKey(key: FilmKey) {
  const text = String(key)
  return text.startsWith('tv-')
    ? { tmdb_id: Number(text.slice('tv-'.length)), media_type: 'tv' as const }
    : { tmdb_id: Number(text), media_type: 'movie' as const }
}

/**
 * Titles with a change still on its way, shared by every page using the hook. While a title is
 * here its buttons ignore clicks, so a remove never runs before the add has returned the real row id
 * (the instant placeholder has none) and two requests for one title never race.
 */
const pending = new Set<string>()

/** `enabled: false` (signed out) loads nothing and every title reads as not on the watchlist. */
export function useWatchlist({ enabled = true }: { enabled?: boolean } = {}) {
  const { data, error, setData, reload } = useApi<WatchlistResponse>('/watchlist', { enabled })
  const items = useMemo(() => data?.items ?? [], [data])
  const queued = useMemo(() => items.filter(isQueued), [items])
  // Re-renders this page when one of its titles starts or stops saving.
  const [, setSaving] = useState(0)

  const has = useCallback((key: FilmKey) => items.some((item) => sameTitle(key)(item) && isQueued(item)), [items])
  const isWatched = useCallback((key: FilmKey) => items.some((item) => sameTitle(key)(item) && item.status === 'watched'), [items])
  const isPending = (key: FilmKey) => pending.has(String(key))

  /** Runs one change for a title unless another is still on its way; false when it was skipped. */
  const exclusive = useCallback(async (key: FilmKey, change: () => Promise<void>) => {
    const id = String(key)
    if (pending.has(id)) return false
    pending.add(id)
    setSaving((n) => n + 1)
    try {
      await change()
      return true
    } finally {
      pending.delete(id)
      setSaving((n) => n + 1)
    }
  }, [])

  /** Saves the title with this status (new or moved); shows at once, undone if the request fails. */
  const save = useCallback(
    (key: FilmKey, status: ApiWatchItem['status']) =>
      exclusive(key, async () => {
        const title = titleOfKey(key)
        const placeholder: ApiWatchItem = {
          id: -title.tmdb_id,
          ...title,
          status,
          watched_at: status === 'watched' ? new Date().toISOString() : null,
          created_at: new Date().toISOString(),
        }
        setData((current) => ({ ...current, items: [placeholder, ...current.items.filter((item) => !sameTitle(key)(item))] }))
        try {
          const response = await api.post<{ item: ApiWatchItem }>('/watchlist', { ...title, status })
          setData((current) => ({ ...current, items: current.items.map((item) => (sameTitle(key)(item) ? response.data.item : item)) }))
        } catch (error) {
          reload()
          throw error
        }
      }),
    [exclusive, setData, reload],
  )

  /** Takes the title off the watchlist and out of the history; shows at once, undone if the request fails. */
  const remove = useCallback(
    (key: FilmKey) =>
      exclusive(key, async () => {
        const entry = items.find(sameTitle(key))
        // Only rows the server has created have an id that can be deleted.
        if (!entry || entry.id < 0) return
        setData((current) => ({ ...current, items: current.items.filter((item) => !sameTitle(key)(item)) }))
        try {
          await api.delete(`/watchlist/${entry.id}`)
        } catch (error) {
          reload()
          throw error
        }
      }),
    [exclusive, items, setData, reload],
  )

  /** Adds the title (or puts a watched one back in the queue). */
  const add = useCallback((key: FilmKey) => save(key, 'to_watch'), [save])

  /** Marking moves the title to the history (adding it if needed); unmarking takes it off both. */
  const setWatched = useCallback((key: FilmKey, watched: boolean) => (watched ? save(key, 'watched') : remove(key)), [save, remove])

  return { loaded: data !== undefined, failed: error !== undefined, reload, queued, has, isWatched, isPending, add, remove, setWatched }
}
