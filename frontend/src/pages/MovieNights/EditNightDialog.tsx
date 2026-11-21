import { useEffect, useId, useRef, useState, type FormEvent } from 'react'
import { createPortal } from 'react-dom'
import { api } from '../../lib/api.ts'
import { apiErrorMessage } from '../../lib/auth.ts'
import type { ApiNight } from '../../lib/types.ts'
import type { Night } from './nightsData.ts'
import type { NightDetail } from './nightDetailData.ts'
import { nightEditPayload } from './nightEdit.ts'
import styles from './NightDialogs.module.css'

export default function EditNightDialog({ night, detail, onSaved, onClose }: {
  night: Night; detail: NightDetail; onSaved: (night: ApiNight) => void; onClose: () => void
}) {
  const panel = useRef<HTMLDialogElement>(null)
  const heading = useId()
  const [fields, setFields] = useState({ title: night.title, date: night.date, time: night.time, location: detail.where ?? '', meetingLink: detail.link ?? '', note: detail.note ?? '' })
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  useEffect(() => {
    const previous = document.activeElement as HTMLElement | null
    const dialog = panel.current
    const overflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    dialog?.showModal()
    return () => { dialog?.close(); document.body.style.overflow = overflow; previous?.focus() }
  }, [])
  const save = async (event: FormEvent) => {
    event.preventDefault()
    if (busy) return
    setBusy(true)
    setError('')
    try {
      const payload = nightEditPayload(fields, night.date, night.time)
      const { data } = await api.patch<{ night: ApiNight }>(`/movie-nights/${night.id}`, payload)
      onSaved(data.night)
      onClose()
    } catch (failure) { setError(apiErrorMessage(failure, failure instanceof Error ? failure.message : "Couldn't save this movie night.")) }
    finally { setBusy(false) }
  }
  const field = (key: keyof typeof fields, label: string, type = 'text', maxLength?: number) => <label>
    {label}<input type={type} value={fields[key]} required={['title', 'date', 'time'].includes(key)} maxLength={maxLength}
      onChange={(event) => setFields((current) => ({ ...current, [key]: event.target.value }))} />
  </label>
  return createPortal(<dialog ref={panel} className={styles.dialog} aria-labelledby={heading} data-lenis-prevent
    onCancel={(event) => { event.preventDefault(); if (!busy) onClose() }}>
    <form onSubmit={save}>
      <h2 id={heading}>Edit movie night</h2>
      <p>Times use your current time zone. Guests will see the updated details.</p>
      <fieldset disabled={busy}>
        {field('title', 'Title', 'text', 80)}
        <div className={styles.row}>{field('date', 'Date', 'date')}{field('time', 'Time', 'time')}</div>
        {field('location', 'Location (optional)', 'text', 255)}
        {field('meetingLink', 'Meeting link (optional)', 'url', 255)}
        <label>Note (optional)<textarea rows={3} maxLength={500} value={fields.note} onChange={(event) => setFields((current) => ({ ...current, note: event.target.value }))} /></label>
      </fieldset>
      {error && <p role="alert" className={styles.error}>{error}</p>}
      <div className={styles.actions}><button type="button" disabled={busy} onClick={onClose}>Discard changes</button><button type="submit" disabled={busy}>{busy ? 'Saving…' : 'Save changes'}</button></div>
    </form>
  </dialog>, document.body)
}
