import { useEffect, useId, useRef, useState, type FormEvent } from 'react'
import { createPortal } from 'react-dom'
import { Flag } from 'lucide-react'
import { api } from '../../lib/api.ts'
import { apiErrorMessage } from '../../lib/auth.ts'
import { REPORT_REASONS, reportEndpoint, reportPayload, type ReportTarget } from './report.ts'
import styles from './ReportButton.module.css'

export default function ReportButton({ target, className }: { target: ReportTarget; className?: string }) {
  const [open, setOpen] = useState(false)
  const [note, setNote] = useState('')
  return <>
    <button type="button" className={`${styles.trigger} ${className ?? ''}`} aria-disabled={Boolean(note) || undefined}
      onClick={() => { if (!note) setOpen(true) }} aria-label={`Report ${target.type}`}>
      <Flag size={14} aria-hidden="true" />{note ? 'Reported' : 'Report'}
    </button>
    {note && <span className={styles.note} role="status">{note}</span>}
    {open && createPortal(<ReportDialog target={target} onClose={() => setOpen(false)} onReported={(message) => {
      setNote(message)
      setOpen(false)
    }} />, document.body)}
  </>
}

function ReportDialog({ target, onClose, onReported }: { target: ReportTarget; onClose: () => void; onReported: (message: string) => void }) {
  const dialog = useRef<HTMLDialogElement>(null)
  const titleId = useId()
  const descriptionId = useId()
  const reasonId = useId()
  const detailsId = useId()
  const [reason, setReason] = useState('')
  const [details, setDetails] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    const previous = document.activeElement as HTMLElement | null
    const overflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    const panel = dialog.current
    panel?.showModal()
    return () => { panel?.close(); document.body.style.overflow = overflow; previous?.focus() }
  }, [])

  const submit = async (event: FormEvent) => {
    event.preventDefault()
    if (busy) return
    setBusy(true)
    setError('')
    try {
      const { data } = await api.post<{ message: string }>(reportEndpoint(target), reportPayload(target.type, reason, details))
      onReported(data.message || 'Report submitted. Our moderators will review it.')
    } catch (failure) {
      setError(apiErrorMessage(failure, "Couldn't send your report. Please try again."))
    } finally {
      setBusy(false)
    }
  }

  return <dialog ref={dialog} className={styles.dialog} aria-labelledby={titleId} aria-describedby={descriptionId}
    aria-busy={busy || undefined} data-lenis-prevent onCancel={(event) => { event.preventDefault(); if (!busy) onClose() }}>
    <form onSubmit={submit}>
      <h2 id={titleId}>Report {target.type}</h2>
      <p id={descriptionId}>Tell our moderators what needs attention. Reporting does not remove this content automatically.</p>
      <label htmlFor={reasonId}>Reason</label>
      <select id={reasonId} required value={reason} disabled={busy} onChange={(event) => setReason(event.target.value)}>
        <option value="" disabled>Choose a reason</option>
        {REPORT_REASONS[target.type].map((item) => <option key={item} value={item}>{item.charAt(0).toUpperCase() + item.slice(1)}</option>)}
      </select>
      {target.type !== 'review' && <>
        <label htmlFor={detailsId}>Details <span>(optional)</span></label>
        <textarea id={detailsId} value={details} disabled={busy} maxLength={500} rows={4} onChange={(event) => setDetails(event.target.value)} />
        <span className={styles.count}>{details.length}/500</span>
      </>}
      {error && <p className={styles.error} role="alert">{error}</p>}
      <div className={styles.actions}>
        <button type="button" disabled={busy} onClick={onClose}>Cancel</button>
        <button type="submit" disabled={busy || !reason}>{busy ? 'Sending…' : 'Send report'}</button>
      </div>
    </form>
  </dialog>
}
