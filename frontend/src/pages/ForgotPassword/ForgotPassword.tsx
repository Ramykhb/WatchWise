import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import AuthShell from '../../components/AuthShell/AuthShell.tsx'
import AuthWaves from '../../components/AuthWaves/AuthWaves.tsx'
import ButtonPrimary from '../../components/ButtonPrimary/ButtonPrimary.tsx'
import Magnet from '../../components/reactbits/Magnet/Magnet.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import TextField from '../../components/TextField/TextField.tsx'
import bgPeach from '../../assets/auth/signin-bg-peach-blob.svg'
import arrowLeft from '../../assets/forgot/icon-arrow-left.svg'
import arrowIcon from '../../assets/landing/icon-arrow.svg'
import lockIcon from '../../assets/forgot/icon-lock.svg'
import mailIcon from '../../assets/forgot/icon-mail.svg'
import { AUTH_SWAP_STATE as SWAP, useAuthSwap } from '../../hooks/useAuthSwap.ts'
import styles from './ForgotPassword.module.css'

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const STEPS = ['Request', 'Check email', 'New password']

export default function ForgotPassword() {
  const swapped = useAuthSwap()
  const [error, setError] = useState<string>()
  const [sentTo, setSentTo] = useState<string>()
  const step = sentTo ? 2 : 1

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const email = String(new FormData(event.currentTarget).get('email') ?? '').trim()
    if (!EMAIL_PATTERN.test(email)) {
      setError('Enter a valid email address.')
      return
    }
    setError(undefined)
    // TODO: call the Laravel API (POST /api/forgot-password) once the backend endpoint exists.
    setSentTo(email)
  }

  return (
    <AuthShell
      formSide="left"
      logoTheme="light"
      background={
        <>
          <img className={`${styles.bg} vt-auth-bg-peach`} src={bgPeach} width={1440} height={1024} alt="" />
          <AuthWaves side="right" className={`${styles.bg} ${styles.wave}`} />
        </>
      }
      topLink={
        <div className={styles.topLink}>
          <span>Remembered it?</span>
          <Magnet padding={40} magnetStrength={3} maxOffset={10}>
            <Link to="/signin" viewTransition state={SWAP} className={styles.pill}>
              Sign in
            </Link>
          </Magnet>
        </div>
      }
      artboard={
        <>
          {/* Previews of steps 2 and 3 (decorative, from the Figma frame). */}
          <div className={`${styles.card} ${styles.inboxCard} vt-forgot-inbox`} data-enter data-enter-delay="0.3" data-enter-rise="60">
            <span className={styles.mailBadge}>
              <img src={mailIcon} width={25} height={25} alt="" />
            </span>
            <p className={styles.cardTitle}>Check your inbox</p>
            <p className={styles.cardText}>
              We sent a reset link to <strong>{sentTo ?? 'maya@watchwise.app'}</strong>
            </p>
            <div className={styles.cardActions}>
              <span className={styles.softPill}>Open email app</span>
              <span className={styles.cardMuted}>Resend in 0:42</span>
            </div>
          </div>
          <div className={`${styles.card} ${styles.resetCard} vt-forgot-reset`} data-enter data-enter-delay="0.5" data-enter-rise="60">
            <p className={styles.resetTitle}>Create a new password</p>
            <div className={styles.mockField}>
              <span className={styles.mockLabel}>New password</span>
              <span className={styles.mockControl}>
                ••••••••••••<span className={styles.mockToggle}>Show</span>
              </span>
            </div>
            <div className={styles.strength}>
              <span className={styles.barOn} />
              <span className={styles.barOn} />
              <span className={styles.barOn} />
              <span className={styles.barOff} />
              <span className={styles.strengthLabel}>Strong</span>
            </div>
            <div className={styles.mockField}>
              <span className={styles.mockLabel}>Confirm password</span>
              <span className={styles.mockControl}>••••••••••••</span>
            </div>
            <span className={styles.mockButton}>
              Update password
              <span className={styles.mockButtonIcon}>
                <img src={arrowIcon} width={22} height={22} alt="" />
              </span>
            </span>
          </div>
        </>
      }
    >
      <div className={`${styles.form} vt-auth-form`} data-enter data-enter-delay="0.05" data-enter-rise="24">
        <Link to="/signin" viewTransition state={SWAP} className={styles.back}>
          <img src={arrowLeft} width={18} height={18} alt="" />
          Back to sign in
        </Link>

        <ol className={styles.steps} aria-label="Reset steps">
          {STEPS.map((label, index) => (
            <li
              key={label}
              className={index + 1 === step ? styles.stepActive : styles.step}
              aria-current={index + 1 === step ? 'step' : undefined}
            >
              {index + 1}&nbsp;&nbsp;{label}
            </li>
          ))}
        </ol>

        <div className={styles.heading}>
          <SplitHeadline
            className={styles.title}
            accentClassName={styles.accent}
            lines={[{ text: 'Forgot your' }, { text: 'password?', accent: true }]}
            animate={!swapped}
          />
          <p className={styles.subtitle}>
            No worries — enter the email you signed up with and we&apos;ll send you a secure reset link.
          </p>
        </div>

        <form className={styles.formBody} onSubmit={handleSubmit} noValidate>
          <TextField
            label="Email"
            name="email"
            type="email"
            placeholder="you@example.com"
            autoComplete="email"
            required
            error={error}
          />
          <ButtonPrimary type="submit" label={sentTo ? 'Resend reset link' : 'Send reset link'} fullWidth />
        </form>

        {sentTo ? (
          <p className={styles.sent} role="status">
            Reset link sent to <strong>{sentTo}</strong>. Check your inbox.
          </p>
        ) : (
          <p className={styles.note}>
            <img src={lockIcon} width={16} height={16} alt="" />
            Reset links expire after 30 minutes for your security.
          </p>
        )}
      </div>
    </AuthShell>
  )
}
