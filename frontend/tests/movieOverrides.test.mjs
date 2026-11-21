import assert from 'node:assert/strict'
import { mock, test } from 'node:test'
mock.module('../src/lib/api.ts', { namedExports: { api: {} } })
const { toDetails } = await import('../src/pages/MovieDetails/movieApi.ts')

test('local director names render without inventing a TMDB person link', () => {
  const movie = {
    id: 550, media_type: 'movie', title: 'Local title', overview: 'Local overview', release_date: null, year: null,
    rating: 4, vote_count: 100, genre_ids: [35], genres: [{ id: 35, name: 'Comedy' }],
    poster_url: null, backdrop_url: null, tagline: null, runtime: null, status: 'Released', tmdb_score: 8,
    original_language: null, certification: null, budget: null, revenue: null,
    directors: [{ id: null, name: 'Local Director' }], cast: [{ id: 43, name: 'Actor', character: 'Hero', profile_url: null }], trailer: null, similar: [],
  }
  const details = toDetails(movie, new Map([[35, 'Comedy']]))
  assert.equal(details.movie.title, 'Local title')
  assert.equal(details.director, 'Local Director')
  assert.equal(details.cast[0].slug, '')
  assert.equal(details.cast[1].slug, '43')
  assert.equal(details.releaseDate, null)
  assert.equal(details.runtimeMinutes, null)
})
