import AppNav from '../AppNav/AppNav.tsx'
import PillButton from '../PillButton/PillButton.tsx'
import styles from './LoadingState.module.css'

export default function LoadError({ label, onRetry }: { label: string; onRetry: () => void }) {
  return (
    <div className={styles.page}>
      <AppNav />
      <main className={styles.main}>
        <div className={styles.error} role="alert">
          <p>{label}</p>
          <PillButton variant="outline" onClick={onRetry}>Try again</PillButton>
        </div>
      </main>
    </div>
  )
}
