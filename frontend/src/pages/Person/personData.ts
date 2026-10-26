import type { Movie } from '../../components/MovieTile/MovieTile.tsx'
import { MOVIES, type Gradient } from '../Home/homeData.ts'

// Mock content from the Figma "Person — Actor & Director" frame (Lena Okafor), for the design's demo people
// (/people/lena-okafor, linked from the demo movies). Real TMDB people (/people/{id}) load through personApi.ts.

export type Credit = { year: string; movie: Movie; roles: string }

/** `photoUrl` (TMDB people) replaces the initials on the colour. */
export type Collaborator = { slug: string; initials: string; name: string; meta: string; color: string; photoUrl?: string }

export type Person = {
  slug: string
  name: string
  initials: string
  gradient: Gradient
  /** A real photo (TMDB people); the initials on the gradient show without one. */
  photoUrl?: string
  roles: string[]
  bio: string
  stats: { value: string; label: string }[]
  knownFor: Movie[]
  filmography: Credit[]
  collaborators: Collaborator[]
  awards: string[]
  /** "Personal details" card rows (TMDB people): known for, born, birthplace, died. */
  details?: { label: string; value: string }[]
}

const LENA: Person = {
  slug: 'lena-okafor',
  name: 'Lena Okafor',
  initials: 'LO',
  gradient: ['#fa8cbf', '#9940b2', '#2b1a63'],
  roles: ['Director', 'Writer', 'Producer'],
  bio: 'Nigerian-British filmmaker known for luminous, ocean-soaked science fiction and intimate character dramas. Her 2026 hit Neon Tide became the most-rated film on WatchWise in its opening week.',
  stats: [
    { value: '14', label: 'films' },
    { value: '4.6', label: 'avg WatchWise rating' },
    { value: '1984', label: 'born · Lagos' },
  ],
  knownFor: [MOVIES.neonTide, MOVIES.lastLap, MOVIES.stardust, MOVIES.deepBlue, MOVIES.velvetHour, MOVIES.wildFern],
  filmography: [
    { year: '2026', movie: MOVIES.neonTide, roles: 'Director, Writer' },
    { year: '2025', movie: MOVIES.lastLap, roles: 'Director' },
    { year: '2025', movie: MOVIES.stardust, roles: 'Producer' },
    { year: '2024', movie: MOVIES.deepBlue, roles: 'Writer' },
    { year: '2023', movie: MOVIES.velvetHour, roles: 'Director' },
    { year: '2021', movie: MOVIES.wildFern, roles: 'Director, Writer' },
  ],
  collaborators: [
    { slug: 'amara-mensah', initials: 'AM', name: 'Amara Mensah', meta: 'Actor · 5 films', color: 'var(--accent-mint)' },
    { slug: 'tom-brennan', initials: 'TB', name: 'Tom Brennan', meta: 'Composer · 4 films', color: 'var(--accent-coral)' },
    { slug: 'sana-kaur', initials: 'SK', name: 'Sana Kaur', meta: 'Cinematographer · 4 films', color: 'var(--accent-yellow)' },
  ],
  awards: ['BAFTA · Outstanding Debut (2021)', 'Venice · Silver Lion nominee (2025)', 'Saturn Award · Best Director (2026)'],
}

// The Neon Tide cast and Lena's collaborators, so every person link on the site opens a page.
const OTHERS: Pick<Person, 'slug' | 'name' | 'initials' | 'gradient' | 'roles'>[] = [
  { slug: 'amara-mensah', name: 'Amara Mensah', initials: 'AM', gradient: ['#7de0c2', '#338cb2', '#211a5c'], roles: ['Actor'] },
  { slug: 'jonah-reyes', name: 'Jonah Reyes', initials: 'JR', gradient: ['#8cccff', '#4066d9', '#1a144d'], roles: ['Actor'] },
  { slug: 'sofia-park', name: 'Sofia Park', initials: 'SP', gradient: ['#ffe073', '#ff8c59', '#99264d'], roles: ['Actor'] },
  { slug: 'dev-bhatt', name: 'Dev Bhatt', initials: 'DB', gradient: ['#bfeb8c', '#4d9973', '#143338'], roles: ['Actor'] },
  { slug: 'elena-lind', name: 'Elena Lind', initials: 'EL', gradient: ['#ccbfff', '#8c66d9', '#331a66'], roles: ['Actor'] },
  { slug: 'tom-brennan', name: 'Tom Brennan', initials: 'TB', gradient: ['#ff995c', '#ed4d6b', '#5c298c'], roles: ['Composer'] },
  { slug: 'sana-kaur', name: 'Sana Kaur', initials: 'SK', gradient: ['#ffbf66', '#d94033', '#400d26'], roles: ['Cinematographer'] },
]

/** Person for a slug, or undefined when the slug is unknown. */
export function getPerson(slug: string | undefined): Person | undefined {
  if (slug === LENA.slug) return LENA
  const other = OTHERS.find((p) => p.slug === slug)
  if (!other) return undefined
  return {
    ...LENA,
    ...other,
    bio: `${other.name} has worked with Lena Okafor on several WatchWise favourites. A full biography arrives with TMDB data.`,
    collaborators: [
      { slug: LENA.slug, initials: LENA.initials, name: LENA.name, meta: 'Director · 6 films', color: 'var(--surface-violet-tint)' },
      ...LENA.collaborators.filter((c) => c.slug !== other.slug),
    ].slice(0, 3),
  }
}
