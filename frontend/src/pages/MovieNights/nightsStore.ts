import { useEffect, useSyncExternalStore } from 'react'
import { api } from '../../lib/api.ts'
import type { ApiNight } from '../../lib/types.ts'

// Movie nights of the signed-in user (GET /api/movie-nights): ones they host or were invited to.
// Shared by the planner so creating a night or answering an invite shows at once.

type State = { upcoming: ApiNight[]; invitations: ApiNight[]; past: ApiNight[]; declined: string[]; loaded: boolean }

let state: State = { upcoming: [], invitations: [], past: [], declined: [], loaded: false }
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

// Bumped on every account change, so a response for the previous account is ignored.
let generation = 0

export async function loadNights() {
  const started = generation
  const response = await api.get<Pick<State, 'upcoming' | 'invitations' | 'past'>>('/movie-nights')
  if (started === generation) emit({ ...state, ...response.data, loaded: true })
}

/** Drops the nights when the signed-in account changes (see AuthContext). */
export function clearNights() {
  generation++
  emit({ upcoming: [], invitations: [], past: [], declined: [], loaded: false })
}

export function useNights() {
  useEffect(() => {
    const started = generation
    loadNights().catch(() => started === generation && emit({ ...state, loaded: true }))
  }, [])
  return useSyncExternalStore(subscribe, getState)
}

export type NightInput = {
  title: string
  scheduled_at: string
  location?: string | null
  meeting_link?: string | null
  invitees: string[]
  tmdb_ids: number[]
}

export async function createNight(input: NightInput) {
  const started = generation
  const response = await api.post<{ night: ApiNight }>('/movie-nights', input)
  if (started !== generation) return response.data.night
  const upcoming = [...state.upcoming, response.data.night].sort((a, b) => a.scheduled_at.localeCompare(b.scheduled_at))
  emit({ ...state, upcoming })
  return response.data.night
}

/** Accepting moves the night into Upcoming; declining just drops it. */
export async function answerInvitation(id: number, accept: boolean) {
  const invitation = state.invitations.find((night) => night.id === id)
  if (!invitation) return
  const started = generation
  const response = await api.put<{ night: ApiNight }>(`/movie-nights/${id}/rsvp`, { status: accept ? 'accepted' : 'declined' })
  if (started !== generation) return
  emit({
    ...state,
    upcoming: accept ? [...state.upcoming, response.data.night].sort((a, b) => a.scheduled_at.localeCompare(b.scheduled_at)) : state.upcoming,
    invitations: state.invitations.filter((night) => night.id !== id),
    declined: accept ? state.declined : [...state.declined, invitation.title],
  })
}
