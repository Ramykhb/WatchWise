import { useEffect, useState, type FormEvent } from 'react'
import { Link, useLocation, useNavigate } from 'react-router'
import AuthShell from '../../components/AuthShell/AuthShell.tsx'
import AuthWaves from '../../components/AuthWaves/AuthWaves.tsx'
import ButtonPrimary from '../../components/ButtonPrimary/ButtonPrimary.tsx'
import TextField from '../../components/TextField/TextField.tsx'
import { useNoReferrer } from '../../hooks/useNoReferrer.ts'
import { useAuth } from '../../contexts/useAuth.ts'
import { authApi } from '../../lib/auth.ts'
import { PASSWORD_RULE_HINT, passwordRuleError } from '../../lib/password.ts'
import bgPeach from '../../assets/auth/signin-bg-peach-blob.svg'
import mailIcon from '../../assets/forgot/icon-mail.svg'
import styles from '../VerifyEmail/VerifyEmail.module.css'
import { classifyResetFailure, parseResetLink, resetBody } from './resetPassword.ts'

type Errors = Partial<Record<'password' | 'confirm', string>>
type Phase = 'form' | 'done' | 'invalid'

/** Public page for the emailed `/reset-password?token=&email=` link; works signed in or out. */
export default function ResetPassword() {
  const { search } = useLocation()
  const navigate = useNavigate()
  const { setUser } = useAuth()
  useNoReferrer()
  // Read once, then drop the secret from the address bar and history; the page keeps it in state.
  const [link] = useState(() => parseResetLink(search))
  useEffect(() => {
    if (search) navigate('/reset-password', { replace: true })
  }, [search, navigate])
  const [errors, setErrors] = useState<Errors>({})
  const [formError, setFormError] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [phase, setPhase] = useState<Phase>('form')

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!link || submitting) return
    const data = new FormData(event.currentTarget)
    const password = String(data.get('password') ?? '')
    const confirmation = String(data.get('password_confirmation') ?? '')
    const next: Errors = {}
    const ruleError = passwordRuleError(password)
    if (ruleError) next.password = ruleError
    if (confirmation !== password) next.confirm = 'Passwords do not match.'
    setErrors(next)
    setFormError('')
    if (Object.keys(next).length > 0) return
    setSubmitting(true)
    try {
      await authApi.post('/auth/reset-password', resetBody(link, password, confirmation))
      // Every session of the account was revoked server-side.
      setUser(null)
      setPhase('done')
    } catch (error) {
      const failure = classifyResetFailure(error)
      if (failure.kind === 'invalid-link') setPhase('invalid')
      else if (failure.kind === 'fields') {
        setErrors({ password: failure.errors.password, confirm: failure.errors.password_confirmation })
        if (!failure.errors.password && !failure.errors.password_confirmation) setFormError(failure.message)
      } else setFormError(failure.message)
    } finally {
      setSubmitting(false)
    }
  }

  const shown: Phase = link ? phase : 'invalid'

  return (
    <AuthShell
      formSide="left"
      logoTheme="light"
      background={<><img className={styles.bg} src={bgPeach} width={1440} height={1024} alt="" /><AuthWaves side="right" className={styles.bg} /></>}
      topLink={<Link className={styles.topLink} to="/signin">Sign in</Link>}
      artboard={<div className={styles.card}><img src={mailIcon} width={48} height={48} alt="" /><p>A fresh start,<br />same great films.</p><span>Pick a new password and carry on.</span></div>}
    >
      <div className={styles.form}>
        {shown === 'done' && <>
          <h1>Password updated.</h1>
          <p className={styles.description} role="status">Your password was changed and every device was signed out. Sign in with your new password.</p>
          <Link className={styles.continue} to="/signin">Sign in</Link>
        </>}
        {shown === 'invalid' && <>
          <h1>Link not valid.</h1>
          <p className={styles.description} role="status">This reset link is incomplete, already used, or has expired (links last 60 minutes). Request a new one and we will email it right away.</p>
          <Link className={styles.continue} to="/forgot-password">Request a new link</Link>
        </>}
        {shown === 'form' && link && <>
          <h1>New password.</h1>
          <p className={styles.description}>Choose a new password for <strong>{link.email}</strong>. {PASSWORD_RULE_HINT}</p>
          <form className={styles.formBody} onSubmit={handleSubmit} noValidate>
            <TextField label="New password" name="password" type="password" autoComplete="new-password" placeholder="Create a password" revealable required error={errors.password} />
            <TextField label="Confirm password" name="password_confirmation" type="password" autoComplete="new-password" placeholder="Repeat your password" revealable required error={errors.confirm} />
            {formError && <p className={styles.error} role="alert">{formError}</p>}
            <ButtonPrimary type="submit" label={submitting ? 'Updating…' : 'Update password'} fullWidth disabled={submitting} />
          </form>
        </>}
      </div>
    </AuthShell>
  )
}
