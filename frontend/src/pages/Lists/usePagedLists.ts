import { useState } from 'react'
import { api, useApi } from '../../lib/api.ts'
import type { ApiList, ApiListsPage } from '../../lib/types.ts'

/** The lists after the first page, tied to the first-page response they were loaded for. */
type More = { base: ApiListsPage | undefined; lists: ApiList[]; page: number; hasMore: boolean; loading: boolean }

/**
 * Pages through `GET /api/lists…`: the first page loads (and is cached) like any `useApi` request, and
 * `loadMore` appends the next one. Reloading or changing `path` starts over from the first page.
 * `path` may carry its own query string (`/lists?scope=public&q=noir`).
 */
export function usePagedLists(path: string) {
  const first = useApi<ApiListsPage>(path)
  const [more, setMore] = useState<More>({ base: undefined, lists: [], page: 1, hasMore: false, loading: false })

  // Extra pages belong to one first-page response; a new one (other search, reload) drops them.
  const current: More = more.base === first.data
    ? more
    : { base: first.data, lists: [], page: 1, hasMore: first.data?.meta.has_more ?? false, loading: false }

  const loadMore = async () => {
    if (!first.data || !current.hasMore || current.loading) return
    const { base } = current
    setMore({ ...current, loading: true })
    try {
      const response = await api.get<ApiListsPage>(path, { params: { page: current.page + 1 } })
      // The first page changed (new search, reload) while this request ran: its answer no longer fits.
      setMore((latest) => {
        if (latest.base !== base) return latest
        const seen = new Set([...base!.lists, ...latest.lists].map((list) => list.id))
        const added = response.data.lists.filter((list) => !seen.has(list.id))
        return { base, lists: [...latest.lists, ...added], page: current.page + 1, hasMore: response.data.meta.has_more, loading: false }
      })
    } catch {
      // Keep what is shown; the "Load more" button stays so the member can try again.
      setMore((latest) => (latest.base === base ? { ...latest, loading: false } : latest))
    }
  }

  return {
    lists: [...(first.data?.lists ?? []), ...current.lists],
    /** True until the first page answers or fails. */
    loading: !first.data && !first.error,
    error: first.error,
    reload: first.reload,
    hasMore: current.hasMore,
    loadingMore: current.loading,
    loadMore,
  }
}
