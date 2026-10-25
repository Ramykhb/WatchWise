import { useEffect, useState } from 'react'
import type { Movie } from '../components/MovieTile/MovieTile.tsx'
import { genreLabel, toTileMovie, type TmdbMovie } from '../pages/Discover/discoverApi.ts'
import { api } from './api.ts'
import { isDemoId, movieFor, tmdbIdOf } from './movies.ts'

// Films saved by TMDB id (a list's films, …) only come back from the API as ids. This loads their
// title, year and poster from GET /api/movies/{id}, once per film while the app is open.

type TmdbMovieDetails = TmdbMovie & { genres: { id: number; name: string }[] }

/** Films already known in full (loaded, or picked from a search), so pages can show them at once. */
const known = new Map<number, Movie>()
const requests = new Map<number, Promise<Movie>>()

/** Keeps a film the user already has in full (e.g. picked from search), so it never needs loading. */
export function rememberMovie(movie: Movie) {
  known.set(tmdbIdOf(movie), movie)
}

function loadMovie(tmdbId: number): Promise<Movie> {
  let request = requests.get(tmdbId)
  if (!request) {
    request = api
      .get<TmdbMovieDetails>(`/movies/${tmdbId}`)
      .then(({ data }) => {
        const movie = toTileMovie(data, new Map(data.genres.map((genre) => [genre.id, genreLabel(genre.name)])))
        known.set(tmdbId, movie)
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

/** Shown while a film's details load. */
const loading = (tmdbId: number): Movie => ({ ...movieFor(tmdbId), title: 'Loading…', posterTitle: ['', ''], meta: '' })

/**
 * Loads the real details of `tmdbIds` and returns a lookup: a known film, "Loading…" while it loads,
 * otherwise `fallback` (or the id's placeholder when TMDB can't be reached). Demo ids need no request.
 */
export function useMovies(tmdbIds: number[]) {
  const [loaded, setLoaded] = useState<Map<number, Movie>>(new Map())
  const [failed, setFailed] = useState<Set<number>>(new Set())
  const key = tmdbIds.join(',')

  useEffect(() => {
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
  }, [key])

  return (tmdbId: number, fallback?: Movie): Movie => {
    const movie = loaded.get(tmdbId) ?? known.get(tmdbId)
    if (movie) return movie
    if (tmdbIds.includes(tmdbId) && !isDemoId(tmdbId) && !failed.has(tmdbId)) return loading(tmdbId)
    return fallback ?? movieFor(tmdbId)
  }
}
