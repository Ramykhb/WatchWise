import styles from './Chip.module.css'

/** Small genre / tag pill. */
export default function Chip({ label }: { label: string }) {
  return <span className={styles.chip}>{label}</span>
}
