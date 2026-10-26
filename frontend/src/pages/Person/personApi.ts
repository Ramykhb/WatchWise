import type { Movie } from '../../components/MovieTile/MovieTile.tsx'
import type { Gradient } from '../Home/homeData.ts'
import type { Collaborator, Credit, Person } from './personData.ts'

/** One film from GET /api/people/{id}, with every role the person had on it. */
export type ApiPersonCredit = {
  id: number
  title: string
  year: number | null
  poster_url: string | null
  rating: number
  vote_count: number
  character: string | null
  roles: string[]
}

/** GET /api/people/{id}: a TMDB person with their best-known films and filmography (newest first). */
export type ApiPerson = {
  id: number
  name: string
  biography: string
  birthday: string | null
  deathday: string | null
  place_of_birth: string | null
  known_for_department: string | null
  profile_url: string | null
  known_for: ApiPersonCredit[]
  credits: ApiPersonCredit[]
}

/** GET /api/people/{id}/collaborators: up to three people who share the most of this person's best-known films. */
export type ApiCollaborators = {
  collaborators: { id: number; name: string; profile_url: string | null; role: string; films: number }[]
}

/** Avatar colours behind collaborators' initials, as in the design. */
const COLLABORATOR_COLORS = ['var(--accent-mint)', 'var(--accent-coral)', 'var(--accent-yellow)']

/** Shown behind posters that are loading or missing. */
const FALLBACK_GRADIENT: Gradient = ['#ccbfff', '#8c66d9', '#331a66']

/** The portrait behind the photo, and with initials when TMDB has no photo. */
const PORTRAIT_GRADIENTS: Gradient[] = [
  ['#fa8cbf', '#9940b2', '#2b1a63'],
  ['#7de0c2', '#338cb2', '#211a5c'],
  ['#8cccff', '#4066d9', '#1a144d'],
  ['#ffe073', '#ff8c59', '#99264d'],
  ['#bfeb8c', '#4d9973', '#143338'],
]

/** TMDB's department names as the design writes a person's main role. */
const DEPARTMENT_ROLES: Record<string, string> = {
  Acting: 'Actor',
  Directing: 'Director',
  Writing: 'Writer',
  Production: 'Producer',
  Sound: 'Composer',
  Camera: 'Cinematographer',
  Editing: 'Editor',
}

/**
 * TMDB jobs worth showing, as the design names them. Minor credits ("Thanks", "Songs",
 * "Casting Assistant", …) are left out of the role chips and the filmography.
 */
const JOB_ROLES: Record<string, string> = {
  Actor: 'Actor',
  Director: 'Director',
  Writer: 'Writer',
  Screenplay: 'Writer',
  Story: 'Writer',
  Novel: 'Writer',
  Characters: 'Writer',
  Producer: 'Producer',
  'Executive Producer': 'Executive producer',
  'Original Music Composer': 'Composer',
  Music: 'Composer',
  'Director of Photography': 'Cinematographer',
  Editor: 'Editor',
}

const initials = (name: string) =>
  name
    .split(/\s+/)
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()

/** "Neon Tide" → ["NEON", "TIDE"], for the gradient tile when there is no poster. */
function posterTitle(title: string): [string, string] {
  const words = title.toUpperCase().split(/\s+/)
  const middle = Math.ceil(words.length / 2)
  return [words.slice(0, middle).join(' '), words.slice(middle).join(' ')]
}

/** "1970-07-30" → "July 30, 1970". */
const formatDate = (date: string) =>
  new Date(`${date}T00:00:00`).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })

/** Whole years between two "YYYY-MM-DD" dates (the second defaults to today). */
function yearsBetween(from: string, to?: string) {
  const start = new Date(`${from}T00:00:00`)
  const end = to ? new Date(`${to}T00:00:00`) : new Date()
  const hadBirthday = end.getMonth() > start.getMonth() || (end.getMonth() === start.getMonth() && end.getDate() >= start.getDate())
  return end.getFullYear() - start.getFullYear() - (hadBirthday ? 0 : 1)
}

/** A credit's roles as the design names them, without minor credits (unique, in TMDB's order). */
const mainRoles = (credit: ApiPersonCredit) => [...new Set(credit.roles.map((role) => JOB_ROLES[role]).filter(Boolean))]

/** A film shaped like the design's movie tiles, so MovieTile and the watchlist button can use it. */
function toMovie(credit: ApiPersonCredit): Movie {
  return {
    // The TMDB movie id, so the film links to /movies/{id} and the watchlist saves the right film.
    slug: String(credit.id),
    title: credit.title,
    posterTitle: posterTitle(credit.title),
    meta: credit.year ? String(credit.year) : 'Upcoming',
    // Unreleased films have no votes yet; a dash reads better than "0.0".
    score: credit.vote_count ? credit.rating.toFixed(1) : '–',
    gradient: FALLBACK_GRADIENT,
    posterUrl: credit.poster_url ?? undefined,
  }
}

/** "Director, Writer" or "Actor · as Bruce Wayne" for a filmography row. */
function rolesLabel(credit: ApiPersonCredit) {
  const label = mainRoles(credit).join(', ')
  return credit.character ? `${label} · as ${credit.character}` : label
}

/** The person's role chips: their TMDB department first, then the roles they've had most often. */
function personRoles(api: ApiPerson): string[] {
  const counts = new Map<string, number>()
  for (const credit of api.credits) {
    for (const role of mainRoles(credit)) counts.set(role, (counts.get(role) ?? 0) + 1)
  }
  const main = api.known_for_department ? DEPARTMENT_ROLES[api.known_for_department] : undefined
  const others = [...counts.entries()]
    .filter(([role]) => role !== main)
    .sort((a, b) => b[1] - a[1])
    .map(([role]) => role)
  return [...(main ? [main] : []), ...others].slice(0, 3)
}

/** Shapes GET /api/people/{id}/collaborators for the "Frequent collaborators" card ("Composer · 8 films"). */
export function toCollaborators(api: ApiCollaborators): Collaborator[] {
  return api.collaborators.map((person, index) => ({
    // The TMDB person id, so each collaborator links to their own page.
    slug: String(person.id),
    initials: initials(person.name),
    name: person.name,
    meta: `${JOB_ROLES[person.role] ?? person.role} · ${person.films} films`,
    color: COLLABORATOR_COLORS[index % COLLABORATOR_COLORS.length],
    photoUrl: person.profile_url ?? undefined,
  }))
}

/** Shapes GET /api/people/{id} like the design's Person, so the page draws real and mock people the same way. */
export function toPerson(api: ApiPerson): Person {
  // Rows whose only roles are minor credits (e.g. "Thanks") don't belong in a filmography.
  const credits = api.credits.filter((credit) => mainRoles(credit).length > 0)
  const rated = credits.filter((credit) => credit.vote_count > 0)
  const average = rated.length ? rated.reduce((sum, credit) => sum + credit.rating, 0) / rated.length : null
  const city = api.place_of_birth?.split(',')[0]?.trim()
  const age = api.birthday ? yearsBetween(api.birthday, api.deathday ?? undefined) : null

  return {
    slug: String(api.id),
    name: api.name,
    initials: initials(api.name),
    gradient: PORTRAIT_GRADIENTS[api.id % PORTRAIT_GRADIENTS.length],
    photoUrl: api.profile_url ?? undefined,
    roles: personRoles(api),
    bio: api.biography || `TMDB has no biography for ${api.name} yet.`,
    stats: [
      { value: String(credits.length), label: credits.length === 1 ? 'film' : 'films' },
      ...(average !== null ? [{ value: average.toFixed(1), label: 'avg TMDB rating' }] : []),
      ...(api.birthday ? [{ value: api.birthday.slice(0, 4), label: city ? `born · ${city}` : 'born' }] : []),
    ],
    knownFor: api.known_for.map(toMovie),
    filmography: credits.map((credit) => ({ year: credit.year ? String(credit.year) : '—', movie: toMovie(credit), roles: rolesLabel(credit) }) satisfies Credit),
    // TMDB has no collaborators or awards; the page hides those cards when they're empty.
    collaborators: [],
    awards: [],
    details: [
      api.known_for_department && { label: 'Known for', value: api.known_for_department },
      api.birthday && { label: 'Born', value: api.deathday ? formatDate(api.birthday) : `${formatDate(api.birthday)} (age ${age})` },
      api.place_of_birth && { label: 'Birthplace', value: api.place_of_birth },
      api.deathday && { label: 'Died', value: `${formatDate(api.deathday)} (aged ${age})` },
    ].filter((detail): detail is { label: string; value: string } => Boolean(detail)),
  }
}
