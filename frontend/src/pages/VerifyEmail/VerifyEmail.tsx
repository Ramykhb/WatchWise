import { useCallback, useEffect, useRef, useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router'
import AuthShell from '../../components/AuthShell/AuthShell.tsx'
import AuthWaves from '../../components/AuthWaves/AuthWaves.tsx'
import ButtonPrimary from '../../components/ButtonPrimary/ButtonPrimary.tsx'
import TextField from '../../components/TextField/TextField.tsx'
import { useAuth } from '../../contexts/useAuth.ts'
import { apiErrorMessage, authApi, type AuthUser } from '../../lib/auth.ts'
import { isStatus, SESSION_REQUEST_TIMEOUT } from '../../lib/api.ts'
import bgPeach from '../../assets/auth/signin-bg-peach-blob.svg'
import mailIcon from '../../assets/forgot/icon-mail.svg'
import styles from './VerifyEmail.module.css'

export default function VerifyEmail() {
  const [params] = useSearchParams()
  const { user, loading, setUser, logout } = useAuth()
  const [email, setEmail] = useState('')
  const [sending, setSending] = useState(false)
  const [checking, setChecking] = useState(false)
  const [cooldown, setCooldown] = useState(0)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const checkingRef = useRef(false)
  const statusRequest = useRef<AbortController | null>(null)
  const status = params.get('status')
  const verified = Boolean(user?.email_verified_at)

  const checkStatus = useCallback(async (manual = false) => {
    if (checkingRef.current) return
    checkingRef.current = true
    const controller = new AbortController()
    statusRequest.current = controller
    setChecking(true)
    if (manual) { setError(''); setMessage('') }
    try {
      const response = await authApi.get<{ user: AuthUser }>('/auth/me', { timeout: SESSION_REQUEST_TIMEOUT, signal: controller.signal })
      if (controller.signal.aborted) return
      setUser(response.data.user)
      if (manual && !response.data.user.email_verified_at) {
        setMessage('Your email is still awaiting verification. Open the link in your inbox, then check again.')
      }
    } catch (requestError) {
      if (controller.signal.aborted) return
      if (isStatus(requestError, 401)) setUser(null)
      else setError('Unable to check your verification status. Please try again.')
    } finally {
      checkingRef.current = false
      setChecking(false)
    }
  }, [setUser])

  // Refresh from the database on return from an email opened in another tab.
  useEffect(() => {
    if (loading) return
    const timer = window.setTimeout(() => { void checkStatus() }, 0)
    const onFocus = () => { void checkStatus() }
    window.addEventListener('focus', onFocus)
    return () => {
      window.clearTimeout(timer)
      statusRequest.current?.abort()
      window.removeEventListener('focus', onFocus)
    }
  }, [checkStatus, loading])

  useEffect(() => {
    if (cooldown <= 0) return
    const timer = window.setTimeout(() => setCooldown(cooldown - 1), 1000)
    return () => window.clearTimeout(timer)
  }, [cooldown])

  const resend = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (sending || cooldown > 0) return
    setError('')
    setMessage('')
    setSending(true)
    try {
      await authApi.post('/auth/resend-verification', { email: (user?.email ?? email).trim() })
      setMessage('If this address belongs to an unverified account, a new link will arrive shortly. Check your inbox and spam folder.')
      setCooldown(60)
    } catch (requestError) {
      if (isStatus(requestError, 429)) {
        setCooldown(60)
        setError('Too many requests. Wait a minute before requesting another link.')
      } else {
        setError(apiErrorMessage(requestError, 'Unable to request a verification email. Please try again.'))
      }
    } finally {
      setSending(false)
    }
  }

  const title = verified ? 'Email verified.' : status === 'expired' ? 'Link expired.' : status === 'invalid' ? 'Invalid link.' : status === 'success' ? 'Check verification.' : 'Check your inbox.'
  const description = verified
    ? 'Your email address is verified. You’re ready to start your film diary.'
    : status === 'expired'
      ? 'This verification link has expired. Request a fresh link below.'
      : status === 'invalid'
        ? 'This verification link is invalid or has been changed. Request a fresh link below.'
        : status === 'success'
          ? 'The link was processed. Sign in to check your account’s verification status. If you are signed into a different account, switch accounts below.'
          : 'Open the verification link in your email to finish setting up your WatchWise account. Links expire after 60 minutes.'

  return (
    <AuthShell
      formSide="left"
      logoTheme="light"
      background={<><img className={styles.bg} src={bgPeach} width={1440} height={1024} alt="" /><AuthWaves side="right" className={styles.bg} /></>}
      topLink={<Link className={styles.topLink} to="/signin">Sign in</Link>}
      artboard={<div className={styles.card}><img src={mailIcon} width={48} height={48} alt="" /><p>Your next chapter<br />starts here.</p><span>One click closer to your film diary.</span></div>}
    >
      <div className={styles.form}>
        <h1>{title}</h1>
        <p className={styles.description}>{description}</p>
        {user && !verified && <p className={styles.address}>Verification address: <strong>{user.email}</strong></p>}
        {verified ? <Link className={styles.continue} to="/home-page">Continue to WatchWise →</Link> : <>
          <form className={styles.formBody} onSubmit={resend}>
            {!user && <TextField label="Email" name="email" type="email" autoComplete="email" value={email} onChange={(event) => setEmail(event.target.value)} required maxLength={255} placeholder="you@example.com" />}
            <ButtonPrimary type="submit" label={sending ? 'Requesting…' : cooldown > 0 ? `Resend in ${cooldown}s` : 'Resend verification email'} fullWidth disabled={loading || sending || cooldown > 0} />
          </form>
          {user && <button className={styles.textButton} type="button" disabled={checking || loading} onClick={() => { void checkStatus(true) }}>{checking ? 'Checking…' : 'I’ve verified my email — check status'}</button>}
          {!user && <Link className={styles.textButton} to="/signin">Sign in to continue</Link>}
        </>}
        {message && <p className={styles.feedback} role="status">{message}</p>}
        {error && <p className={styles.error} role="alert">{error}</p>}
        {user && <button className={styles.textButton} type="button" onClick={() => { statusRequest.current?.abort(); void logout().catch(() => setError('Unable to sign out. Please try again.')) }}>Use a different account</button>}
      </div>
    </AuthShell>
  )
}
