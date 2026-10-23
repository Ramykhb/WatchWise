import type { Movie } from '../../components/MovieTile/MovieTile.tsx'
import { MOVIES } from '../Home/homeData.ts'

// Mock content from the Figma "Nearby Cinemas — Map" frame. Replace with a maps / showtimes API
// (called through the backend, like the weather endpoint).

export type Feature = 'tonight' | 'imax' | '4dx' | 'arabic'

export const FEATURES: { id: Feature; label: string }[] = [
  { id: 'tonight', label: 'Tonight' },
  { id: 'imax', label: 'IMAX' },
  { id: '4dx', label: '4DX' },
  { id: 'arabic', label: 'Arabic subs' },
]

/** Position on the 896×884 Figma map, in px. */
export type MapPoint = { x: number; y: number }

export type Cinema = {
  id: string
  name: string
  /** Short name on the map pin. */
  pin: string
  address: string
  distance: string
  drive: string
  rating: string
  times: string[]
  features: Feature[]
  at: MapPoint
}

export type FilmLocation = {
  id: string
  name: string
  pin: string
  place: string
  movie: Movie
  scene: string
  at: MapPoint
}

export const MAP_SIZE = { width: 896, height: 884 }
/** "You" on the map; zoom is centred here. */
export const YOU: MapPoint = { x: 400, y: 500 }

export const DEFAULT_LOCATION = 'Hamra, Beirut'

export const CINEMAS: Cinema[] = [
  {
    id: 'grand-abc',
    name: 'Grand Cinemas ABC',
    pin: 'Grand ABC',
    address: 'ABC Verdun, Beirut',
    distance: '1.2 km',
    drive: '4 min drive',
    rating: '4.6',
    times: ['6:15', '8:40', '10:20'],
    features: ['tonight', 'imax', 'arabic'],
    at: { x: 430, y: 400 },
  },
  {
    id: 'vox-city-centre',
    name: 'VOX Cinemas City Centre',
    pin: 'VOX',
    address: 'Hazmieh',
    distance: '3.4 km',
    drive: '9 min drive',
    rating: '4.5',
    times: ['7:00', '9:30'],
    features: ['tonight', 'imax', '4dx'],
    at: { x: 600, y: 580 },
  },
  {
    id: 'empire-sodeco',
    name: 'Empire Sodeco',
    pin: 'Empire',
    address: 'Sodeco Square',
    distance: '4.1 km',
    drive: '11 min drive',
    rating: '4.2',
    times: ['8:15'],
    features: ['tonight', 'arabic'],
    at: { x: 160, y: 250 },
  },
]

export const LOCATIONS: FilmLocation[] = [
  {
    id: 'raouche-seafront',
    name: 'Raouché seafront',
    pin: 'Filming spot',
    place: 'Raouché, Beirut · 2.3 km',
    movie: MOVIES.neonTide,
    scene: 'The opening night-swim scene',
    at: { x: 250, y: 140 },
  },
  {
    id: 'sanayeh-garden',
    name: 'Sanayeh Garden',
    pin: 'Filming spot',
    place: 'Sanayeh, Beirut · 0.9 km',
    movie: MOVIES.velvetHour,
    scene: 'The first-date picnic',
    at: { x: 200, y: 640 },
  },
]

/** Films you can pick showtimes for ("Showing … Change"). */
export const SHOWING: Movie[] = [MOVIES.neonTide, MOVIES.deepBlue, MOVIES.paperMoon]
