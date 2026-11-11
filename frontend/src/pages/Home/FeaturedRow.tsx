import { useRef } from 'react'
import { ChevronLeft, ChevronRight } from 'lucide-react'
import MovieTile from '../../components/MovieTile/MovieTile.tsx'
import { toTileMovie, type TmdbMovie } from '../Discover/discoverApi.ts'
import styles from './WeeklyTrending.module.css'

const NO_GENRES = new Map<number, string>()

/**
 * The films the admins feature on Home. They arrive with the Home response, so while it loads, if it fails
 * (Following activity already offers the retry) or when nothing is featured, the row is not shown at all.
 */
export default function FeaturedRow({ movies }: { movies: TmdbMovie[] | undefined }) {
  const rowRef = useRef<HTMLOListElement>(null)
  if (!movies || movies.length === 0) return null

  const scroll = (direction: number) => {
    const row = rowRef.current
    if (!row) return
    row.scrollBy({
      left: direction * row.clientWidth * 0.8,
      behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
    })
  }

  return (
    <section className={styles.section} aria-labelledby="home-featured-title">
      <div className={styles.header}>
        <div>
          <span className={styles.eyebrow}>HANDPICKED</span>
          <h2 id="home-featured-title" className={styles.title}>Featured</h2>
          <p className={styles.subtitle}>Chosen by the WatchWise team</p>
        </div>
        <div className={styles.controls}>
          <button type="button" aria-label="Scroll featured movies left" aria-controls="home-featured" onClick={() => scroll(-1)}>
            <ChevronLeft size={20} aria-hidden="true" />
          </button>
          <button type="button" aria-label="Scroll featured movies right" aria-controls="home-featured" onClick={() => scroll(1)}>
            <ChevronRight size={20} aria-hidden="true" />
          </button>
        </div>
      </div>
      <ol ref={rowRef} id="home-featured" className={styles.row} aria-label="Featured" tabIndex={0}>
        {movies.map((movie) => (
          <li key={movie.id} className={`${styles.card} ${styles.unranked}`}>
            <MovieTile movie={toTileMovie(movie, NO_GENRES)} />
          </li>
        ))}
      </ol>
    </section>
  )
}
