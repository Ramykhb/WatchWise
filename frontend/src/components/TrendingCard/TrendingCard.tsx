import { Link } from 'react-router'
import Chip from '../Chip/Chip.tsx'
import flameIcon from '../../assets/landing/icon-flame.svg'
import playIcon from '../../assets/landing/icon-play.svg'
import starIcon from '../../assets/landing/icon-star.svg'
import plusIcon from '../../assets/landing/icon-plus-cream.svg'
import avatars from '../../assets/landing/avatars.svg'
import posterSun from '../../assets/landing/poster-sun.svg'
import posterWaveBack from '../../assets/landing/poster-wave-back.svg'
import posterWaveFront from '../../assets/landing/poster-wave-front.svg'
import styles from './TrendingCard.module.css'

export type TrendingMovie = {
  title: string
  posterTitle: string[]
  rating: string
  meta: string
  genres: string[]
  rank: string
  watching: string
}

type TrendingCardProps = {
  movie: TrendingMovie
  className?: string
}

/** Floating "Trending Now" movie card used in the landing hero. */
export default function TrendingCard({ movie, className }: TrendingCardProps) {
  return (
    <article className={[styles.card, className].filter(Boolean).join(' ')} aria-label={`Trending now: ${movie.title}`}>
      <header className={styles.header}>
        <span className={styles.heading}>
          <img src={flameIcon} width={22} height={22} alt="" />
          Trending Now
        </span>
        <Link to="/discover" className={styles.seeAll}>
          See all
        </Link>
      </header>

      <div className={styles.poster}>
        <img className={styles.sun} src={posterSun} width={160} height={160} alt="" />
        <img className={styles.waveBack} src={posterWaveBack} width={344} height={200} alt="" />
        <img className={styles.waveFront} src={posterWaveFront} width={344} height={180} alt="" />
        <p className={styles.posterTitle} aria-hidden="true">
          {movie.posterTitle.map((line) => (
            <span key={line}>{line}</span>
          ))}
        </p>
        <span className={styles.badge}>{movie.rank}</span>
        <button type="button" className={styles.play} aria-label={`Play ${movie.title} trailer`}>
          <img src={playIcon} width={20} height={20} alt="" />
        </button>
      </div>

      <div className={styles.info}>
        <div className={styles.titleRow}>
          <h3 className={styles.title}>{movie.title}</h3>
          <span className={styles.rating}>
            <img src={starIcon} width={18} height={18} alt="" />
            <span>
              {movie.rating}
              <span className={styles.srOnly}> out of 5</span>
            </span>
          </span>
        </div>
        <p className={styles.meta}>{movie.meta}</p>
      </div>

      <ul className={styles.genres} aria-label="Genres">
        {movie.genres.map((genre) => (
          <li key={genre}>
            <Chip label={genre} />
          </li>
        ))}
      </ul>

      <footer className={styles.footer}>
        <span className={styles.social}>
          <img src={avatars} width={70} height={30} alt="" />
          {movie.watching}
        </span>
        <Link to="/signup" className={styles.watchlist}>
          <img src={plusIcon} width={16} height={16} alt="" />
          Watchlist
        </Link>
      </footer>
    </article>
  )
}
