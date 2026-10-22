import type { Movie } from '../../components/MovieTile/MovieTile.tsx'
import type { Review } from '../../components/ReviewCard/ReviewCard.tsx'
import { MOVIES, type Gradient } from '../Home/homeData.ts'
import { MY_LISTS, PUBLIC_LISTS } from '../Lists/listsData.ts'

// Mock content from the Figma "Profile — Public (Lina S.)" frame. Replace with the users / follows API.

/** Handle of the signed-in user until auth exists (`/profile` shows this profile). */
export const ME = 'maya'

export type Member = {
  handle: string
  name: string
  initials: string
  /** Avatar background, e.g. 'var(--accent-coral)'. */
  color: string
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
  /** Shown as e.g. "2.4k"; `followers` is the exact count used for the follow button. */
  followers: number
  following: number
  reviews: number
  lists: number
  films: number
  activity: ActivityItem[]
  reviewList: Review[]
  listCards: ProfileList[]
  watched: Movie[]
  top4: Movie[]
  genres: string[]
  followerList: Member[]
  followingList: Member[]
}

const MEMBERS = {
  maya: { handle: 'maya', name: 'Maya Rivera', initials: 'MR', color: 'var(--accent-mint)' },
  linasaad: { handle: 'linasaad', name: 'Lina Saad', initials: 'LS', color: 'var(--accent-yellow)' },
  omark: { handle: 'omark', name: 'Omar Khalil', initials: 'OK', color: 'var(--accent-coral)' },
  jadn: { handle: 'jadn', name: 'Jad Nassar', initials: 'JN', color: 'var(--surface-violet-tint)' },
  ranah: { handle: 'ranah', name: 'Rana Haddad', initials: 'RH', color: 'var(--accent-yellow)' },
} satisfies Record<string, Member>

/** Who the signed-in user follows; drives the Follow / Unfollow buttons. */
export const MY_FOLLOWING = ['linasaad', 'omark']

const rainyDay = PUBLIC_LISTS.find((list) => list.slug === 'rainy-day-comfort')!
const RAINY_DAY: ProfileList = {
  slug: rainyDay.slug,
  title: rainyDay.title,
  meta: `${rainyDay.films} films · ${rainyDay.likes} likes`,
  posters: [MOVIES.velvetHour, MOVIES.deepBlue, MOVIES.stardust, MOVIES.lastLap, MOVIES.paperMoon].map((m) => m.gradient),
}

const VELVET_REVIEW: Review = {
  id: 'lina-velvet-hour',
  initials: 'LS',
  color: 'var(--accent-yellow)',
  name: 'Lina Saad',
  date: 'Mar 8, 2026',
  rating: 5,
  sentiment: 'positive',
  body: 'Warm, gentle and quietly devastating. The last ten minutes wrecked me in the best possible way.',
  helpful: 41,
}

const PROFILES: Record<string, Profile> = {
  linasaad: {
    ...MEMBERS.linasaad,
    location: 'Beirut',
    since: '2023',
    bio: "Slow cinema evangelist, rainy-day list maker and professional popcorn critic. If it has a melancholy soundtrack, I've probably reviewed it.",
    followers: 2412,
    following: 318,
    reviews: 214,
    lists: 12,
    films: 860,
    activity: [
      { id: 'a1', kind: 'rated', movie: MOVIES.deepBlue, rating: 5, time: '2 h ago' },
      { id: 'a2', kind: 'reviewed', movie: MOVIES.velvetHour, review: VELVET_REVIEW, time: 'Yesterday' },
      { id: 'a3', kind: 'list', list: RAINY_DAY, time: '2 days ago' },
      { id: 'a4', kind: 'watchlist', movie: MOVIES.stardust, more: 2, time: '3 days ago' },
    ],
    reviewList: [
      VELVET_REVIEW,
      {
        id: 'lina-deep-blue',
        initials: 'LS',
        color: 'var(--accent-yellow)',
        name: 'Lina Saad',
        date: 'Feb 21, 2026',
        rating: 5,
        sentiment: 'positive',
        body: 'Every frame is drenched in blue and dread. Tense, patient and beautifully scored.',
        helpful: 27,
      },
    ],
    listCards: [RAINY_DAY],
    watched: [MOVIES.deepBlue, MOVIES.velvetHour, MOVIES.stardust, MOVIES.wildFern, MOVIES.lastLap, MOVIES.paperMoon],
    top4: [MOVIES.velvetHour, MOVIES.deepBlue, MOVIES.stardust, MOVIES.wildFern],
    genres: ['Drama', 'Romance', 'Mystery', 'Slow cinema', 'Documentary'],
    followerList: [MEMBERS.maya, MEMBERS.omark, MEMBERS.jadn, MEMBERS.ranah],
    followingList: [MEMBERS.omark, MEMBERS.ranah, MEMBERS.jadn],
  },
  maya: {
    ...MEMBERS.maya,
    location: 'Beirut',
    since: '2024',
    bio: 'Sci-fi devotee. Will cry at any ocean movie.',
    followers: 186,
    following: 2,
    reviews: 38,
    lists: MY_LISTS.length,
    films: 142,
    activity: [
      { id: 'm1', kind: 'rated', movie: MOVIES.neonTide, rating: 5, time: '1 h ago' },
      { id: 'm2', kind: 'watchlist', movie: MOVIES.deepBlue, more: 1, time: 'Yesterday' },
    ],
    reviewList: [],
    listCards: MY_LISTS.map((list) => ({
      slug: list.slug,
      title: list.title,
      meta: `${list.films} films · ${list.isPublic ? 'Public' : 'Private'}`,
      posters: list.posters,
    })),
    watched: [MOVIES.neonTide, MOVIES.stardust, MOVIES.lastLap, MOVIES.paperMoon],
    top4: [MOVIES.neonTide, MOVIES.stardust, MOVIES.deepBlue, MOVIES.lastLap],
    genres: ['Sci-fi', 'Drama', 'Adventure'],
    followerList: [MEMBERS.linasaad, MEMBERS.omark, MEMBERS.jadn],
    followingList: [MEMBERS.linasaad, MEMBERS.omark],
  },
}

export function getProfile(handle: string | undefined): Profile | undefined {
  return PROFILES[handle ?? ME]
}

export function hasProfile(handle: string) {
  return handle in PROFILES
}

/** 2412 → "2.4k", 318 → "318". */
export function compactCount(n: number) {
  return n >= 1000 ? `${(n / 1000).toFixed(1).replace(/\.0$/, '')}k` : String(n)
}
