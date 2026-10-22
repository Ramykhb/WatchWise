import starFull from '../../assets/movie/star-full.svg'
import starEmpty from '../../assets/movie/star-empty.svg'
import styles from './RatingStars.module.css'

type RatingStarsProps = {
  /** Whole stars filled, 0–5. */
  value: number
  /** Makes the stars clickable (e.g. picking a rating for a review). */
  onChange?: (value: number) => void
  label?: string
}

/** Five 16px stars (Figma "Rating/Stars"); read-only unless `onChange` is given. */
export default function RatingStars({ value, onChange, label = 'Rating' }: RatingStarsProps) {
  if (!onChange) {
    return (
      <span className={styles.stars} role="img" aria-label={`${label}: ${value} out of 5`}>
        {[1, 2, 3, 4, 5].map((n) => (
          <img key={n} src={n <= value ? starFull : starEmpty} width={16} height={16} alt="" />
        ))}
      </span>
    )
  }

  return (
    <span className={styles.stars} role="radiogroup" aria-label={label}>
      {[1, 2, 3, 4, 5].map((n) => (
        <button
          key={n}
          type="button"
          role="radio"
          aria-checked={value === n}
          aria-label={`${n} star${n > 1 ? 's' : ''}`}
          className={styles.star}
          onClick={() => onChange(n)}
        >
          <img src={n <= value ? starFull : starEmpty} width={16} height={16} alt="" />
        </button>
      ))}
    </span>
  )
}
