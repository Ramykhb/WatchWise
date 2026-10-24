import type { Gradient } from '../Home/homeData.ts'
import { look, shortName } from '../../lib/format.ts'
import { movieFor } from '../../lib/movies.ts'
import type { ApiMember, ApiNight } from '../../lib/types.ts'

// Turns movie nights from GET /api/movie-nights into the cards and invites the planner draws.

export type NightStatus = 'voting' | 'picked' | 'shortlisting'

export type Guest = { id: number; handle: string; name: string; initials: string; color: string }

export type Night = {
  id: number
  slug: string
  title: string
  /** Local ISO date, e.g. "2025-03-14". */
  date: string
  /** Local 24h time, e.g. "20:30". */
  time: string
  status: NightStatus
  /** Status chip text, e.g. "Voting open". */
  statusLabel: string
  /** Line next to the crown, e.g. "Deep Blue leads · 5 votes". */
  lead: string
  where: string
  online: boolean
  /** Host and guests who are going; the first three get faces. */
  guests: Guest[]
  /** Shown as "+N" after the faces. */
  moreGuests: number
  /** TMDB ids on the shortlist. */
  candidates: number[]
  gradient: Gradient
}

export type Invitation = { id: number; host: Guest; night: Night }

/** "You" for the signed-in user, "Lina S." for everyone else. */
export const toGuest = (member: ApiMember, viewerId: number | undefined): Guest => ({
  id: member.id,
  handle: member.username,
  name: member.id === viewerId ? 'You' : shortName(member.name),
  ...look(member),
})

const pad = (n: number) => String(n).padStart(2, '0')

/** Local { date: "2025-03-14", time: "20:30" } of an API timestamp. */
export function localDateTime(iso: string) {
  const when = new Date(iso)
  return { date: `${when.getFullYear()}-${pad(when.getMonth() + 1)}-${pad(when.getDate())}`, time: `${pad(when.getHours())}:${pad(when.getMinutes())}` }
}

/** Leading film: the winner once picked, else the most-voted candidate. */
export function leaderOf(night: ApiNight) {
  const picked = night.winning_tmdb_id ? night.candidates.find((c) => c.tmdb_id === night.winning_tmdb_id) : undefined
  return picked ?? night.candidates[0]
}

export function toNight(night: ApiNight, viewerId: number | undefined): Night {
  const when = new Date(night.scheduled_at)
  const past = when.getTime() < Date.now()
  const picked = night.winning_tmdb_id !== null || night.status === 'confirmed'
  const status: NightStatus = picked ? 'picked' : night.candidates.length > 1 ? 'voting' : 'shortlisting'
  const leader = leaderOf(night)
  const leaderVotes = leader?.voters.length ?? 0
  const leaderTitle = leader ? movieFor(leader.tmdb_id).title : ''
  const going = [night.organizer, ...night.guests.filter((g) => g.status === 'accepted').map((g) => g.user)]

  return {
    id: night.id,
    slug: String(night.id),
    title: night.title,
    ...localDateTime(night.scheduled_at),
    status,
    statusLabel: past ? 'Watched' : status === 'picked' ? 'Movie picked' : status === 'voting' ? 'Voting open' : 'Shortlisting',
    lead:
      status === 'picked' && leader
        ? `${leaderTitle} · ${leaderVotes} vote${leaderVotes === 1 ? '' : 's'}`
        : status === 'voting'
          ? leaderVotes > 0
            ? `${leaderTitle} leads · ${leaderVotes} vote${leaderVotes === 1 ? '' : 's'}`
            : `${night.candidates.length} films to vote on`
          : `${night.candidates.length} film${night.candidates.length === 1 ? '' : 's'} so far`,
    where: night.meeting_link ?? night.location ?? 'TBD',
    online: night.meeting_link !== null,
    guests: going.slice(0, 3).map((m) => toGuest(m, viewerId)),
    moreGuests: Math.max(0, going.length - 3),
    candidates: night.candidates.map((c) => c.tmdb_id),
    gradient: movieFor(leader?.tmdb_id ?? 0).gradient,
  }
}

const DATE = new Intl.DateTimeFormat('en-US', { weekday: 'short', month: 'short', day: 'numeric', timeZone: 'UTC' })

/** "Sat, Mar 15 · 9:00 PM" */
export function formatWhen(night: Pick<Night, 'date' | 'time'>) {
  const [h, m] = night.time.split(':').map(Number)
  const time = `${((h + 11) % 12) + 1}:${String(m).padStart(2, '0')} ${h < 12 ? 'AM' : 'PM'}`
  return `${DATE.format(new Date(`${night.date}T00:00:00Z`))} · ${time}`
}

/** { month: "MAR", day: "14" } for the date tile. */
export function dateTile(date: string) {
  const d = new Date(`${date}T00:00:00Z`)
  return {
    month: d.toLocaleString('en-US', { month: 'short', timeZone: 'UTC' }).toUpperCase(),
    day: String(d.getUTCDate()).padStart(2, '0'),
  }
}
