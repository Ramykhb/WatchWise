import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router'
import AuthShell from '../../components/AuthShell/AuthShell.tsx'
import AuthWaves from '../../components/AuthWaves/AuthWaves.tsx'
import ButtonPrimary from '../../components/ButtonPrimary/ButtonPrimary.tsx'
import Checkbox from '../../components/Checkbox/Checkbox.tsx'
import OrDivider from '../../components/OrDivider/OrDivider.tsx'
import Magnet from '../../components/reactbits/Magnet/Magnet.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import PosterCardMini from '../../components/PosterCardMini/PosterCardMini.tsx'
import SocialButton, { type SocialProvider } from '../../components/SocialButton/SocialButton.tsx'
import TextField from '../../components/TextField/TextField.tsx'
import YearInFilmCard from '../../components/YearInFilmCard/YearInFilmCard.tsx'
import bgPeach from '../../assets/auth/signup-bg-peach-blob.svg'
import bgYellow from '../../assets/auth/signup-bg-yellow-blob.svg'
import decoRing from '../../assets/auth/deco-ring-signup.png'
import dotCoral from '../../assets/landing/deco-dot-coral.svg'
import dotMintMd from '../../assets/auth/deco-dot-mint-md.svg'
import dotMintSm from '../../assets/landing/deco-dot-mint-sm.svg'
import dotYellow from '../../assets/auth/deco-dot-yellow-md.svg'
import infoIcon from '../../assets/auth/icon-info.svg'
import { AUTH_SWAP_STATE as SWAP, useAuthSwap } from '../../hooks/useAuthSwap.ts'
import { LAST_LAP, NEON_TIDE, PAPER_MOON } from '../authPosters.ts'
import styles from './SignUp.module.css'
import { apiErrorMessage, authApi, type AuthPayload } from '../../lib/auth.ts'
import { useAuth } from '../../contexts/useAuth.ts'

const PROVIDERS: SocialProvider[] = ['google', 'github', 'discord']
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const PASSWORD_PATTERN = /^(?=.*\d).{8,}$/

type Errors = Partial<Record<'name' | 'email' | 'password' | 'password_confirmation' | 'terms', string>>

export default function SignUp() {
  const swapped = useAuthSwap()
  const [errors, setErrors] = useState<Errors>({})
  const [formError, setFormError] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const navigate = useNavigate()
  const { setUser } = useAuth()

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const data = new FormData(event.currentTarget)
    const name = String(data.get('name') ?? '').trim()
    const email = String(data.get('email') ?? '').trim()
    const password = String(data.get('password') ?? '')
    const passwordConfirmation = String(data.get('password_confirmation') ?? '')
    const next: Errors = {}
    if (name.length < 2) next.name = 'Tell us your name.'
    if (!EMAIL_PATTERN.test(email)) next.email = 'Enter a valid email address.'
    if (!PASSWORD_PATTERN.test(password)) next.password = 'Use at least 8 characters, including a number.'
    if (passwordConfirmation !== password) next.password_confirmation = 'Passwords do not match.'
    if (!data.get('terms')) next.terms = 'Please accept the Terms and Privacy Policy.'
    setErrors(next)
    setFormError('')
    if (Object.keys(next).length > 0) return
    setSubmitting(true)
    try {
      const response = await authApi.post<AuthPayload>('/register', {
        name, email, password, password_confirmation: passwordConfirmation,
      })
      setUser(response.data.user)
      navigate('/home-page', { replace: true })
    } catch (error) {
      setFormError(apiErrorMessage(error, 'Unable to create your account. Please try again.'))
    } finally {
      setSubmitting(false)
    }
  }

  const handleSocial = (provider: SocialProvider) => {
    window.location.assign(`${import.meta.env.VITE_BACKEND_URL ?? 'http://localhost:8000'}/api/auth/social/${provider}`)
  }

  return (
    <AuthShell
      formSide="right"
      logoTheme="dark"
      background={
        <>
          <img className={`${styles.bg} vt-auth-bg-peach`} src={bgPeach} width={1440} height={1024} alt="" />
          <AuthWaves side="left" className={`${styles.bg} ${styles.wave}`} />
          <img className={`${styles.bgYellow} ${styles.wave} vt-auth-bg-yellow`} src={bgYellow} width={1440} height={1024} alt="" />
        </>
      }
      decor={
        <div className={styles.decor} aria-hidden="true">
          <img className={styles.ring} src={decoRing} width={280} height={280} alt="" />
          <img className={styles.dotCoral} src={dotCoral} width={16} height={16} alt="" />
          <img className={styles.dotMintMd} src={dotMintMd} width={20} height={20} alt="" />
          <img className={styles.dotMintSm} src={dotMintSm} width={14} height={14} alt="" />
          <img className={styles.dotYellow} src={dotYellow} width={14} height={14} alt="" />
        </div>
      }
      topLink={
        <div className={styles.topLink}>
          <span>Already a member?</span>
          <Magnet padding={40} magnetStrength={3} maxOffset={10}>
            <Link to="/signin" viewTransition state={SWAP} className={styles.pill}>
              Sign in
            </Link>
          </Magnet>
        </div>
      }
      artboard={
        <>
          <PosterCardMini poster={LAST_LAP} className={`${styles.cardLastLap} vt-card-last-lap`} />
          <PosterCardMini poster={PAPER_MOON} className={`${styles.cardPaperMoon} vt-card-paper-moon`} />
          <PosterCardMini poster={NEON_TIDE} className={`${styles.cardNeonTide} vt-card-neon-tide`} />
          <YearInFilmCard className={`${styles.yearCard} vt-auth-year-card`} data-enter data-enter-delay="0.25" data-enter-rise="60" />
          <div className={`${styles.sticker} vt-auth-sticker`} data-enter="pop" data-enter-delay="0.7">
            <span className={styles.stickerTitle}>FREE</span>
            <span className={styles.stickerLabel}>FOREVER</span>
          </div>
          <div className={`${styles.caption} vt-auth-caption`}>
            <p className={styles.captionTitle}>
              Your film life,
              <br />
              beautifully logged.
            </p>
            <p className={styles.captionText}>Diary, ratings, lists and a yearly recap — all in one playful place.</p>
          </div>
        </>
      }
    >
      <div className={`${styles.form} vt-auth-form`} data-enter data-enter-delay="0.05" data-enter-rise="24">
        <div className={styles.heading}>
          <SplitHeadline
            className={styles.title}
            accentClassName={styles.accent}
            lines={[{ text: 'Join the' }, { text: 'reel.', accent: true }]}
            animate={!swapped}
          />
          <p className={styles.subtitle}>Create a free account and start your film diary in seconds.</p>
        </div>

        <div className={styles.social}>
          {PROVIDERS.map((provider) => (
            <SocialButton key={provider} provider={provider} action="Sign up" onSelect={handleSocial} />
          ))}
        </div>

        <OrDivider label="or sign up with email" />

        <form className={styles.formBody} onSubmit={handleSubmit} noValidate>
          <div className={styles.fields}>
            <TextField
              label="Full name"
              name="name"
              placeholder="Maya Rivera"
              autoComplete="name"
              required
              error={errors.name}
            />
            <TextField
              label="Email"
              name="email"
              type="email"
              placeholder="you@example.com"
              autoComplete="email"
              required
              error={errors.email}
            />
            <TextField
              label="Password"
              name="password"
              type="password"
              placeholder="Create a password"
              autoComplete="new-password"
              required
              minLength={8}
              revealable
              error={errors.password}
              hint={
                <>
                  <img src={infoIcon} width={14} height={14} alt="" />
                  At least 8 characters, including a number.
                </>
              }
            />
            <TextField
              label="Confirm password"
              name="password_confirmation"
              type="password"
              placeholder="Repeat your password"
              autoComplete="new-password"
              required
              revealable
              error={errors.password_confirmation}
            />
          </div>
          <div className={styles.terms}>
            <Checkbox name="terms" required aria-describedby={errors.terms ? 'terms-error' : undefined}>
              I agree to the{' '}
              <Link to="/terms" className={styles.inlineLink}>
                Terms of Service
              </Link>{' '}
              and{' '}
              <Link to="/privacy" className={styles.inlineLink}>
                Privacy Policy
              </Link>
              .
            </Checkbox>
            {errors.terms && (
              <p id="terms-error" className={styles.error} role="alert">
                {errors.terms}
              </p>
            )}
          </div>
          {formError && <p className={styles.error} role="alert">{formError}</p>}
          <ButtonPrimary type="submit" label={submitting ? 'Creating account…' : 'Create account'} fullWidth disabled={submitting} />
        </form>

        <p className={styles.footer}>
          Already have an account?{' '}
          <Link to="/signin" viewTransition state={SWAP} className={styles.link}>
            Sign in
          </Link>
        </p>
      </div>
    </AuthShell>
  )
}
