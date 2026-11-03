import type { Movie } from '../../components/MovieTile/MovieTile.tsx'
import { type Gradient } from '../Home/homeData.ts'

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
