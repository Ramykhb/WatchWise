import type { ApiNight, ApiRsvp } from '../../lib/types.ts'
import { toGuest, type Guest } from './nightsData.ts'

// Turns GET /api/movie-nights/{id} into the guests, RSVPs and shortlist the detail page draws.

export type Rsvp = 'going' | 'maybe' | 'declined' | 'pending'

export type Candidate = { tmdbId: number; suggestedBy: string; voters: Guest[] }

export type NightDetail = {
  host: Guest
  isHost: boolean
  /** Your answer, or null when you are the host. */
  myRsvp: Rsvp | null
  myVote: number | null
  where?: string
  link?: string
  note?: string
  /** Host first, then everyone invited. */
  guests: { guest: Guest; rsvp: Rsvp; isHost: boolean }[]
  candidates: Candidate[]
  closed: boolean
  winner: number | null
}

const RSVP: Record<ApiRsvp, Rsvp> = { accepted: 'going', maybe: 'maybe', declined: 'declined', pending: 'pending' }

/** The API's answer for an RSVP option. */
export const apiRsvp = (rsvp: Exclude<Rsvp, 'pending'>): ApiRsvp => (rsvp === 'going' ? 'accepted' : rsvp)

export function toNightDetail(night: ApiNight, viewerId: number | undefined): NightDetail {
  const host = toGuest(night.organizer, viewerId)
  return {
    host,
    isHost: night.is_organizer,
    myRsvp: night.my_rsvp === 'host' ? null : RSVP[night.my_rsvp],
    myVote: night.my_vote,
    where: night.location ?? undefined,
    link: night.meeting_link ?? undefined,
    note: night.note ?? undefined,
    guests: [
      { guest: host, rsvp: 'going', isHost: true },
      ...night.guests.map((g) => ({ guest: toGuest(g.user, viewerId), rsvp: RSVP[g.status], isHost: false })),
    ],
    candidates: night.candidates.map((c) => ({
      tmdbId: c.tmdb_id,
      suggestedBy: c.added_by ? (c.added_by.id === viewerId ? 'you' : toGuest(c.added_by, viewerId).name) : 'someone',
      voters: c.voters.map((v) => toGuest(v, viewerId)),
    })),
    closed: night.status === 'confirmed' || night.winning_tmdb_id !== null,
    winner: night.winning_tmdb_id,
  }
}
