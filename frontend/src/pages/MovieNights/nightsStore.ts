import { useSyncExternalStore } from 'react'
import { INVITATIONS, UPCOMING, type Invitation, type Night } from './nightsData.ts'

// In-memory store for movie nights, shared by the planner and the movie night page so creating
// a night or answering an invite stays in sync while the app is open.
// TODO: replace with the movie nights API (create, invite, RSVP) once the backend is connected.

type State = { upcoming: Night[]; invitations: Invitation[]; declined: string[] }

let state: State = { upcoming: UPCOMING, invitations: INVITATIONS, declined: [] }
const listeners = new Set<() => void>()

function emit(next: State) {
  state = next
  listeners.forEach((listener) => listener())
}

function subscribe(listener: () => void) {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

const getState = () => state

export function useNights() {
  return useSyncExternalStore(subscribe, getState)
}

const byDate = (a: Night, b: Night) => `${a.date}${a.time}`.localeCompare(`${b.date}${b.time}`)

export function addNight(night: Night) {
  emit({ ...state, upcoming: [...state.upcoming, night].sort(byDate) })
}

/** Accepting moves the invite's night into Upcoming; declining just drops it. */
export function answerInvitation(id: string, accept: boolean) {
  const invitation = state.invitations.find((i) => i.id === id)
  if (!invitation) return
  emit({
    upcoming: accept ? [...state.upcoming, invitation.night].sort(byDate) : state.upcoming,
    invitations: state.invitations.filter((i) => i.id !== id),
    declined: accept ? state.declined : [...state.declined, invitation.night.title],
  })
}

/** URL slug from a title, unique among upcoming nights. */
export function uniqueNightSlug(title: string) {
  const base = title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'movie-night'
  let slug = base
  for (let n = 2; state.upcoming.some((night) => night.slug === slug); n++) slug = `${base}-${n}`
  return slug
}
