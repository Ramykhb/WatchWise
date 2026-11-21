import { useEffect, useId, useRef, useState, type FormEvent } from 'react'
import { createPortal } from 'react-dom'
import { api } from '../../lib/api.ts'
import { apiErrorMessage } from '../../lib/auth.ts'
import type { ApiNight } from '../../lib/types.ts'
import type { Candidate } from './nightDetailData.ts'
import styles from './NightDialogs.module.css'

export default function ChooseWinnerDialog({ nightId, candidates, titleOf, onSaved, onClose }: {
  nightId: number; candidates: Candidate[]; titleOf: (id: number) => string; onSaved: (night: ApiNight) => void; onClose: () => void
}) {
  const panel = useRef<HTMLDialogElement>(null)
  const heading = useId()
  const top = Math.max(0, ...candidates.map((candidate) => candidate.voters.length))
  const leaders = candidates.filter((candidate) => candidate.voters.length === top)
  const [chosen, setChosen] = useState(top > 0 && leaders.length === 1 ? String(leaders[0].tmdbId) : '')
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
  const confirm = async (event: FormEvent) => {
    event.preventDefault()
    if (busy || !chosen) return
    setBusy(true)
    setError('')
    try {
      const { data } = await api.patch<{ night: ApiNight }>(`/movie-nights/${nightId}`, { status: 'confirmed', winning_tmdb_id: Number(chosen) })
      onSaved(data.night)
      onClose()
    } catch (failure) { setError(apiErrorMessage(failure, "Couldn't confirm the winner. Please try again.")) }
    finally { setBusy(false) }
  }
  return createPortal(<dialog ref={panel} className={styles.dialog} aria-labelledby={heading} data-lenis-prevent onCancel={(event) => { event.preventDefault(); if (!busy) onClose() }}>
    <form onSubmit={confirm}><h2 id={heading}>Choose the winning movie</h2>
      <p>{top === 0 ? 'No votes have been cast. Choose a movie explicitly to finalize this night.' : leaders.length > 1 ? 'The vote is tied. Choose the movie everyone will watch.' : 'Confirm the leading movie, or choose another film from the shortlist.'}</p>
      <label>Winning movie<select required value={chosen} disabled={busy} onChange={(event) => setChosen(event.target.value)}>
        <option value="" disabled>Choose a movie</option>
        {candidates.map((candidate) => <option key={candidate.tmdbId} value={candidate.tmdbId}>{titleOf(candidate.tmdbId)} · {candidate.voters.length} votes</option>)}
      </select></label>
      <p>Voting closes when you confirm. Participants with Calendar enabled will be emailed an invitation.</p>
      {error && <p role="alert" className={styles.error}>{error}</p>}
      <div className={styles.actions}><button type="button" disabled={busy} onClick={onClose}>Keep voting open</button><button type="submit" disabled={busy || !chosen}>{busy ? 'Confirming…' : 'Confirm winner & close voting'}</button></div>
    </form>
  </dialog>, document.body)
}
