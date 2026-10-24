import type { Movie } from '../../components/MovieTile/MovieTile.tsx'
import type { Review } from '../../components/ReviewCard/ReviewCard.tsx'
import type { Gradient } from '../Home/homeData.ts'
import { colorOf, initialsOf, longDate, look, timeAgo } from '../../lib/format.ts'
import { movieFor } from '../../lib/movies.ts'
import type { ApiActivity, ApiList, ApiMember, ApiProfile, ApiReview } from '../../lib/types.ts'

// Turns GET /api/users/{username} into what the Profile page draws.

export type Member = {
  id: number
  handle: string
  name: string
  initials: string
  /** Avatar background, e.g. 'var(--accent-coral)'. */
  color: string
  avatar: string | null
}

export type ProfileList = { slug: string; title: string; meta: string; posters: Gradient[] }

export type ActivityItem =
  | { id: string; kind: 'rated'; movie: Movie; rating: number; time: string }
  | { id: string; kind: 'reviewed'; movie: Movie; review: Review; time: string }
  | { id: string; kind: 'list'; list: ProfileList; time: string }
  | { id: string; kind: 'watchlist'; movie: Movie; more: number; time: string }

export type Profile = Member & {
  location: string
  since: string
  bio: string
  isMe: boolean
  isFollowing: boolean
  followsYou: boolean
  /** Private (or followers-only) profile the viewer can't open: only the header is shown. */
  restricted: boolean
  followers: number
  /** null when the owner hides that section. */
  following: number | null
  reviews: number | null
  lists: number | null
  films: number | null
  activity: ActivityItem[]
  reviewList: Review[]
  listCards: ProfileList[]
  watched: Movie[]
  top4: Movie[]
  genres: string[]
  followerList: Member[]
  followingList: Member[]
  /** Ids the signed-in user follows, for the Follow buttons in the people list. */
  myFollowing: number[]
}

export const toMember = (m: ApiMember): Member => ({ id: m.id, handle: m.username, name: m.name, avatar: m.avatar, ...look(m) })

/** URL segment for a list: "12-rainy-day-comfort"; pages read the leading id. */
export const listSlug = (list: Pick<ApiList, 'id' | 'title'>) =>
  `${list.id}-${list.title.toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')}`

export const posterGradients = (tmdbIds: number[]) => tmdbIds.map((id) => movieFor(id).gradient)

export function toReview(review: ApiReview): Review {
  const author = review.author
  return {
    id: String(review.id),
    initials: author ? initialsOf(author.name) : '?',
    color: author ? colorOf(author.id) : 'var(--surface-cream-deep)',
    name: author?.name ?? 'Member',
    date: longDate(review.created_at),
    rating: review.rating,
    sentiment: review.sentiment,
    body: review.content,
    helpful: 0,
  }
}

export const toProfileList = (list: ApiList): ProfileList => ({
  slug: listSlug(list),
  title: list.title,
  meta: `${list.films} film${list.films === 1 ? '' : 's'} · ${list.is_public ? 'Public' : 'Private'}`,
  posters: posterGradients(list.posters),
})

export function toActivity(item: ApiActivity): ActivityItem {
  const id = String(item.id)
  const time = timeAgo(item.created_at)
  switch (item.kind) {
    case 'rated':
      return { id, kind: 'rated', movie: movieFor(item.review.tmdb_id), rating: item.rating, time }
    case 'reviewed':
      return { id, kind: 'reviewed', movie: movieFor(item.review.tmdb_id), review: toReview(item.review), time }
    case 'list':
      return { id, kind: 'list', list: toProfileList(item.list), time }
    case 'watchlist':
      return { id, kind: 'watchlist', movie: movieFor(item.tmdb_id ?? 0), more: 0, time }
  }
}

export function toProfile(data: ApiProfile): Profile {
  return {
    ...toMember(data.user),
    location: data.user.location ?? '',
    since: data.user.member_since ? String(new Date(data.user.member_since).getFullYear()) : '',
    bio: data.user.bio ?? '',
    isMe: data.is_me,
    isFollowing: data.is_following,
    followsYou: data.follows_you,
    restricted: data.restricted,
    followers: data.counts?.followers ?? 0,
    following: data.counts?.following ?? null,
    reviews: data.counts?.reviews ?? null,
    lists: data.counts?.lists ?? null,
    films: data.counts?.films ?? null,
    activity: (data.activity ?? []).map(toActivity),
    reviewList: (data.reviews ?? []).map(toReview),
    listCards: (data.lists ?? []).map(toProfileList),
    watched: (data.watched ?? []).map(movieFor),
    top4: (data.top4 ?? []).map(movieFor),
    genres: data.genres ?? [],
    followerList: (data.followers ?? []).map(toMember),
    followingList: (data.following ?? []).map(toMember),
    myFollowing: data.my_following ?? [],
  }
}

/** 2412 → "2.4k", 318 → "318", hidden (null) → "–". */
export function compactCount(n: number | null) {
  if (n === null) return '–'
  return n >= 1000 ? `${(n / 1000).toFixed(1).replace(/\.0$/, '')}k` : String(n)
}
