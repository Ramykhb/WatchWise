import { useSyncExternalStore } from 'react'
import { MY_LISTS, type MyList } from './listsData.ts'

// In-memory store for the signed-in user's lists, shared by Lists, List Detail and the Create/Edit
// List dialog so creating, editing and deleting stay in sync while the app is open.
// TODO: replace with the lists API (GET/POST/PATCH/DELETE) once the backend is connected.

let lists: MyList[] = MY_LISTS
const listeners = new Set<() => void>()

function emit(next: MyList[]) {
  lists = next
  listeners.forEach((listener) => listener())
}

function subscribe(listener: () => void) {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

export const getMyLists = () => lists

export function useMyLists() {
  return useSyncExternalStore(subscribe, getMyLists)
}

/** Adds a new list or replaces the one with the same slug. */
export function saveMyList(list: MyList) {
  const exists = lists.some((l) => l.slug === list.slug)
  emit(exists ? lists.map((l) => (l.slug === list.slug ? list : l)) : [list, ...lists])
}

export function deleteMyList(slug: string) {
  emit(lists.filter((l) => l.slug !== slug))
}

/** URL slug from a title, made unique among the user's lists. */
export function uniqueSlug(title: string, ignore?: string) {
  const base =
    title
      .toLowerCase()
      .normalize('NFKD')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '') || 'my-list'
  let slug = base
  for (let n = 2; lists.some((l) => l.slug === slug && l.slug !== ignore); n++) slug = `${base}-${n}`
  return slug
}
