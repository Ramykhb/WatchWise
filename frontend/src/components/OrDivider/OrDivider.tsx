import styles from './OrDivider.module.css'

/** Horizontal rule with a centered label, e.g. "or sign in with email". */
export default function OrDivider({ label }: { label: string }) {
  return (
    <div className={styles.divider} role="separator" aria-label={label}>
      <span className={styles.line} />
      <span className={styles.label}>{label}</span>
      <span className={styles.line} />
    </div>
  )
}
