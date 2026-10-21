import Chip from '../Chip/Chip.tsx'
import styles from './YearInFilmCard.module.css'

type Month = { label: string; value: number; tone: 'soft' | 'strong' | 'peak' }

const MONTHS: Month[] = [
  { label: 'J', value: 34, tone: 'soft' },
  { label: 'F', value: 52, tone: 'soft' },
  { label: 'M', value: 40, tone: 'soft' },
  { label: 'A', value: 70, tone: 'strong' },
  { label: 'M', value: 58, tone: 'soft' },
  { label: 'J', value: 88, tone: 'strong' },
  { label: 'J', value: 64, tone: 'soft' },
  { label: 'A', value: 46, tone: 'soft' },
  { label: 'S', value: 76, tone: 'strong' },
  { label: 'O', value: 60, tone: 'soft' },
  { label: 'N', value: 92, tone: 'peak' },
  { label: 'D', value: 72, tone: 'strong' },
]

const GENRES = ['Sci-fi · 38', 'Drama · 27', 'Horror · 12']

/** "Your 2026 in film" recap card shown beside the Sign up form. */
export default function YearInFilmCard({ className }: { className?: string }) {
  return (
    <div className={[styles.card, className].filter(Boolean).join(' ')}>
      <div className={styles.header}>
        <span className={styles.title}>Your 2026 in film</span>
        <Chip label="Wrapped" />
      </div>
      <div className={styles.total}>
        <span className={styles.count}>142</span>
        <span className={styles.countLabel}>films watched</span>
      </div>
      <div className={styles.chart}>
        {MONTHS.map((month, index) => (
          <div key={index} className={styles.bar}>
            <span className={`${styles.value} ${styles[month.tone]}`} style={{ height: month.value }} />
            <span className={styles.month}>{month.label}</span>
          </div>
        ))}
      </div>
      <div className={styles.genres}>
        {GENRES.map((genre) => (
          <Chip key={genre} label={genre} />
        ))}
      </div>
    </div>
  )
}
