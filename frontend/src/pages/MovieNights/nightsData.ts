import { MOVIES, type Gradient } from '../Home/homeData.ts'
import { MEMBERS, type Member } from '../Profile/profileData.ts'

// Mock content from the Figma "Movie Nights — Planner" frame. Replace with the movie nights API
// (movie_nights, invites, candidates and votes tables already exist in the backend schema).

export type NightStatus = 'voting' | 'picked' | 'shortlisting'

export type Guest = Pick<Member, 'initials' | 'color'> & { name: string }

export type Night = {
  slug: string
  title: string
  /** ISO date, e.g. "2025-03-14". */
  date: string
  /** 24h time, e.g. "20:30". */
  time: string
  status: NightStatus
  /** Status chip text, e.g. "Voting open · 18h left". */
  statusLabel: string
  /** Line next to the crown, e.g. "Deep Blue leads · 5 votes". */
  lead: string
  where: string
  online: boolean
  guests: Guest[]
  /** Shown as "+N" after the faces. */
  moreGuests: number
  /** Movie slugs on the shortlist. */
  candidates: string[]
  gradient: Gradient
}

export type Invitation = {
  id: string
  host: Guest
  night: Night
}

const guest = (m: Member): Guest => ({ name: m.name.split(' ')[0], initials: m.initials, color: m.color })
export const YOU: Guest = { ...guest(MEMBERS.maya), name: 'You' }
const OMAR = guest(MEMBERS.omark)
const LINA = guest(MEMBERS.linasaad)
const JAD = { ...guest(MEMBERS.jadn), color: 'var(--accent-mint)' }
const RANA = guest(MEMBERS.ranah)
const SARA: Guest = { name: 'Sara', initials: 'SA', color: 'var(--accent-coral)' }

/** People you follow, offered in "Invite friends". */
export const FRIENDS: Guest[] = [OMAR, LINA, JAD, { ...RANA, color: 'var(--surface-violet-tint)' }, SARA]

export const UPCOMING: Night[] = [
  {
    slug: 'friday-frights',
    title: 'Friday Frights',
    date: '2025-03-14',
    time: '20:30',
    status: 'voting',
    statusLabel: 'Voting open · 18h left',
    lead: 'Deep Blue leads · 5 votes',
    where: "Lina's place",
    online: false,
    guests: [YOU, OMAR, LINA],
    moreGuests: 3,
    candidates: ['deep-blue', 'red-signal', 'neon-tide'],
    gradient: MOVIES.deepBlue.gradient,
  },
  {
    slug: 'comedy-night',
    title: 'Comedy Night',
    date: '2025-03-22',
    time: '21:00',
    status: 'picked',
    statusLabel: 'Movie picked',
    lead: 'Paper Moon · 6 votes',
    where: 'Online',
    online: true,
    guests: [JAD, RANA],
    moreGuests: 2,
    candidates: ['paper-moon', 'wild-fern'],
    gradient: MOVIES.paperMoon.gradient,
  },
  {
    slug: 'date-night',
    title: 'Date Night',
    date: '2025-04-05',
    time: '20:00',
    status: 'shortlisting',
    statusLabel: 'Shortlisting',
    lead: '3 films so far',
    where: 'Grand ABC',
    online: false,
    guests: [YOU, SARA],
    moreGuests: 0,
    candidates: ['velvet-hour', 'stardust', 'paper-moon'],
    gradient: MOVIES.velvetHour.gradient,
  },
]

export const INVITATIONS: Invitation[] = [
  {
    id: 'scifi-saturday',
    host: OMAR,
    night: {
      slug: 'sci-fi-saturday',
      title: 'Sci-fi Saturday',
      date: '2025-03-15',
      time: '21:00',
      status: 'voting',
      statusLabel: 'Voting open',
      lead: 'Neon Tide leads · 3 votes',
      where: 'meet.watchwise.app/scifi',
      online: true,
      guests: [OMAR, YOU, JAD],
      moreGuests: 1,
      candidates: ['neon-tide', 'stardust'],
      gradient: MOVIES.neonTide.gradient,
    },
  },
  {
    id: 'oscars-watch-party',
    host: RANA,
    night: {
      slug: 'oscars-watch-party',
      title: 'Oscars Watch Party',
      date: '2025-03-16',
      time: '19:00',
      status: 'picked',
      statusLabel: 'Movie picked',
      lead: 'Velvet Hour · 7 votes',
      where: "Rana's place, Achrafieh",
      online: false,
      guests: [RANA, YOU, LINA],
      moreGuests: 4,
      candidates: ['velvet-hour'],
      gradient: MOVIES.redSignal.gradient,
    },
  },
]

export const PAST: Night[] = [
  {
    slug: 'valentines-rewatch',
    title: "Valentine's Rewatch",
    date: '2025-02-14',
    time: '20:00',
    status: 'picked',
    statusLabel: 'Watched',
    lead: 'Velvet Hour · 4 votes',
    where: 'Online',
    online: true,
    guests: [YOU, LINA],
    moreGuests: 1,
    candidates: ['velvet-hour'],
    gradient: MOVIES.velvetHour.gradient,
  },
  {
    slug: 'thriller-thursday',
    title: 'Thriller Thursday',
    date: '2025-02-05',
    time: '21:30',
    status: 'picked',
    statusLabel: 'Watched',
    lead: 'Deep Blue · 5 votes',
    where: "Omar's place",
    online: false,
    guests: [OMAR, YOU, JAD],
    moreGuests: 2,
    candidates: ['deep-blue'],
    gradient: MOVIES.wildFern.gradient,
  },
]

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
