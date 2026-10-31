import { useRef } from 'react'
import { ChevronLeft, ChevronRight } from 'lucide-react'
import MovieTile from '../../components/MovieTile/MovieTile.tsx'
import { useApi } from '../../lib/api.ts'
import { toTileMovie, type MoviePage } from '../Discover/discoverApi.ts'
import styles from './WeeklyTrending.module.css'

const NO_GENRES = new Map<number, string>()

function TrendingRow({ type, title }: { type: 'movie' | 'tv'; title: string }) {
  const { data, error, loading, reload } = useApi<MoviePage>(`/movies/trending?type=${type}`)
  const rowRef = useRef<HTMLOListElement>(null)
  const rowId = `weekly-trending-${type}`
  const titles = data?.movies.slice(0, 10)
  const scroll = (direction: number) => {
    const row = rowRef.current
    if (!row) return
    row.scrollBy({
      left: direction * row.clientWidth * 0.8,
      behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
    })
  }

  return (
    <section className={styles.section} aria-labelledby={`${rowId}-title`}>
      <div className={styles.header}>
        <div>
          <span className={styles.eyebrow}>THE WEEKLY TOP 10</span>
          <h2 id={`${rowId}-title`} className={styles.title}>{title}</h2>
          <p className={styles.subtitle}>Trending on TMDB this week</p>
        </div>
        <div className={styles.controls}>
          <button type="button" aria-label={`Scroll ${type === 'tv' ? 'TV show' : 'movie'} rankings left`} aria-controls={rowId} onClick={() => scroll(-1)}>
            <ChevronLeft size={20} aria-hidden="true" />
          </button>
          <button type="button" aria-label={`Scroll ${type === 'tv' ? 'TV show' : 'movie'} rankings right`} aria-controls={rowId} onClick={() => scroll(1)}>
            <ChevronRight size={20} aria-hidden="true" />
          </button>
        </div>
      </div>
      {error && !loading ? (
        <div className={styles.state} role="status">
          <p>This week’s rankings are temporarily unavailable.</p>
          <button type="button" onClick={reload}>Try again</button>
        </div>
      ) : titles?.length === 0 ? (
        <p className={styles.state} role="status">No trending titles are available yet.</p>
      ) : (
        <ol ref={rowRef} id={rowId} className={styles.row} aria-label={title} aria-busy={!titles} tabIndex={0}>
          {titles ? titles.map((movie, index) => (
            <li key={`${movie.media_type}-${movie.id}`} className={styles.card}>
              <span className={styles.rank} aria-hidden="true">{index + 1}</span>
              <span className={styles.srOnly}>Rank {index + 1}</span>
              <MovieTile movie={toTileMovie(movie, NO_GENRES)} />
            </li>
          )) : Array.from({ length: 10 }, (_, index) => (
            <li key={index} className={styles.card} aria-hidden="true">
              <div className={styles.skeletonPoster} />
              <div className={styles.skeletonTitle} />
              <div className={styles.skeletonMeta} />
            </li>
          ))}
        </ol>
      )}
    </section>
  )
}

export default function WeeklyTrending() {
  return (
    <>
      <TrendingRow type="movie" title="Popular movies this week" />
      <TrendingRow type="tv" title="Popular TV shows this week" />
    </>
  )
}
