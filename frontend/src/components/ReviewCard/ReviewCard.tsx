import { useState } from 'react'
import RatingStars from '../RatingStars/RatingStars.tsx'
import Sentiment, { type SentimentType } from '../Sentiment/Sentiment.tsx'
import heartIcon from '../../assets/movie/icon-heart.svg'
import chatIcon from '../../assets/movie/icon-chat-sm.svg'
import styles from './ReviewCard.module.css'

export type Review = {
  id: string
  initials: string
  /** Avatar background, e.g. 'var(--accent-mint)'. */
  color: string
  name: string
  date: string
  rating: number
  sentiment: SentimentType
  body: string
  helpful: number
  /** Hidden behind "Show spoiler" until the reader asks. */
  spoilers?: boolean
}

type ReviewCardProps = {
  review: Review
  /** Saves "helpful" through the API; without it the button only counts on this page (demo data). */
  onHelpful?: () => void
  /** Whether you marked it helpful (with `onHelpful`). */
  markedHelpful?: boolean
  /** Your own review: no helpful button (you can't vote for yourself). */
  own?: boolean
  /** Show the Reply button (only where replies exist). */
  canReply?: boolean
}

/** Member review with rating, sentiment, "helpful" and reply actions (Figma "Review Card"). */
export default function ReviewCard({ review, onHelpful, markedHelpful, own = false, canReply = !onHelpful }: ReviewCardProps) {
  const [localHelpful, setLocalHelpful] = useState(false)
  const [revealed, setRevealed] = useState(false)
  const pressed = onHelpful ? Boolean(markedHelpful) : localHelpful
  const helpful = review.helpful + (onHelpful ? 0 : localHelpful ? 1 : 0)
  const hidden = review.spoilers && !revealed && !own

  return (
    <article className={styles.card}>
      <header className={styles.header}>
        <div className={styles.author}>
          <span className={styles.avatar} style={{ background: review.color }} aria-hidden="true">
            {review.initials}
          </span>
          <div className={styles.name}>
            <p className={styles.who}>{review.name}</p>
            <p className={styles.date}>{review.date}</p>
          </div>
        </div>
        <div className={styles.rating}>
          <RatingStars value={review.rating} label={`${review.name}'s rating`} />
          <Sentiment type={review.sentiment} />
        </div>
      </header>
      {hidden ? (
        <p className={styles.spoiler}>
          This review contains spoilers.{' '}
          <button type="button" className={styles.reveal} onClick={() => setRevealed(true)}>
            Show spoiler
          </button>
        </p>
      ) : (
        <p className={styles.body}>
          {review.spoilers && <span className={styles.spoilerTag}>Spoiler</span>}
          {review.body}
        </p>
      )}
      <footer className={styles.footer}>
        {own ? (
          helpful > 0 && (
            <span className={styles.action}>
              <img src={heartIcon} width={16} height={16} alt="" />
              {helpful} helpful
            </span>
          )
        ) : (
          <button
            type="button"
            className={styles.action}
            aria-pressed={pressed}
            onClick={() => (onHelpful ? onHelpful() : setLocalHelpful((value) => !value))}
          >
            <img src={heartIcon} width={16} height={16} alt="" />
            {helpful} helpful
          </button>
        )}
        {canReply && (
          // TODO: open a reply thread once comments exist in the API.
          <button type="button" className={styles.action}>
            <img src={chatIcon} width={16} height={16} alt="" />
            Reply
          </button>
        )}
      </footer>
    </article>
  )
}
