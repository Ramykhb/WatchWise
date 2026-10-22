import { MOVIES } from '../Home/homeData.ts'
import { FRIENDS, YOU, type Guest, type Night } from './nightsData.ts'

// Mock content from the Figma "Movie Night Detail — Friday Frights" frame. Other nights get a
// default detail built from their card data. Replace with the movie nights API.

export type Rsvp = 'going' | 'maybe' | 'declined' | 'pending'

export type Candidate = {
  slug: string
  /** e.g. "1h 58m" */
  runtime?: string
  suggestedBy: string
  voters: Guest[]
}

export type NightDetail = {
  host: Guest
  /** Full address when the card shows a short one ("Lina's place" → "Lina's place, Hamra"). */
  where?: string
  link?: string
  note?: string
  /** Everyone invited, including you. */
  guests: { guest: Guest; rsvp: Rsvp }[]
  /** RSVPs of guests not listed individually (Figma shows 5 of 10). */
  hidden: { going: number; pending: number }
  totalGuests: number
  candidates: Candidate[]
  closesIn?: string
}

const [OMAR, LINA, JAD, RANA] = FRIENDS
const SAMI: Guest = { name: 'Sami', initials: 'SA', color: 'var(--accent-coral)' }
const EXTRA_A: Guest = { name: 'Nour', initials: 'NB', color: 'var(--surface-violet-tint)' }
const EXTRA_B: Guest = { name: 'Karim', initials: 'KT', color: 'var(--surface-violet-tint)' }

const DETAILS: Record<string, NightDetail> = {
  'friday-frights': {
    host: { ...LINA, name: 'Lina S.' },
    where: "Lina's place, Hamra",
    link: 'meet.watchwise.app/ff-314',
    note: 'Bring snacks — Lina has the projector.',
    closesIn: '18h',
    guests: [
      { guest: { ...LINA, name: 'Lina S.' }, rsvp: 'going' },
      { guest: YOU, rsvp: 'going' },
      { guest: { ...OMAR, name: 'Omar K.' }, rsvp: 'going' },
      { guest: { ...JAD, name: 'Jad N.' }, rsvp: 'pending' },
      { guest: { ...SAMI, name: 'Sami A.' }, rsvp: 'declined' },
    ],
    hidden: { going: 3, pending: 1 },
    totalGuests: 10,
    candidates: [
      { slug: 'deep-blue', runtime: '1h 58m', suggestedBy: 'Lina S.', voters: [LINA, YOU, OMAR, EXTRA_A, EXTRA_B] },
      { slug: 'red-signal', runtime: '1h 54m', suggestedBy: 'Omar K.', voters: [JAD, SAMI] },
      { slug: 'stardust', runtime: '2h 06m', suggestedBy: 'Rana H.', voters: [{ ...RANA, color: 'var(--accent-yellow)' }] },
      { slug: 'velvet-hour', runtime: '1h 49m', suggestedBy: 'Jad N.', voters: [] },
    ],
  },
}

export function getNightDetail(night: Night): NightDetail {
  const known = DETAILS[night.slug]
  if (known) return known
  const host = night.guests[0] ?? YOU
  return {
    host,
    link: night.online ? night.where : undefined,
    guests: night.guests.map((guest) => ({ guest, rsvp: guest === YOU || guest === host ? 'going' : 'pending' })),
    hidden: { going: 0, pending: night.moreGuests },
    totalGuests: night.guests.length + night.moreGuests,
    candidates: night.candidates.map((slug) => ({ slug, suggestedBy: host === YOU ? 'you' : host.name, voters: [] })),
  }
}

export const movieInfo = (slug: string) => Object.values(MOVIES).find((m) => m.slug === slug)
