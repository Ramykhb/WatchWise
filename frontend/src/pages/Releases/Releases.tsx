import { useEffect, useRef, useState } from 'react'
import AppNav from '../../components/AppNav/AppNav.tsx'
import MovieTile from '../../components/MovieTile/MovieTile.tsx'
import PillButton from '../../components/PillButton/PillButton.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import { api, useApi } from '../../lib/api.ts'
import { loadGenres, toTileMovie, type MoviePage, type TmdbMovie } from '../Discover/discoverApi.ts'
import { ResultsSkeleton } from '../Discover/Discover.tsx'
import discoverStyles from '../Discover/Discover.module.css'
import { upcomingReleases } from '../Home/homeData.ts'
import homeStyles from '../Home/Home.module.css'
import settingsStyles from '../Settings/Settings.module.css'

/** "All releases →" from Home: every upcoming film TMDB lists, soonest first, by month, with more on demand. */
export default function Releases() {
  const pageRef = useRef<HTMLDivElement>(null)
  useEntranceAnimation(pageRef)
  const { data, error, loading, reload } = useApi<MoviePage>('/movies?list=upcoming&sort=release_date')
  // Pages loaded after the first one with "Show more releases".
  const [more, setMore] = useState<{ movies: TmdbMovie[]; page: number }>({ movies: [], page: 1 })
  const [loadingMore, setLoadingMore] = useState(false)
  const [moreFailed, setMoreFailed] = useState(false)
  // Genre names for the meta line. Loaded on the side: the releases never wait for them.
  const [genres, setGenres] = useState<Map<number, string>>(() => new Map())

  useEffect(() => {
    let active = true
    loadGenres()
      .then((map) => active && setGenres(map))
      // Without the names, a release just says when it comes out.
      .catch((error: unknown) => console.error('Unable to load genre names:', error))
    return () => {
      active = false
    }
  }, [])

  // TMDB can list a film on two pages; keep it once.
  const movies = data ? [...data.movies, ...more.movies].filter((movie, index, list) => list.findIndex((other) => other.id === movie.id) === index) : []
  const byId = new Map(movies.map((movie) => [movie.id, movie]))
  const releases = data ? upcomingReleases(movies, genres, Infinity) : null
  const hasMore = data ? more.page < data.total_pages : false

  const months: { label: string; releases: NonNullable<typeof releases> }[] = []
  for (const release of releases ?? []) {
    const label = release.date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
    const last = months.at(-1)
    if (last?.label === label) last.releases.push(release)
    else months.push({ label, releases: [release] })
  }

  const loadMore = async () => {
    setLoadingMore(true)
    setMoreFailed(false)
    try {
      const { data: next } = await api.get<MoviePage>('/movies', { params: { list: 'upcoming', sort: 'release_date', page: more.page + 1 } })
      setMore((current) => ({ movies: [...current.movies, ...next.movies], page: next.page }))
    } catch {
      setMoreFailed(true)
    } finally {
      setLoadingMore(false)
    }
  }

  return (
    <div ref={pageRef} className={homeStyles.page}>
      <AppNav />

      <main className={homeStyles.main}>
        <header className={settingsStyles.header} data-enter data-enter-delay="0.05">
          <SplitHeadline className={settingsStyles.title} lines={[{ text: 'Coming soon' }]} />
          <p className={settingsStyles.subtitle}>The next films in cinemas, soonest first. Open one to add it to your watchlist.</p>
        </header>

        {error && !loading ? (
          <p className={homeStyles.releasesState} role="status">
            Upcoming releases are temporarily unavailable.{' '}
            <button type="button" onClick={reload}>
              Try again
            </button>
          </p>
        ) : !releases ? (
          <ResultsSkeleton view="grid" />
        ) : releases.length === 0 && !hasMore ? (
          <p className={homeStyles.releasesState} role="status">
            No upcoming releases right now.
          </p>
        ) : (
          months.map((month, monthIndex) => (
            <section key={month.label} className={homeStyles.section} aria-labelledby={`releases-${monthIndex}`} data-enter data-enter-delay="0.15">
              <h2 id={`releases-${monthIndex}`} className={homeStyles.sectionTitle}>
                {month.label}
              </h2>
              <div className={discoverStyles.grid}>
                {month.releases.map((release) => {
                  const movie = byId.get(release.id)
                  // The same poster card as Discover, with the release date where the year would be.
                  return movie ? (
                    <MovieTile
                      key={release.id}
                      movie={{ ...toTileMovie(movie, genres), meta: `${release.date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} · ${release.meta}` }}
                    />
                  ) : null
                })}
              </div>
            </section>
          ))
        )}

        {releases && hasMore && (
          <div className={homeStyles.sectionHeader}>
            <PillButton variant="outline" onClick={() => void loadMore()} disabled={loadingMore}>
              {loadingMore ? 'Loading…' : 'Show more releases'}
            </PillButton>
            {moreFailed && (
              <p className={homeStyles.releasesState} role="alert">
                Couldn’t load more releases. Try again.
              </p>
            )}
          </div>
        )}
      </main>
    </div>
  )
}
