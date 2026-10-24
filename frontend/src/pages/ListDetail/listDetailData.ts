import type { Movie } from '../../components/MovieTile/MovieTile.tsx'
import type { Gradient } from '../Home/homeData.ts'
import { longDate, look, shortName, updatedLabel } from '../../lib/format.ts'
import { movieFor } from '../../lib/movies.ts'
import type { ApiList } from '../../lib/types.ts'
import { listSlug, posterGradients } from '../Profile/profileData.ts'

// Turns GET /api/lists/{id} into what the List Detail page draws.

export type ListEntry = { movie: Movie; note?: string }

export type ListDetail = {
  id: number
  slug: string
  title: string
  description: string
  isPublic: boolean
  films: number
  updated: string
  created: string
  runtime: string
  author: { name: string; handle: string; initials: string; color: string }
  isOwner: boolean
  posters: Gradient[]
  entries: ListEntry[]
}

export type SimilarList = { slug: string; title: string; author: string; poster: Gradient }

/** Rough total until TMDB runtimes exist: about two hours a film. */
export const runtimeFor = (films: number) => {
  const minutes = films * 118
  return `${Math.floor(minutes / 60)}h ${String(minutes % 60).padStart(2, '0')}m`
}

export function toListDetail(list: ApiList, viewerId: number | undefined): ListDetail {
  const owner = list.owner
  const updated = updatedLabel(list.updated_at)
  return {
    id: list.id,
    slug: listSlug(list),
    title: list.title,
    description: list.description ?? '',
    isPublic: list.is_public,
    films: list.films,
    updated: updated[0].toUpperCase() + updated.slice(1),
    created: longDate(list.created_at),
    runtime: runtimeFor(list.films),
    author: owner
      ? { name: shortName(owner.name), handle: owner.username, ...look(owner) }
      : { name: 'Member', handle: '', initials: '?', color: 'var(--surface-cream-deep)' },
    isOwner: owner !== null && owner.id === viewerId,
    posters: posterGradients(list.posters.length ? list.posters.slice(0, 3) : [0]),
    entries: (list.items ?? []).map((item) => ({ movie: movieFor(item.tmdb_id), note: item.note ?? undefined })),
  }
}

export const toSimilarList = (list: ApiList): SimilarList => ({
  slug: listSlug(list),
  title: list.title,
  author: list.owner ? shortName(list.owner.name) : 'Member',
  poster: movieFor(list.posters[0] ?? 0).gradient,
})
