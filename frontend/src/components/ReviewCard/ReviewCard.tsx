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
  /** Text is blurred until clicked. */
  spoilers?: boolean
  moderationLabels?: string[]
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
  /** Classification is still running; sentiment is not available yet. */
  pending?: boolean
}

/** Member review with rating, sentiment, "helpful" and reply actions (Figma "Review Card"). */
export default function ReviewCard({ review, onHelpful, markedHelpful, own = false, canReply = !onHelpful, pending = false }: ReviewCardProps) {
  const [localHelpful, setLocalHelpful] = useState(false)
  const [revealedReview, setRevealedReview] = useState<string | null>(null)
  const pressed = onHelpful ? Boolean(markedHelpful) : localHelpful
  const helpful = review.helpful + (onHelpful ? 0 : localHelpful ? 1 : 0)
  const reviewVersion = `${review.id}:${review.body}`
  const hidden = (review.spoilers || review.moderationLabels?.includes('spoiler')) && revealedReview !== reviewVersion
  const labels = [...new Set([
    ...(review.moderationLabels ?? []),
    ...(review.spoilers ? ['spoiler'] : []),
  ])]

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
          {!pending && <Sentiment type={review.sentiment} />}
        </div>
      </header>
      <div className={styles.body}>
        {labels.map((label) => (
          <span key={label} className={styles.spoilerTag}>
            {label.charAt(0).toUpperCase() + label.slice(1)}
          </span>
        ))}
        {hidden ? (
          <button
            type="button"
            className={styles.spoilerReveal}
            aria-label="This review contains spoilers. Reveal review text"
            onClick={() => setRevealedReview(reviewVersion)}
          >
            <span className={styles.blurredText} aria-hidden="true">{review.body}</span>
            <span className={styles.revealHint} aria-hidden="true">Spoiler · Click to reveal</span>
          </button>
        ) : <span>{review.body}</span>}
      </div>
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
