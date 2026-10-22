import { MOVIES, type Gradient } from '../Home/homeData.ts'

// Mock content from the Figma "Lists — My & Public Lists" frame. Replace with the lists API.

export type MyList = {
  slug: string
  title: string
  description: string
  isPublic: boolean
  films: number
  updated: string
  /** Cover background behind the collage. */
  cover: string
  posters: Gradient[]
}

export type PublicList = {
  slug: string
  title: string
  author: string
  initials: string
  color: string
  likes: string
  films: number
  posters: Gradient[]
  following?: boolean
}

const G = Object.fromEntries(Object.entries(MOVIES).map(([key, movie]) => [key, movie.gradient])) as Record<
  keyof typeof MOVIES,
  Gradient
>

export const MY_LISTS: MyList[] = [
  {
    slug: 'favorite-comedies',
    title: 'Favorite Comedies',
    description: 'Guaranteed laughs for bad days and good friends.',
    isPublic: true,
    films: 14,
    updated: 'updated 2d ago',
    cover: 'var(--surface-violet-tint)',
    posters: [G.paperMoon, G.velvetHour, G.neonTide],
  },
  {
    slug: 'movies-to-watch-this-summer',
    title: 'Movies to Watch This Summer',
    description: 'Big, bright adventures for long warm nights.',
    isPublic: true,
    films: 22,
    updated: 'updated 1w ago',
    cover: 'var(--surface-violet-tint)',
    posters: [G.wildFern, G.deepBlue, G.paperMoon],
  },
  {
    slug: 'comfort-rewatches',
    title: 'Comfort Rewatches',
    description: 'My personal ‘reset button’ films.',
    isPublic: false,
    films: 9,
    updated: 'updated Mar 1',
    cover: 'var(--surface-cream-deep)',
    posters: [G.stardust, G.lastLap, G.velvetHour],
  },
  {
    slug: 'lena-okafor-marathon',
    title: 'Lena Okafor Marathon',
    description: 'Every film, ranked — work in progress.',
    isPublic: true,
    films: 6,
    updated: 'updated Feb 22',
    cover: 'var(--surface-violet-tint)',
    posters: [G.neonTide, G.lastLap, G.stardust],
  },
  {
    slug: 'date-night-shortlist',
    title: 'Date Night Shortlist',
    description: 'Romance & feel-good, nothing too heavy.',
    isPublic: false,
    films: 11,
    updated: 'updated Feb 10',
    cover: 'var(--surface-cream-deep)',
    posters: [G.velvetHour, G.redSignal, G.paperMoon],
  },
]

export const PUBLIC_LISTS: PublicList[] = [
  {
    slug: 'rainy-day-comfort',
    title: 'Rainy Day Comfort',
    author: 'Lina S.',
    initials: 'LS',
    color: 'var(--accent-yellow)',
    likes: '2.1k',
    films: 24,
    posters: [G.velvetHour, G.deepBlue, G.stardust],
    following: true,
  },
  {
    slug: 'sci-fi-that-feels-like-a-hug',
    title: 'Sci-fi That Feels Like a Hug',
    author: 'Jad N.',
    initials: 'JN',
    color: 'var(--accent-mint)',
    likes: '1.4k',
    films: 18,
    posters: [G.neonTide, G.stardust, G.lastLap],
    following: true,
  },
  {
    slug: 'under-90-minutes',
    title: 'Under 90 Minutes',
    author: 'Omar K.',
    initials: 'OK',
    color: 'var(--accent-coral)',
    likes: '986',
    films: 31,
    posters: [G.redSignal, G.paperMoon, G.wildFern],
  },
]

/** "Following 12" in the Figma tabs; only the followed lists above are shown until the API exists. */
export const FOLLOWING_COUNT = 12
