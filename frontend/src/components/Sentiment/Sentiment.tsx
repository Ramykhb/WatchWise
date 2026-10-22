import dotPositive from '../../assets/home/dot-online.svg'
import dotNeutral from '../../assets/movie/dot-neutral.svg'
import dotNegative from '../../assets/movie/dot-negative.svg'
import styles from './Sentiment.module.css'

export type SentimentType = 'positive' | 'neutral' | 'negative'

const VARIANTS = {
  positive: { label: 'Positive', dot: dotPositive },
  neutral: { label: 'Neutral', dot: dotNeutral },
  negative: { label: 'Negative', dot: dotNegative },
} satisfies Record<SentimentType, { label: string; dot: string }>

/** Review sentiment pill (Figma "Sentiment": Positive / Neutral / Negative). */
export default function Sentiment({ type }: { type: SentimentType }) {
  const { label, dot } = VARIANTS[type]
  return (
    <span className={`${styles.sentiment} ${styles[type]}`}>
      <img src={dot} width={8} height={8} alt="" />
      {label}
    </span>
  )
}
