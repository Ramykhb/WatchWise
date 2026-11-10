import { useEffect, useState, type ReactNode } from 'react'
import { Link, useLocation, useNavigate, useParams, useSearchParams } from 'react-router'
import axios from 'axios'
import AuthShell from '../../components/AuthShell/AuthShell.tsx'
import AuthWaves from '../../components/AuthWaves/AuthWaves.tsx'
import ButtonPrimary from '../../components/ButtonPrimary/ButtonPrimary.tsx'
import { useAuth } from '../../contexts/useAuth.ts'
import { api } from '../../lib/api.ts'
import bgPeach from '../../assets/auth/signin-bg-peach-blob.svg'
import mailIcon from '../../assets/forgot/icon-mail.svg'
import styles from '../VerifyEmail/VerifyEmail.module.css'
import {
  FAILED, buildConfirmRequest, confirmCopy, confirmRequestUrl, isConfirmKind, outcomeFromResponse, resolveOutcome, resultPath, type Resolution,
} from './accountLink.ts'

const NEXT_PATH: Record<Exclude<Resolution['next'], 'retry'>, string> = {
  signin: '/signin',
  'edit-profile': '/edit-profile',
  settings: '/settings',
  landing: '/',
}

const NO_REFERRER = { rel: 'noreferrer', referrerPolicy: 'no-referrer' } as const

/** The address of these pages carries a signed link, so no request they make may reveal it in a Referer header. */
function useNoReferrer() {
  useEffect(() => {
    const meta = document.createElement('meta')
    meta.name = 'referrer'
    meta.content = 'no-referrer'
    document.head.appendChild(meta)
    return () => { meta.remove() }
  }, [])
}

function Shell({ children }: { children: ReactNode }) {
  return (
    <AuthShell
      formSide="left"
      logoTheme="light"
      background={<><img className={styles.bg} src={bgPeach} width={1440} height={1024} alt="" /><AuthWaves side="right" className={styles.bg} /></>}
      topLink={<Link {...NO_REFERRER} className={styles.topLink} to="/signin">Sign in</Link>}
      artboard={<div className={styles.card}><img src={mailIcon} width={48} height={48} alt="" /><p>Your account,<br />your call.</p><span>Links from your email land here.</span></div>}
    >
      <div className={styles.form}>{children}</div>
    </AuthShell>
  )
}

function ResolutionView({ resolution, onRetry }: { resolution: Resolution; onRetry?: () => void }) {
  return (
    <Shell>
      <h1>{resolution.title}</h1>
      <p className={styles.description} role="status">{resolution.body}</p>
      {resolution.next === 'retry'
        ? <button className={styles.continue} type="button" onClick={onRetry}>{resolution.nextLabel}</button>
        : <Link {...NO_REFERRER} className={styles.continue} to={NEXT_PATH[resolution.next]}>{resolution.nextLabel}</Link>}
    </Shell>
  )
}

/** Public page for `/account-link/result`: where the safe cancel links and bad-signature redirects land. */
export function AccountLinkResult() {
  useNoReferrer()
  const [params] = useSearchParams()
  // `failed` only makes sense on the confirm page, where the signed link is still there to retry.
  const outcome = params.get('outcome') === 'failed' ? 'invalid' : params.get('outcome')
  return <ResolutionView resolution={resolveOutcome(params.get('kind'), outcome)} />
}

/** Public page for `/account-link/:kind`: the emailed link only opens it; the POST happens on the click. */
export default function AccountLink() {
  const { kind } = useParams()
  const { search } = useLocation()
  const { user, setUser } = useAuth()
  const navigate = useNavigate()
  useNoReferrer()
  const request = buildConfirmRequest(kind, search)
  const [busy, setBusy] = useState(false)
  const [outcome, setOutcome] = useState<string | null>(null)

  if (!request || !isConfirmKind(kind)) return <ResolutionView resolution={resolveOutcome(kind, 'invalid')} />

  const confirm = async () => {
    if (busy) return
    setBusy(true)
    let result: string
    // The signature covers the absolute URL, so the API host must equal the backend's APP_URL host.
    try {
      const response = await api.post(confirmRequestUrl(request), undefined, { headers: { 'Content-Type': undefined }, withCredentials: false })
      result = outcomeFromResponse(response.status, response.data)
    } catch (error) {
      result = axios.isAxiosError(error) ? outcomeFromResponse(error.response?.status, error.response?.data) : outcomeFromResponse(undefined, null)
    }
    // Every session of the account is gone server-side, so drop the local signed-in state too.
    // Leave a different signed-in account's local state alone.
    const linkId = Number(new URLSearchParams(search).get('id'))
    if (resolveOutcome(kind, result).endsSession && user?.id === linkId) setUser(null)
    if (result === FAILED) {
      // Nothing was decided, so stay on the signed link and let them try again.
      setOutcome(result)
      setBusy(false)
    } else {
      navigate(resultPath(kind, result), { replace: true })
    }
  }

  if (outcome !== null) return <ResolutionView resolution={resolveOutcome(kind, outcome)} onRetry={() => setOutcome(null)} />

  const copy = confirmCopy(kind)
  return (
    <Shell>
      <h1>{copy.title}</h1>
      <p className={styles.description}>{copy.body}</p>
      <form className={styles.formBody} onSubmit={(event) => { event.preventDefault(); void confirm() }}>
        <ButtonPrimary type="submit" label={busy ? 'Working…' : copy.button} fullWidth disabled={busy} />
      </form>
      <Link {...NO_REFERRER} className={styles.textButton} to="/signin">Not now</Link>
    </Shell>
  )
}
