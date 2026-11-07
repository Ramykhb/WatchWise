import { useEffect, useRef, useState } from 'react'
import AppNav from '../../components/AppNav/AppNav.tsx'
import MovieTile from '../../components/MovieTile/MovieTile.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import { useApi } from '../../lib/api.ts'
import { loadGenres, toTileMovie, type MoviePage } from '../Discover/discoverApi.ts'
import { ResultsSkeleton } from '../Discover/Discover.tsx'
import discoverStyles from '../Discover/Discover.module.css'
import { upcomingReleases } from '../Home/homeData.ts'
import homeStyles from '../Home/Home.module.css'
import settingsStyles from '../Settings/Settings.module.css'
import { upcomingReleaseParams } from './releasesApi.ts'

/** "All releases →" from Home: one page of popular upcoming films, grouped by month. */
export default function Releases() {
  const pageRef = useRef<HTMLDivElement>(null)
  useEntranceAnimation(pageRef)
  const [releaseParams] = useState(() => upcomingReleaseParams())
  const { data, error, loading, reload } = useApi<MoviePage>(`/movies?${new URLSearchParams(releaseParams)}`)
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

  const movies = data?.movies ?? []
  const byId = new Map(movies.map((movie) => [movie.id, movie]))
  const releases = data ? upcomingReleases(movies, genres, Infinity) : null

  const months: { label: string; releases: NonNullable<typeof releases> }[] = []
  for (const release of releases ?? []) {
    const label = release.date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
    const last = months.at(-1)
    if (last?.label === label) last.releases.push(release)
    else months.push({ label, releases: [release] })
  }

  return (
    <div ref={pageRef} className={homeStyles.page}>
      <AppNav />

      <main className={homeStyles.main}>
        <header className={settingsStyles.header} data-enter data-enter-delay="0.05">
          <SplitHeadline className={settingsStyles.title} lines={[{ text: 'Coming soon' }]} />
          <p className={settingsStyles.subtitle}>The most anticipated upcoming films, grouped by release month. Open one to add it to your watchlist.</p>
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
        ) : releases.length === 0 ? (
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
      </main>
    </div>
  )
}
