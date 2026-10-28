import { useEffect, useState } from 'react'
import type { Movie } from '../components/MovieTile/MovieTile.tsx'
import { genreLabel, toTileMovie, type TmdbMovie } from '../pages/Discover/discoverApi.ts'
import { api } from './api.ts'
import { filmKeyOf, isDemoId, movieForKey, type FilmKey } from './movies.ts'

// Titles saved by TMDB id (a list's movies and shows, …) only come back from the API as ids. This loads
// their title, year and poster from GET /api/movies/{id} (?type=tv for shows), once per title, and keeps
// them in this browser for a week so lists show their posters straight away on the next visit.

type TmdbMovieDetails = TmdbMovie & { genres: { id: number; name: string }[] }

const STORAGE_KEY = 'watchwise.movies'
const STORAGE_DAYS = 7
/** Keeps the stored films well under the browser's storage limit. */
const STORAGE_MAX = 400

/** Titles already known in full (loaded, stored, or picked from a search), by key ("550", "tv-1399"). */
const known = new Map<string, Movie>(readStored())
const requests = new Map<string, Promise<Movie>>()

function readStored(): [string, Movie][] {
  try {
    const stored = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? 'null') as { savedAt: number; movies: [FilmKey, Movie][] } | null
    if (!stored || Date.now() - stored.savedAt > STORAGE_DAYS * 86_400_000) return []
    return stored.movies.map(([key, movie]) => [String(key), movie])
  } catch {
    return []
  }
}

/** Demo movies (1001–1008) are known locally and never loaded. */
const isDemo = (key: FilmKey) => typeof key === 'number' && isDemoId(key)

/** "tv-1399" back to a key the API understands (a plain number for movies). */
const toKey = (part: string): FilmKey => (part.startsWith('tv-') ? part : Number(part))

let storeTimer = 0
function remember(key: FilmKey, movie: Movie) {
  known.set(String(key), movie)
  // Batched, so a page loading many films writes once.
  window.clearTimeout(storeTimer)
  storeTimer = window.setTimeout(() => {
    try {
      const movies = [...known].slice(-STORAGE_MAX)
      localStorage.setItem(STORAGE_KEY, JSON.stringify({ savedAt: Date.now(), movies }))
    } catch {
      // Storage full or blocked: the films still load from the API.
    }
  }, 500)
}

/** Keeps a title the user already has in full (e.g. picked from search), so it never needs loading. */
export function rememberMovie(movie: Movie) {
  remember(filmKeyOf(movie), movie)
}

/** At most this many film requests run at once; the rest wait their turn, so a page can't flood the API. */
const MAX_PARALLEL = 4
let running = 0
const waiting: (() => void)[] = []

function queued<T>(task: () => Promise<T>): Promise<T> {
  return new Promise<T>((resolve, reject) => {
    const run = () => {
      running++
      task()
        .then(resolve, reject)
        .finally(() => {
          running--
          waiting.shift()?.()
        })
    }
    if (running < MAX_PARALLEL) run()
    else waiting.push(run)
  })
}

function loadMovie(key: FilmKey): Promise<Movie> {
  const id = String(key)
  let request = requests.get(id)
  if (!request) {
    const isTv = id.startsWith('tv-')
    request = queued(() =>
      api.get<TmdbMovieDetails>(`/movies/${isTv ? id.slice('tv-'.length) : id}`, { params: isTv ? { type: 'tv' } : undefined }),
    )
      .then(({ data }) => {
        const movie = toTileMovie(data, new Map(data.genres.map((genre) => [genre.id, genreLabel(genre.name)])))
        remember(key, movie)
        return movie
      })
      .catch((error: unknown) => {
        // Let the next page that needs this film try again.
        requests.delete(id)
        throw error
      })
    requests.set(id, request)
  }
  return request
}

const loadingMovies = new WeakSet<Movie>()

/** Shown while a film's details load. */
function loading(key: FilmKey): Movie {
  const movie = { ...movieForKey(key), title: 'Loading…', posterTitle: ['', ''] as [string, string], meta: '' }
  loadingMovies.add(movie)
  return movie
}

/** True for the placeholder `useMovies` returns while a film's details load. */
export const isLoading = (movie: Movie) => loadingMovies.has(movie)

/**
 * The same TMDB poster in a smaller size (e.g. "w185"), for thumbnails: it downloads much faster than
 * the full poster the API returns. Other URLs are returned as they are.
 */
export const posterAt = (url: string | undefined, size: 'w92' | 'w185' | 'w342') => url?.replace(/\/t\/p\/w\d+\//, `/t/p/${size}/`)

/**
 * Loads the real details of these titles (movie ids, or "tv-1399" for shows) and returns a lookup: a
 * known title, "Loading…" while it loads, otherwise `fallback` (or the key's placeholder when TMDB can't
 * be reached). Demo ids need no request. With `load` false nothing is requested yet (e.g. a card still
 * off screen); its titles show as loading.
 */
export function useMovies(keys: FilmKey[], load = true) {
  const [loaded, setLoaded] = useState<Map<string, Movie>>(new Map())
  const [failed, setFailed] = useState<Set<string>>(new Set())
  const joined = keys.join(',')

  useEffect(() => {
    if (!load) return
    let active = true
    for (const part of new Set(joined.split(',').filter(Boolean))) {
      const key = toKey(part)
      if (isDemo(key) || known.has(part)) continue
      loadMovie(key)
        .then((movie) => active && setLoaded((current) => new Map(current).set(part, movie)))
        .catch(() => active && setFailed((current) => new Set(current).add(part)))
    }
    return () => {
      active = false
    }
  }, [joined, load])

  return (key: FilmKey, fallback?: Movie): Movie => {
    const id = String(key)
    const movie = loaded.get(id) ?? known.get(id)
    if (movie) return movie
    if (keys.some((wanted) => String(wanted) === id) && !isDemo(key) && !failed.has(id)) return loading(key)
    return fallback ?? movieForKey(key)
  }
}
