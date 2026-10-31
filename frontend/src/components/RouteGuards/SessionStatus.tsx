import { LoaderCircle } from 'lucide-react'
import { useAuth } from '../../contexts/useAuth.ts'
import Logo from '../Logo/Logo.tsx'
import PillButton from '../PillButton/PillButton.tsx'
import styles from './SessionStatus.module.css'

export default function SessionStatus() {
  const { loading, sessionError, retrySession } = useAuth()

  return (
    <div className={styles.page}>
      <Logo />
      <main className={styles.content} aria-busy={loading}>
        {loading && <LoaderCircle className={styles.spinner} size={36} aria-hidden="true" />}
        <div role={sessionError ? 'alert' : 'status'}>
          <h1 className={styles.title}>{sessionError ? "Couldn't load your session" : 'Opening WatchWise…'}</h1>
          <p className={styles.message}>{sessionError ?? 'Checking your session. Your page will be ready shortly.'}</p>
        </div>
        {sessionError && <PillButton onClick={retrySession}>Try again</PillButton>}
      </main>
    </div>
  )
}
