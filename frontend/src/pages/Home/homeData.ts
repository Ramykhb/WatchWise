import type { Movie } from '../../components/MovieTile/MovieTile.tsx'

// Mock content from the Figma Home frame. Replace with API data once the backend is connected.

export type Gradient = [string, string, string]

const G = {
  neonTide: ['#ff995c', '#ed4d6b', '#5c298c'],
  stardust: ['#ccbfff', '#8c66d9', '#331a66'],
  deepBlue: ['#8cccff', '#4066d9', '#1a144d'],
  lastLap: ['#7de0c2', '#338cb2', '#211a5c'],
  velvetHour: ['#fa8cbf', '#9940b2', '#2b1a63'],
  wildFern: ['#bfeb8c', '#4d9973', '#143338'],
  paperMoon: ['#ffe073', '#ff8c59', '#99264d'],
  redSignal: ['#ffbf66', '#d94033', '#400d26'],
} satisfies Record<string, Gradient>

export const MOVIES = {
  neonTide: { slug: 'neon-tide', title: 'Neon Tide', posterTitle: ['NEON', 'TIDE'], meta: '2026 · Sci-fi', score: '4.8', gradient: G.neonTide },
  stardust: { slug: 'stardust', title: 'Stardust', posterTitle: ['STAR', 'DUST'], meta: '2025 · Fantasy', score: '4.7', gradient: G.stardust },
  deepBlue: { slug: 'deep-blue', title: 'Deep Blue', posterTitle: ['DEEP', 'BLUE'], meta: '2025 · Thriller', score: '4.4', gradient: G.deepBlue },
  lastLap: { slug: 'last-lap', title: 'Last Lap', posterTitle: ['LAST', 'LAP'], meta: '2025 · Drama', score: '4.5', gradient: G.lastLap },
  velvetHour: { slug: 'velvet-hour', title: 'Velvet Hour', posterTitle: ['VELVET', 'HOUR'], meta: '2026 · Romance', score: '4.3', gradient: G.velvetHour },
  wildFern: { slug: 'wild-fern', title: 'Wild Fern', posterTitle: ['WILD', 'FERN'], meta: '2024 · Adventure', score: '4.2', gradient: G.wildFern },
  paperMoon: { slug: 'paper-moon', title: 'Paper Moon', posterTitle: ['PAPER', 'MOON'], meta: '2026 · Comedy', score: '4.6', gradient: G.paperMoon },
  redSignal: { slug: 'red-signal', title: 'Red Signal', posterTitle: ['RED', 'SIGNAL'], meta: '2026 · Action', score: '4.1', gradient: G.redSignal },
} satisfies Record<string, Movie>

export const TONIGHTS_PICK = {
  movie: MOVIES.deepBlue,
  reason: 'picked for tonight',
  details: '2025 · Thriller · 1h 58m · ★ 4.4',
}

export const BECAUSE_YOU_LOVED = {
  title: 'Because you loved Neon Tide',
  reasons: ['Sci-fi fan', 'Directed by Lena Okafor', 'Rated ★5 by people you follow'],
  movies: [MOVIES.stardust, MOVIES.deepBlue, MOVIES.lastLap, MOVIES.velvetHour, MOVIES.wildFern, MOVIES.paperMoon],
}

export const POPULAR = [MOVIES.neonTide, MOVIES.redSignal, MOVIES.paperMoon, MOVIES.stardust, MOVIES.velvetHour, MOVIES.lastLap]

export const RELEASES = [
  { title: 'Velvet Hour II', meta: 'Romance · in cinemas', month: 'MAR', day: '14', gradient: G.velvetHour },
  { title: 'Red Signal: Zero', meta: 'Action · streaming', month: 'APR', day: '02', gradient: G.redSignal },
  { title: 'Wild Fern Returns', meta: 'Adventure · in cinemas', month: 'APR', day: '19', gradient: G.wildFern },
]

export type Activity = {
  initials: string
  color: string
  name: string
  action: string
  target: string
  suffix?: string
  time: string
  movie: Movie
}

export const ACTIVITY: Activity[] = [
  { initials: 'OK', color: 'var(--accent-coral)', name: 'Omar K.', action: 'rated', target: 'Paper Moon', suffix: '★★★★★', time: '12 min ago', movie: MOVIES.paperMoon },
  { initials: 'LS', color: 'var(--accent-yellow)', name: 'Lina S.', action: 'created the public list', target: '“Rainy Day Comfort”', time: '1 h ago', movie: MOVIES.velvetHour },
  { initials: 'JN', color: 'var(--accent-mint)', name: 'Jad N.', action: 'reviewed', target: 'Deep Blue', suffix: '— “Tense, gorgeous, unforgettable.”', time: '3 h ago', movie: MOVIES.deepBlue },
  { initials: 'RH', color: 'var(--surface-violet-tint)', name: 'Rana H.', action: 'added', target: 'Stardust', suffix: 'to her watchlist', time: 'Yesterday', movie: MOVIES.stardust },
]

export const ROOMS = [
  { name: 'Neon Tide — ending explained', online: 128 },
  { name: 'Thriller Thursday', online: 64 },
  { name: 'Oscars 2026 predictions', online: 312 },
]

export const MOVIE_NIGHT = {
  when: 'Fri, Mar 14 · 8:30 PM',
  title: 'Friday Frights',
  text: 'Voting closes tomorrow. Deep Blue is leading with 5 votes.',
  guests: [
    { initials: 'MR', color: 'var(--accent-mint)' },
    { initials: 'OK', color: 'var(--accent-coral)' },
    { initials: 'LS', color: 'var(--accent-yellow)' },
  ],
  extraGuests: 3,
}

export const gradientCss = ([top, middle, bottom]: Gradient) => `linear-gradient(180deg, ${top} 0%, ${middle} 50%, ${bottom} 100%)`
