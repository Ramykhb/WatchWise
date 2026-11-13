import { useEffect, useSyncExternalStore } from 'react'
import { api } from '../../lib/api.ts'
import type { FilmKey, MediaType } from '../../lib/movies.ts'
import type { ApiList, ApiListsPage } from '../../lib/types.ts'
import { toMyList, type MyList } from './listsData.ts'

// The signed-in user's lists (GET /api/lists?scope=mine), shared by Lists, List Detail and the
// Create/Edit List dialog so creating, editing and deleting show everywhere at once.

// The lists come a page at a time: `page` is the last one loaded, `hasMore` whether another follows.
type State = { lists: MyList[]; loaded: boolean; page: number; hasMore: boolean; loadingMore: boolean }

const EMPTY: State = { lists: [], loaded: false, page: 1, hasMore: false, loadingMore: false }
let state: State = EMPTY
const listeners = new Set<() => void>()

function emit(lists: MyList[], paging: Partial<Pick<State, 'page' | 'hasMore' | 'loadingMore'>> = {}) {
  state = { ...state, ...paging, lists, loaded: true }
  listeners.forEach((listener) => listener())
}

function subscribe(listener: () => void) {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

const getState = () => state

// Bumped on every account change, so a response for the previous account is ignored.
let generation = 0
// Bumped whenever the first page is loaded again, so a "Load more" answer for the old paging is ignored.
let firstPages = 0

export async function loadMyLists() {
  const started = generation
  const response = await api.get<ApiListsPage>('/lists', { params: { scope: 'mine' } })
  if (started !== generation) return
  // A reload starts over from the first page.
  firstPages++
  emit(response.data.lists.map(toMyList), { page: 1, hasMore: response.data.meta.has_more, loadingMore: false })
}

/**
 * Appends the next page of the user's lists (Lists page "Load more"). Lists created in this session are
 * already in the store, so a list the server sends again is skipped; a list deleted since the first page
 * can make the next page start one list late, which the next reload (opening Lists again) puts right.
 */
export async function loadMoreMyLists() {
  if (!state.hasMore || state.loadingMore) return
  const started = generation
  const paging = firstPages
  const page = state.page + 1
  emit(state.lists, { loadingMore: true })
  try {
    const response = await api.get<ApiListsPage>('/lists', { params: { scope: 'mine', page } })
    if (started !== generation || paging !== firstPages) return
    const known = new Set(state.lists.map((list) => list.id))
    const added = response.data.lists.filter((list) => !known.has(list.id)).map(toMyList)
    emit([...state.lists, ...added], { page, hasMore: response.data.meta.has_more, loadingMore: false })
  } catch (error) {
    if (started === generation && paging === firstPages) emit(state.lists, { loadingMore: false })
    throw error
  }
}

/** Drops the lists when the signed-in account changes (see AuthContext). */
export function clearMyLists() {
  generation++
  firstPages++
  state = EMPTY
  listeners.forEach((listener) => listener())
}

/**
 * The user's lists, (re)loaded from the API whenever a page using them opens. A failed load
 * stops the loading state but keeps only what this account already loaded (never another's,
 * since the store is cleared on every account change).
 */
export function useMyLists() {
  useEffect(() => {
    const started = generation
    loadMyLists().catch(() => started === generation && emit(state.lists))
  }, [])
  return useSyncExternalStore(subscribe, getState)
}

/** `tmdb_ids`: movies as TMDB ids (550) and TV shows as "tv-1399", in list order. */
export type ListInput = { title: string; description: string; is_public: boolean; tmdb_ids: FilmKey[] }

export async function createList(input: ListInput) {
  const started = generation
  const response = await api.post<{ list: ApiList }>('/lists', input)
  if (started === generation) emit([toMyList(response.data.list), ...state.lists])
  return response.data.list
}

/**
 * Saves fields and/or the films (in order); returns the updated list. `expectedUpdatedAt` is the list's
 * `updated_at` as last loaded: if the list was saved since, the server answers 409 instead of overwriting it.
 */
export async function updateList(id: number, input: Partial<ListInput>, expectedUpdatedAt?: string) {
  const started = generation
  const body = expectedUpdatedAt ? { ...input, expected_updated_at: expectedUpdatedAt } : input
  const response = await api.patch<{ list: ApiList }>(`/lists/${id}`, body)
  const updated = toMyList(response.data.list)
  if (started === generation) emit(state.lists.map((list) => (list.id === id ? updated : list)))
  return response.data.list
}

/** Adds one movie or TV show to the end of a list (the Movie page's "Add to list"); returns the updated list. */
export async function addMovieToList(id: number, tmdbId: number, mediaType: MediaType = 'movie') {
  const started = generation
  const response = await api.post<{ list: ApiList }>(`/lists/${id}/items`, { tmdb_id: tmdbId, media_type: mediaType })
  const updated = toMyList(response.data.list)
  if (started === generation) emit(state.lists.map((list) => (list.id === id ? updated : list)))
  return response.data.list
}

export async function removeMovieFromList(id: number, tmdbId: number, mediaType: MediaType = 'movie') {
  const started = generation
  const response = await api.delete<{ list: ApiList }>(`/lists/${id}/items/${tmdbId}`, { params: { type: mediaType } })
  const updated = toMyList(response.data.list)
  if (started === generation) emit(state.lists.map((list) => (list.id === id ? updated : list)))
  return response.data.list
}

/**
 * Deletes the list on the server, then drops it from the store. `beforeRemove` runs in between
 * (only after a successful delete), e.g. to play the card's exit animation first.
 */
export async function deleteList(id: number, beforeRemove?: () => Promise<void>) {
  const started = generation
  await api.delete(`/lists/${id}`)
  await beforeRemove?.()
  if (started === generation) emit(state.lists.filter((list) => list.id !== id))
}
