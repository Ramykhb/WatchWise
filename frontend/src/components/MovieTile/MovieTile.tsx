import { Link } from 'react-router'
import sunImg from '../../assets/home/tile-sun.svg'
import waveImg from '../../assets/home/tile-wave.svg'
import starIcon from '../../assets/home/icon-star.svg'
import styles from './MovieTile.module.css'

export type Movie = {
  slug: string
  title: string
  posterTitle: [string, string]
  /** e.g. "2026 · Sci-fi" */
  meta: string
  score: string
  /** Top → middle → bottom poster gradient stops. */
  gradient: [string, string, string]
  /** Real poster (e.g. from TMDB). When set it replaces the gradient art. */
  posterUrl?: string
}

/** Poster tile for grids and carousels (Figma "Movie Tile"). */
export default function MovieTile({ movie }: { movie: Movie }) {
  const [top, middle, bottom] = movie.gradient
  return (
    <Link to={`/movies/${movie.slug}`} className={styles.tile}>
      <div className={styles.poster} style={{ background: `linear-gradient(180deg, ${top} 0%, ${middle} 50%, ${bottom} 100%)` }}>
        {movie.posterUrl ? (
          <img className={styles.posterImage} src={movie.posterUrl} alt="" loading="lazy" />
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
        <span className={styles.score}>
          <img src={starIcon} width={12} height={12} alt="" />
          <span className="sr-only">Rated </span>
          {movie.score}
        </span>
      </div>
      <span className={styles.title}>{movie.title}</span>
      <span className={styles.meta}>{movie.meta}</span>
    </Link>
  )
}
