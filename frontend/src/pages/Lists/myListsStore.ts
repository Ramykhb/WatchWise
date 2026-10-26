import { useEffect, useSyncExternalStore } from 'react'
import { api } from '../../lib/api.ts'
import type { ApiList } from '../../lib/types.ts'
import { toMyList, type MyList } from './listsData.ts'

// The signed-in user's lists (GET /api/lists?scope=mine), shared by Lists, List Detail and the
// Create/Edit List dialog so creating, editing and deleting show everywhere at once.

type State = { lists: MyList[]; loaded: boolean }

let state: State = { lists: [], loaded: false }
const listeners = new Set<() => void>()

function emit(lists: MyList[]) {
  state = { lists, loaded: true }
  listeners.forEach((listener) => listener())
}

function subscribe(listener: () => void) {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

const getState = () => state

// Bumped on every account change, so a response for the previous account is ignored.
let generation = 0

export async function loadMyLists() {
  const started = generation
  const response = await api.get<{ lists: ApiList[] }>('/lists', { params: { scope: 'mine' } })
  if (started === generation) emit(response.data.lists.map(toMyList))
}

/** Drops the lists when the signed-in account changes (see AuthContext). */
export function clearMyLists() {
  generation++
  state = { lists: [], loaded: false }
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

export type ListInput = { title: string; description: string; is_public: boolean; tmdb_ids: number[] }

export async function createList(input: ListInput) {
  const started = generation
  const response = await api.post<{ list: ApiList }>('/lists', input)
  if (started === generation) emit([toMyList(response.data.list), ...state.lists])
  return response.data.list
}

/** Saves fields and/or the films (in order); returns the updated list. */
export async function updateList(id: number, input: Partial<ListInput>) {
  const started = generation
  const response = await api.patch<{ list: ApiList }>(`/lists/${id}`, input)
  const updated = toMyList(response.data.list)
  if (started === generation) emit(state.lists.map((list) => (list.id === id ? updated : list)))
  return response.data.list
}

/** Adds one film to the end of a list (the Movie page's "Add to list"); returns the updated list. */
export async function addMovieToList(id: number, tmdbId: number) {
  const started = generation
  const response = await api.post<{ list: ApiList }>(`/lists/${id}/items`, { tmdb_id: tmdbId })
  const updated = toMyList(response.data.list)
  if (started === generation) emit(state.lists.map((list) => (list.id === id ? updated : list)))
  return response.data.list
}

export async function removeMovieFromList(id: number, tmdbId: number) {
  const started = generation
  const response = await api.delete<{ list: ApiList }>(`/lists/${id}/items/${tmdbId}`)
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
