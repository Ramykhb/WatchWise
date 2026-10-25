import { useEffect, useId, useRef, useState, type KeyboardEvent, type ReactNode } from 'react'
import xIcon from '../../assets/listmodal/icon-x.svg'
import styles from './ConfirmDialog.module.css'

const CLOSE_MS = 220

type ConfirmDialogProps = {
  /** Keep the dialog rendered and flip this to false to close it with its exit animation. */
  open: boolean
  title: string
  message: ReactNode
  confirmLabel: string
  /** Shown on the confirm button while `onConfirm` runs, e.g. "Deleting…". */
  busyLabel?: string
  cancelLabel?: string
  /** "danger" paints the confirm button coral, for actions that can't be undone. */
  tone?: 'danger' | 'default'
  /** Optional picture above the title, e.g. the list's poster collage. */
  media?: ReactNode
  /** Shown inside the dialog when `onConfirm` rejects, so the user can try again. */
  errorMessage?: string
  /** The action. While it runs both buttons are disabled; if it rejects the error shows and the dialog stays open. */
  onConfirm: () => Promise<void>
  /** Cancel, Esc, ✕ or a click outside. Ignored while the action runs. */
  onCancel: () => void
  /** Called once the exit animation has finished after `open` became false. */
  onClosed?: () => void
}

/**
 * A centred "Are you sure?" dialog in the app's style, replacing the browser's `window.confirm`.
 * Cancel is focused first so Enter never confirms by accident; Tab stays inside; focus goes back
 * to the button that opened it.
 */
export default function ConfirmDialog(props: ConfirmDialogProps) {
  // Stays rendered after `open` turns false until the exit animation ends.
  const [rendered, setRendered] = useState(props.open)
  if (props.open && !rendered) setRendered(true)
  if (!rendered) return null

  return (
    <Panel
      {...props}
      onClosed={() => {
        setRendered(false)
        props.onClosed?.()
      }}
    />
  )
}

function Panel({
  open,
  title,
  message,
  confirmLabel,
  busyLabel,
  cancelLabel = 'Cancel',
  tone = 'danger',
  media,
  errorMessage = 'Something went wrong. Please try again.',
  onConfirm,
  onCancel,
  onClosed,
}: ConfirmDialogProps) {
  const titleId = useId()
  const messageId = useId()
  const dialogRef = useRef<HTMLDivElement>(null)
  const cancelRef = useRef<HTMLButtonElement>(null)
  const [busy, setBusy] = useState(false)
  const [failed, setFailed] = useState(false)

  // Focus Cancel, lock the page scroll, and give focus back to the opener when the dialog goes away.
  useEffect(() => {
    const previous = document.activeElement as HTMLElement | null
    cancelRef.current?.focus()
    const { overflow } = document.body.style
    document.body.style.overflow = 'hidden'
    return () => {
      document.body.style.overflow = overflow
      previous?.focus?.()
    }
  }, [])

  // Kept in a ref so a parent re-render during the exit doesn't restart the timer below.
  const onClosedRef = useRef(onClosed)
  useEffect(() => {
    onClosedRef.current = onClosed
  })

  // Play the exit animation, then tell the parent it can forget the dialog.
  useEffect(() => {
    if (open) return
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    const timer = window.setTimeout(() => onClosedRef.current?.(), reduced ? 0 : CLOSE_MS)
    return () => window.clearTimeout(timer)
  }, [open])

  const cancel = () => {
    if (!busy && open) onCancel()
  }

  const confirm = async () => {
    setBusy(true)
    setFailed(false)
    try {
      await onConfirm()
    } catch {
      setFailed(true)
    } finally {
      setBusy(false)
    }
  }

  // Esc cancels; Tab and Shift+Tab stay inside the dialog.
  const handleKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (event.key === 'Escape') {
      event.stopPropagation()
      cancel()
      return
    }
    if (event.key !== 'Tab' || !dialogRef.current) return
    const focusable = [...dialogRef.current.querySelectorAll<HTMLElement>('button:not([disabled])')]
    const first = focusable[0]
    const last = focusable.at(-1)
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault()
      last?.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault()
      first?.focus()
    }
  }

  return (
    <div
      className={open ? styles.backdrop : styles.backdropClosing}
      onMouseDown={(event) => event.target === event.currentTarget && cancel()}
      onKeyDown={handleKeyDown}
      data-lenis-prevent
    >
      <div
        ref={dialogRef}
        className={styles.dialog}
        role="alertdialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={messageId}
        aria-busy={busy || undefined}
      >
        <button type="button" className={styles.close} aria-label="Close" onClick={cancel} disabled={busy}>
          <img src={xIcon} width={16} height={16} alt="" />
        </button>

        {media && <div className={styles.media}>{media}</div>}

        <h2 id={titleId} className={styles.title}>
          {title}
        </h2>
        <div id={messageId} className={styles.message}>
          {message}
        </div>

        {failed && (
          <p className={styles.error} role="alert">
            {errorMessage}
          </p>
        )}

        <div className={styles.actions}>
          <button ref={cancelRef} type="button" className={styles.cancel} onClick={cancel} disabled={busy}>
            {cancelLabel}
          </button>
          <button
            type="button"
            className={tone === 'danger' ? styles.danger : styles.primary}
            onClick={confirm}
            disabled={busy}
          >
            {busy && busyLabel ? busyLabel : confirmLabel}
          </button>
        </div>
      </div>
    </div>
  )
}
