import { useEffect, useRef, useState } from 'react'
import xIcon from '../../assets/listmodal/icon-x-sm.svg'
import styles from './Toast.module.css'

const EXIT_MS = 220

type ToastProps = {
  message: string
  /** How long the message stays before it slides away. */
  duration?: number
  /** Called after it has slid away (timeout or ✕), so the parent can unmount it. */
  onDone: () => void
}

/**
 * A short confirmation that slides up at the bottom of the screen, e.g. "“Summer” deleted".
 * Give it a new `key` for each message so a second one restarts the timer.
 */
export default function Toast({ message, duration = 4000, onDone }: ToastProps) {
  const [leaving, setLeaving] = useState(false)
  // Kept in a ref so a parent re-render doesn't restart the timers below.
  const onDoneRef = useRef(onDone)
  useEffect(() => {
    onDoneRef.current = onDone
  })

  useEffect(() => {
    const timer = window.setTimeout(() => setLeaving(true), duration)
    return () => window.clearTimeout(timer)
  }, [duration])

  useEffect(() => {
    if (!leaving) return
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    const timer = window.setTimeout(() => onDoneRef.current(), reduced ? 0 : EXIT_MS)
    return () => window.clearTimeout(timer)
  }, [leaving])

  return (
    <div className={leaving ? styles.toastLeaving : styles.toast} role="status">
      <span className={styles.dot} aria-hidden="true" />
      <span className={styles.message}>{message}</span>
      <button type="button" className={styles.dismiss} aria-label="Dismiss" onClick={() => setLeaving(true)}>
        <img src={xIcon} width={12} height={12} alt="" />
      </button>
    </div>
  )
}
