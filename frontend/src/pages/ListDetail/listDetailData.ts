import type { Movie } from '../../components/MovieTile/MovieTile.tsx'
import { MOVIES, type Gradient } from '../Home/homeData.ts'
import { MY_LISTS, PUBLIC_LISTS } from '../Lists/listsData.ts'

// Mock content from the Figma "List Detail — Rainy Day Comfort" frame. Other lists get their own
// titles, covers and a few films; replace all of it with the lists API.

export type ListEntry = { movie: Movie; note?: string }

export type ListDetail = {
  slug: string
  title: string
  description: string
  isPublic: boolean
  films: number
  updated: string
  created: string
  runtime: string
  author: { name: string; initials: string; color: string }
  likes: number
  followers: number
  posters: Gradient[]
  entries: ListEntry[]
}

export type SimilarList = { slug: string; title: string; author: string; poster: Gradient }

const ME = { name: 'Maya Rivera', initials: 'MR', color: 'var(--accent-mint)' }

const RAINY_DAY: ListDetail = {
  slug: 'rainy-day-comfort',
  title: 'Rainy Day Comfort',
  description: 'Soft, slow, atmospheric films for grey afternoons — the cinematic equivalent of a blanket and a hot drink.',
  isPublic: true,
  films: 24,
  updated: 'Updated 2 days ago',
  created: 'Jan 12, 2026',
  runtime: '47h 12m',
  author: { name: 'Lina S.', initials: 'LS', color: 'var(--accent-yellow)' },
  likes: 2100,
  followers: 340,
  posters: [MOVIES.velvetHour.gradient, MOVIES.deepBlue.gradient, MOVIES.stardust.gradient],
  entries: [
    { movie: MOVIES.deepBlue, note: '“Best watched with the lights off and rain on the window.”' },
    { movie: MOVIES.velvetHour, note: '“Warm, gentle and quietly devastating.”' },
    { movie: MOVIES.stardust, note: '“Pure comfort. A fairytale for grown-ups.”' },
    { movie: MOVIES.lastLap, note: '“Slow burn that pays off in the last 20 minutes.”' },
    { movie: MOVIES.paperMoon, note: '“For when you need a laugh after a long week.”' },
    { movie: MOVIES.wildFern, note: '“Lush landscapes, cozy pacing.”' },
  ],
}

export const SIMILAR_LISTS: SimilarList[] = [
  { slug: 'sunday-afternoon-slow-films', title: 'Sunday Afternoon Slow Films', author: 'Rana H.', poster: MOVIES.stardust.gradient },
  { slug: 'cozy-mysteries', title: 'Cozy Mysteries', author: 'Omar K.', poster: MOVIES.deepBlue.gradient },
  { slug: 'films-that-feel-like-autumn', title: 'Films That Feel Like Autumn', author: 'Jad N.', poster: MOVIES.redSignal.gradient },
]

const ALL_MOVIES = Object.values(MOVIES)

/** Films whose poster is in the list cover come first, then a few more, so every list has entries. */
function entriesFor(posters: Gradient[], count: number): ListEntry[] {
  const cover = posters
    .map((gradient) => ALL_MOVIES.find((movie) => movie.gradient === gradient))
    .filter((movie): movie is Movie => Boolean(movie))
  const rest = ALL_MOVIES.filter((movie) => !cover.includes(movie))
  return [...cover, ...rest].slice(0, Math.min(count, 6)).map((movie) => ({ movie }))
}

const runtimeFor = (films: number) => {
  const minutes = films * 118
  return `${Math.floor(minutes / 60)}h ${String(minutes % 60).padStart(2, '0')}m`
}

/** Details for a list slug, or undefined when it is unknown. */
export function getListDetail(slug: string | undefined): ListDetail | undefined {
  if (!slug) return undefined
  if (slug === RAINY_DAY.slug) return RAINY_DAY

  const mine = MY_LISTS.find((list) => list.slug === slug)
  if (mine) {
    return {
      slug,
      title: mine.title,
      description: mine.description,
      isPublic: mine.isPublic,
      films: mine.films,
      updated: mine.updated.replace(/^updated/, 'Updated'),
      created: 'Jan 2026',
      runtime: runtimeFor(mine.films),
      author: ME,
      likes: mine.isPublic ? 48 : 0,
      followers: mine.isPublic ? 12 : 0,
      posters: mine.posters,
      entries: entriesFor(mine.posters, mine.films),
    }
  }

  const other = PUBLIC_LISTS.find((list) => list.slug === slug)
  if (other) {
    return {
      slug,
      title: other.title,
      description: `A public list by ${other.author}.`,
      isPublic: true,
      films: other.films,
      updated: 'Updated this month',
      created: '2026',
      runtime: runtimeFor(other.films),
      author: { name: other.author, initials: other.initials, color: other.color },
      likes: Number.parseFloat(other.likes) * (other.likes.endsWith('k') ? 1000 : 1),
      followers: 120,
      posters: other.posters,
      entries: entriesFor(other.posters, other.films),
    }
  }

  const similar = SIMILAR_LISTS.find((list) => list.slug === slug)
  if (similar) {
    const posters = [similar.poster, MOVIES.velvetHour.gradient, MOVIES.lastLap.gradient]
    return {
      slug,
      title: similar.title,
      description: `A public list by ${similar.author}.`,
      isPublic: true,
      films: 12,
      updated: 'Updated this month',
      created: '2026',
      runtime: runtimeFor(12),
      author: { name: similar.author, initials: similar.author.slice(0, 1) + similar.author.split(' ')[1].slice(0, 1), color: 'var(--surface-violet-tint)' },
      likes: 210,
      followers: 64,
      posters,
      entries: entriesFor(posters, 12),
    }
  }

  return undefined
}

export const FOLLOWER_FACES = [
  { initials: 'MR', color: 'var(--accent-mint)' },
  { initials: 'OK', color: 'var(--accent-coral)' },
  { initials: 'JN', color: 'var(--surface-white)' },
  { initials: 'RH', color: 'var(--surface-violet-tint)' },
]
