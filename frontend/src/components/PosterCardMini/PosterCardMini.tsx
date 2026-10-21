import type { CSSProperties } from 'react'
import sunImg from '../../assets/auth/mini-poster-sun.svg'
import waveImg from '../../assets/auth/mini-poster-wave.svg'
import starIcon from '../../assets/auth/icon-star-sm.svg'
import styles from './PosterCardMini.module.css'

export type MiniPoster = {
  title: string
  posterTitle: [string, string]
  meta: string
  rating: string
  /** Top → middle → bottom poster gradient stops. */
  gradient: [string, string, string]
  /** Middle stop position, e.g. "50%". */
  mid?: string
}

type PosterCardMiniProps = {
  poster: MiniPoster
  className?: string
  style?: CSSProperties
}

/** Compact floating poster card for decorative stacks. */
export default function PosterCardMini({ poster, className, style }: PosterCardMiniProps) {
  const [top, middle, bottom] = poster.gradient
  return (
    <div className={[styles.card, className].filter(Boolean).join(' ')} style={style}>
      <div
        className={styles.poster}
        style={{ background: `linear-gradient(180deg, ${top} 0%, ${middle} ${poster.mid ?? '50%'}, ${bottom} 100%)` }}
      >
        <img className={styles.sun} src={sunImg} width={90} height={90} alt="" />
        <img className={styles.wave} src={waveImg} width={180} height={110} alt="" />
        <p className={styles.posterTitle}>
          <span>{poster.posterTitle[0]}</span>
          <span>{poster.posterTitle[1]}</span>
        </p>
      </div>
      <div className={styles.info}>
        <div className={styles.titleRow}>
          <span className={styles.title}>{poster.title}</span>
          <span className={styles.rating}>
            <img src={starIcon} width={13} height={13} alt="" />
            {poster.rating}
          </span>
        </div>
        <span className={styles.meta}>{poster.meta}</span>
      </div>
    </div>
  )
}
