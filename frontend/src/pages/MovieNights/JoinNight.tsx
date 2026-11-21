import { useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router'
import AppNav from '../../components/AppNav/AppNav.tsx'
import PillButton from '../../components/PillButton/PillButton.tsx'
import { api, useApi } from '../../lib/api.ts'
import { apiErrorMessage } from '../../lib/auth.ts'
import { useNoReferrer } from '../../hooks/useNoReferrer.ts'
import styles from './JoinNight.module.css'

export default function JoinNight() {
  useNoReferrer()
  const { slug } = useParams()
  const [params] = useSearchParams()
  const token = params.get('token') ?? ''
  const navigate = useNavigate()
  const [busy, setBusy] = useState(false)
  const [note, setNote] = useState('')
  const preview = useApi<{ title: string; host_name: string; scheduled_at: string }>(`/movie-nights/${Number(slug)}/invitation?token=${encodeURIComponent(token)}`)
  const join = async () => {
    if (busy) return
    setBusy(true)
    setNote('')
    try {
      await api.post(`/movie-nights/${Number(slug)}/join`, { token })
      navigate(`/movie-nights/${Number(slug)}`, { replace: true })
    } catch (error) { setNote(apiErrorMessage(error, "Couldn't join. Please try again.")) }
    finally { setBusy(false) }
  }
  return <><AppNav /><main className={styles.page}><section className={styles.card}>
    <h1>Movie night invitation</h1>
    {preview.error ? <><p>This invite link is invalid, expired, or disabled.</p><Link to="/movie-nights">Back to movie nights</Link></>
      : !preview.data ? <p role="status">Loading invitation…</p>
      : <><h2>{preview.data.title}</h2><p>Hosted by {preview.data.host_name}</p><p>{new Date(preview.data.scheduled_at).toLocaleString()}</p>
        <p>Join to accept the invitation and see the location, meeting link, guests and shortlist.</p>
        <PillButton onClick={() => void join()} disabled={busy}>{busy ? 'Joining…' : 'Join movie night'}</PillButton>
        <Link to="/movie-nights">No thanks</Link></>}
    {note && <p role="alert">{note}</p>}
  </section></main></>
}
