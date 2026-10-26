import { shortName, look, updatedLabel } from '../../lib/format.ts'
import type { ApiList } from '../../lib/types.ts'
import type { Gradient } from '../Home/homeData.ts'
import { listSlug, posterGradients } from '../Profile/profileData.ts'

// Turns lists from GET /api/lists into the cards the Lists page draws.

export type MyList = {
  id: number
  slug: string
  title: string
  description: string
  isPublic: boolean
  films: number
  updated: string
  /** Cover background behind the collage. */
  cover: string
  posters: Gradient[]
  /** TMDB ids of the cover posters, to load their images. */
  posterIds: number[]
}

export type PublicList = {
  id: number
  slug: string
  title: string
  author: string
  authorHandle: string
  initials: string
  color: string
  films: number
  posters: Gradient[]
  /** TMDB ids of the cover posters, to load their images. */
  posterIds: number[]
}

/** Three cover posters; empty lists show the neutral placeholder poster. */
const coverOf = (list: ApiList) => posterGradients(list.posters.length ? list.posters.slice(0, 3) : [0])
const coverIdsOf = (list: ApiList) => list.posters.slice(0, 3)

export const toMyList = (list: ApiList): MyList => ({
  id: list.id,
  slug: listSlug(list),
  title: list.title,
  description: list.description ?? '',
  isPublic: list.is_public,
  films: list.films,
  updated: updatedLabel(list.updated_at),
  cover: list.is_public ? 'var(--surface-violet-tint)' : 'var(--surface-cream-deep)',
  posters: coverOf(list),
  posterIds: coverIdsOf(list),
})

export const toPublicList = (list: ApiList): PublicList => ({
  id: list.id,
  slug: listSlug(list),
  title: list.title,
  author: list.owner ? shortName(list.owner.name) : 'Member',
  authorHandle: list.owner?.username ?? '',
  ...(list.owner ? look(list.owner) : { initials: '?', color: 'var(--surface-cream-deep)' }),
  films: list.films,
  posters: coverOf(list),
  posterIds: coverIdsOf(list),
})

/** The list id from a "/lists/12-rainy-day-comfort" segment. */
export const listIdFrom = (slug: string | undefined) => Number.parseInt(slug ?? '', 10)
