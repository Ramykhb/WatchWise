import { useState } from 'react'
import { Link } from 'react-router'
import sunImg from '../../assets/home/tile-sun.svg'
import waveImg from '../../assets/home/tile-wave.svg'
import styles from './MovieTile.module.css'

export type Movie = {
  slug: string
  title: string
  posterTitle: [string, string]
  /** e.g. "2026 · Sci-fi" */
  meta: string
  score: string
  mediaType?: 'movie' | 'tv'
  /** Top → middle → bottom poster gradient stops. */
  gradient: [string, string, string]
  /** Real poster (e.g. from TMDB). When set it replaces the gradient art. */
  posterUrl?: string
  /** Optional recommendation explanation shown under the movie metadata. */
  recommendationReason?: string
}

/** Poster tile for grids and carousels (Figma "Movie Tile"). */
export default function MovieTile({ movie }: { movie: Movie }) {
  const [top, middle, bottom] = movie.gradient
  // A poster that fails to load falls back to the gradient art instead of an empty box.
  const [failedPoster, setFailedPoster] = useState<string | null>(null)
  const showPoster = movie.posterUrl && movie.posterUrl !== failedPoster
  return (
    <Link to={`/movies/${movie.slug}`} className={styles.tile}>
      <div className={styles.poster} style={{ background: `linear-gradient(180deg, ${top} 0%, ${middle} 50%, ${bottom} 100%)` }}>
        {showPoster ? (
          <img
            className={styles.posterImage}
            src={movie.posterUrl}
            alt=""
            loading="lazy"
            onError={() => setFailedPoster(movie.posterUrl ?? null)}
          />
        ) : (
          <>
            <img className={styles.sun} src={sunImg} width={100} height={100} alt="" />
            <img className={styles.wave} src={waveImg} width={196} height={130} alt="" />
            <p className={styles.posterTitle} aria-hidden="true">
              <span>{movie.posterTitle[0]}</span>
              <span>{movie.posterTitle[1]}</span>
            </p>
          </>
        )}
        <span className={styles.mediaType}>
          {movie.mediaType === 'tv' ? 'TV' : 'Movie'}
        </span>
      </div>
      <span className={styles.title}>{movie.title}</span>
      <span className={styles.meta}>{movie.meta}</span>
      {movie.recommendationReason && <span className={styles.recommendationReason}>{movie.recommendationReason}</span>}
    </Link>
  )
}
