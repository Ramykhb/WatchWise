import styles from './LoadingState.module.css'

/** Small enough for buttons and inline status messages; its label stays accessible. */
export function LoadingIndicator({ label }: { label: string }) {
  return (
    <span className={styles.indicator} role="status">
      <span className={styles.dots} aria-hidden="true"><i /><i /><i /></span>
      <span>{label}</span>
    </span>
  )
}
