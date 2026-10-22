import trendIcon from '../../assets/admin/icon-trend.svg'
import styles from './StatCard.module.css'

type StatCardProps = {
  label: string
  value: string
  delta: string
  /** "up" = mint chip (good news), "attention" = coral chip (needs action). */
  tone?: 'up' | 'attention'
}

/** Dashboard metric with a trend chip (Figma "Stat Card"). */
export default function StatCard({ label, value, delta, tone = 'up' }: StatCardProps) {
  return (
    <div className={styles.card}>
      <p className={styles.label}>{label}</p>
      <p className={styles.value}>{value}</p>
      <span className={tone === 'up' ? styles.deltaUp : styles.deltaAttention}>
        <img src={trendIcon} width={14} height={14} alt="" />
        {delta}
      </span>
    </div>
  )
}
