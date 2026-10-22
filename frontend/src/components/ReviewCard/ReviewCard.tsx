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
}

/** Member review with rating, sentiment, "helpful" and reply actions (Figma "Review Card"). */
export default function ReviewCard({ review }: { review: Review }) {
  const [markedHelpful, setMarkedHelpful] = useState(false)
  const helpful = review.helpful + (markedHelpful ? 1 : 0)

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
      <p className={styles.body}>{review.body}</p>
      <footer className={styles.footer}>
        <button
          type="button"
          className={styles.action}
          aria-pressed={markedHelpful}
          onClick={() => setMarkedHelpful((value) => !value)}
        >
          <img src={heartIcon} width={16} height={16} alt="" />
          {helpful} helpful
        </button>
        {/* TODO: open a reply thread once comments exist in the API. */}
        <button type="button" className={styles.action}>
          <img src={chatIcon} width={16} height={16} alt="" />
          Reply
        </button>
      </footer>
    </article>
  )
}
