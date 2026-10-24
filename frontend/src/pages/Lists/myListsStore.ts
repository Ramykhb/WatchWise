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

export async function loadMyLists() {
  const response = await api.get<{ lists: ApiList[] }>('/lists', { params: { scope: 'mine' } })
  emit(response.data.lists.map(toMyList))
}

/** The user's lists, (re)loaded from the API whenever a page using them opens. */
export function useMyLists() {
  useEffect(() => {
    loadMyLists().catch(() => emit(state.lists))
  }, [])
  return useSyncExternalStore(subscribe, getState)
}

export type ListInput = { title: string; description: string; is_public: boolean; tmdb_ids: number[] }

export async function createList(input: ListInput) {
  const response = await api.post<{ list: ApiList }>('/lists', input)
  emit([toMyList(response.data.list), ...state.lists])
  return response.data.list
}

/** Saves fields and/or the films (in order); returns the updated list. */
export async function updateList(id: number, input: Partial<ListInput>) {
  const response = await api.patch<{ list: ApiList }>(`/lists/${id}`, input)
  const updated = toMyList(response.data.list)
  emit(state.lists.map((list) => (list.id === id ? updated : list)))
  return response.data.list
}

export async function deleteList(id: number) {
  await api.delete(`/lists/${id}`)
  emit(state.lists.filter((list) => list.id !== id))
}
