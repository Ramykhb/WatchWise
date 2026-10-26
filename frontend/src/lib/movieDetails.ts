import { useEffect, useState } from 'react'
import type { Movie } from '../components/MovieTile/MovieTile.tsx'
import { genreLabel, toTileMovie, type TmdbMovie } from '../pages/Discover/discoverApi.ts'
import { api } from './api.ts'
import { isDemoId, movieFor, tmdbIdOf } from './movies.ts'

// Films saved by TMDB id (a list's films, …) only come back from the API as ids. This loads their
// title, year and poster from GET /api/movies/{id}, once per film, and keeps them in this browser for
// a week so lists show their posters straight away on the next visit.

type TmdbMovieDetails = TmdbMovie & { genres: { id: number; name: string }[] }

const STORAGE_KEY = 'watchwise.movies'
const STORAGE_DAYS = 7
/** Keeps the stored films well under the browser's storage limit. */
const STORAGE_MAX = 400

/** Films already known in full (loaded, stored, or picked from a search), so pages can show them at once. */
const known = new Map<number, Movie>(readStored())
const requests = new Map<number, Promise<Movie>>()

function readStored(): [number, Movie][] {
  try {
    const stored = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? 'null') as { savedAt: number; movies: [number, Movie][] } | null
    if (!stored || Date.now() - stored.savedAt > STORAGE_DAYS * 86_400_000) return []
    return stored.movies
  } catch {
    return []
  }
}

let storeTimer = 0
function remember(tmdbId: number, movie: Movie) {
  known.set(tmdbId, movie)
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

/** Keeps a film the user already has in full (e.g. picked from search), so it never needs loading. */
export function rememberMovie(movie: Movie) {
  remember(tmdbIdOf(movie), movie)
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

function loadMovie(tmdbId: number): Promise<Movie> {
  let request = requests.get(tmdbId)
  if (!request) {
    request = queued(() => api.get<TmdbMovieDetails>(`/movies/${tmdbId}`))
      .then(({ data }) => {
        const movie = toTileMovie(data, new Map(data.genres.map((genre) => [genre.id, genreLabel(genre.name)])))
        remember(tmdbId, movie)
        return movie
      })
      .catch((error: unknown) => {
        // Let the next page that needs this film try again.
        requests.delete(tmdbId)
        throw error
      })
    requests.set(tmdbId, request)
  }
  return request
}

const loadingMovies = new WeakSet<Movie>()

/** Shown while a film's details load. */
function loading(tmdbId: number): Movie {
  const movie = { ...movieFor(tmdbId), title: 'Loading…', posterTitle: ['', ''] as [string, string], meta: '' }
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
 * Loads the real details of `tmdbIds` and returns a lookup: a known film, "Loading…" while it loads,
 * otherwise `fallback` (or the id's placeholder when TMDB can't be reached). Demo ids need no request.
 * With `load` false nothing is requested yet (e.g. a card still off screen); its films show as loading.
 */
export function useMovies(tmdbIds: number[], load = true) {
  const [loaded, setLoaded] = useState<Map<number, Movie>>(new Map())
  const [failed, setFailed] = useState<Set<number>>(new Set())
  const key = tmdbIds.join(',')

  useEffect(() => {
    if (!load) return
    let active = true
    for (const id of new Set(key.split(',').filter(Boolean).map(Number))) {
      if (isDemoId(id) || known.has(id)) continue
      loadMovie(id)
        .then((movie) => active && setLoaded((current) => new Map(current).set(id, movie)))
        .catch(() => active && setFailed((current) => new Set(current).add(id)))
    }
    return () => {
      active = false
    }
  }, [key, load])

  return (tmdbId: number, fallback?: Movie): Movie => {
    const movie = loaded.get(tmdbId) ?? known.get(tmdbId)
    if (movie) return movie
    if (tmdbIds.includes(tmdbId) && !isDemoId(tmdbId) && !failed.has(tmdbId)) return loading(tmdbId)
    return fallback ?? movieFor(tmdbId)
  }
}
