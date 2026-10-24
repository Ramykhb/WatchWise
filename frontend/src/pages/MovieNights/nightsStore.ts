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

export async function loadNights() {
  const response = await api.get<Pick<State, 'upcoming' | 'invitations' | 'past'>>('/movie-nights')
  emit({ ...state, ...response.data, loaded: true })
}

export function useNights() {
  useEffect(() => {
    loadNights().catch(() => emit({ ...state, loaded: true }))
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
  const response = await api.post<{ night: ApiNight }>('/movie-nights', input)
  const upcoming = [...state.upcoming, response.data.night].sort((a, b) => a.scheduled_at.localeCompare(b.scheduled_at))
  emit({ ...state, upcoming })
  return response.data.night
}

/** Accepting moves the night into Upcoming; declining just drops it. */
export async function answerInvitation(id: number, accept: boolean) {
  const invitation = state.invitations.find((night) => night.id === id)
  if (!invitation) return
  const response = await api.put<{ night: ApiNight }>(`/movie-nights/${id}/rsvp`, { status: accept ? 'accepted' : 'declined' })
  emit({
    ...state,
    upcoming: accept ? [...state.upcoming, response.data.night].sort((a, b) => a.scheduled_at.localeCompare(b.scheduled_at)) : state.upcoming,
    invitations: state.invitations.filter((night) => night.id !== id),
    declined: accept ? state.declined : [...state.declined, invitation.title],
  })
}
