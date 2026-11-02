import AppNav from '../AppNav/AppNav.tsx'
import styles from './LoadingState.module.css'

type Layout = 'rows' | 'cards' | 'settings' | 'profile'
type LoadingStateProps = {
  label: string
  layout?: Layout
  compact?: boolean
}

/** Small enough for buttons and inline status messages; its label stays accessible. */
export function LoadingIndicator({ label }: { label: string }) {
  return (
    <span className={styles.indicator} role="status">
      <span className={styles.dots} aria-hidden="true"><i /><i /><i /></span>
      <span>{label}</span>
    </span>
  )
}

/** A decorative skeleton with one announced status, shared by pages and async sections. */
export default function LoadingState({ label, layout = 'rows', compact = false }: LoadingStateProps) {
  if (compact) return <LoadingIndicator label={label} />

  return (
    <div className={styles.loading} role="status">
      <div className={styles.heading}>
        <span className={styles.reel} aria-hidden="true"><span /></span>
        <span className={styles.label}>{label}</span>
      </div>
      <div className={`${styles.skeleton} ${styles[layout]}`} aria-hidden="true">
        {layout === 'settings' && (
          <div className={styles.sidebar}>
            <span className={styles.circle} />
            <span className={styles.line} /><span className={styles.shortLine} />
            <span className={styles.line} /><span className={styles.shortLine} />
          </div>
        )}
        {layout === 'profile' && (
          <div className={styles.profileHeader}>
            <span className={styles.avatar} />
            <span className={styles.line} /><span className={styles.shortLine} />
          </div>
        )}
        <div className={styles.items}>
          {Array.from({ length: layout === 'cards' ? 4 : 3 }, (_, index) => (
            <div className={styles.item} key={index}>
              <span className={layout === 'cards' ? styles.poster : styles.circle} />
              <div className={styles.copy}>
                <span className={styles.line} /><span className={styles.shortLine} />
                {layout === 'settings' && <span className={styles.input} />}
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}

/** Keeps navigation available while the page's first response is pending. */
export function PageLoading({ label, layout = 'rows' }: LoadingStateProps) {
  return (
    <div className={styles.page}>
      <AppNav />
      <main className={styles.main} aria-busy="true">
        <LoadingState label={label} layout={layout} />
      </main>
    </div>
  )
}
