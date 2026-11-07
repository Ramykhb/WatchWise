import type { TmdbMovie } from '../Discover/discoverApi.ts'

export type WeatherRecommendation = TmdbMovie & { reason: string }
export type PickLocation = { latitude: number; longitude: number }
type PickCache = PickLocation & { cachedAt: number; movie: WeatherRecommendation }

const CACHE_KEY = 'watchwise:tonights-pick:v1'
export const PICK_CACHE_TTL = 60 * 60 * 1000

/** Keep the chosen film, rather than choosing again from a cached list on each visit. */
export function readTonightPick(location?: PickLocation): PickCache | null {
  if (!location) return null
  try {
    const raw = localStorage.getItem(CACHE_KEY)
    if (!raw) return null
    const cache = JSON.parse(raw) as Partial<PickCache>
    const age = Date.now() - (cache.cachedAt ?? 0)
    const movie = cache.movie
    if (
      typeof cache.cachedAt !== 'number' || !Number.isFinite(cache.cachedAt) || age < 0 || age >= PICK_CACHE_TTL ||
      cache.latitude !== location.latitude || cache.longitude !== location.longitude ||
      !movie || typeof movie.id !== 'number' || typeof movie.title !== 'string' ||
      typeof movie.reason !== 'string' || !Array.isArray(movie.genre_ids)
    ) {
      localStorage.removeItem(CACHE_KEY)
      return null
    }
    return cache as PickCache
  } catch {
    try { localStorage.removeItem(CACHE_KEY) } catch { /* Storage may be unavailable. */ }
    return null
  }
}

export function cacheTonightPick(movie: WeatherRecommendation, location: PickLocation): PickCache {
  const cache = { ...location, cachedAt: Date.now(), movie }
  try {
    localStorage.setItem(CACHE_KEY, JSON.stringify(cache))
  } catch { /* Keep the pick usable if browser storage is unavailable. */ }
  return cache
}
