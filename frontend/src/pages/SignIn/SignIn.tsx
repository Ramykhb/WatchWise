import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import AuthShell, { type AuthSwapState } from '../../components/AuthShell/AuthShell.tsx'
import ButtonPrimary from '../../components/ButtonPrimary/ButtonPrimary.tsx'
import Checkbox from '../../components/Checkbox/Checkbox.tsx'
import OrDivider from '../../components/OrDivider/OrDivider.tsx'
import PosterCardMini, { type MiniPoster } from '../../components/PosterCardMini/PosterCardMini.tsx'
import SocialButton, { type SocialProvider } from '../../components/SocialButton/SocialButton.tsx'
import TextField from '../../components/TextField/TextField.tsx'
import bgPeach from '../../assets/auth/signin-bg-peach-blob.svg'
import bgCoral from '../../assets/auth/signin-bg-coral-wave.svg'
import bgViolet from '../../assets/auth/signin-bg-violet-ground.svg'
import bgYellow from '../../assets/auth/signin-bg-yellow-blob.svg'
import decoRing from '../../assets/auth/deco-ring-signin.png'
import dotCoral from '../../assets/landing/deco-dot-coral.svg'
import dotMintMd from '../../assets/auth/deco-dot-mint-md.svg'
import dotMintSm from '../../assets/landing/deco-dot-mint-sm.svg'
import styles from './SignIn.module.css'

const SWAP: AuthSwapState = { authSwap: true }
const PROVIDERS: SocialProvider[] = ['google', 'github', 'facebook']
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

const LAST_LAP: MiniPoster = { title: 'Last Lap', posterTitle: ['LAST', 'LAP'], meta: 'Drama · 2025', rating: '4.5', gradient: ['#7de0c2', '#338cb2', '#211a5c'], mid: '55%' }
const PAPER_MOON: MiniPoster = { title: 'Paper Moon', posterTitle: ['PAPER', 'MOON'], meta: 'Comedy · 2026', rating: '4.6', gradient: ['#ffe073', '#ff8c59', '#99264d'] }
const NEON_TIDE: MiniPoster = { title: 'Neon Tide', posterTitle: ['NEON', 'TIDE'], meta: 'Sci-fi · 2026', rating: '4.8', gradient: ['#ff995c', '#ed4d6b', '#5c298c'] }

type Errors = Partial<Record<'email' | 'password', string>>

export default function SignIn() {
  const [errors, setErrors] = useState<Errors>({})

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const data = new FormData(event.currentTarget)
    const email = String(data.get('email') ?? '').trim()
    const password = String(data.get('password') ?? '')
    const next: Errors = {}
    if (!EMAIL_PATTERN.test(email)) next.email = 'Enter a valid email address.'
    if (!password) next.password = 'Enter your password.'
    setErrors(next)
    if (Object.keys(next).length > 0) return
    // TODO: call the Laravel auth API (POST /api/login) once the backend endpoint exists.
  }

  const handleSocial = (provider: SocialProvider) => {
    // TODO: redirect to the backend OAuth route (Laravel Socialite) for this provider.
    void provider
  }

  return (
    <AuthShell
      formSide="left"
      logoTheme="light"
      background={
        <>
          <img className={`${styles.bg} vt-auth-bg-peach`} src={bgPeach} width={1440} height={1024} alt="" />
          <img className={`${styles.bg} ${styles.wave} vt-auth-bg-coral`} src={bgCoral} width={1440} height={1024} alt="" />
          <img className={`${styles.bg} ${styles.wave} vt-auth-bg-violet`} src={bgViolet} width={1440} height={1024} alt="" />
          <img className={`${styles.bgYellow} ${styles.wave} vt-auth-bg-yellow`} src={bgYellow} width={1448} height={1024} alt="" />
        </>
      }
      decor={
        <div className={styles.decor} aria-hidden="true">
          <img className={styles.ring} src={decoRing} width={300} height={300} alt="" />
          <img className={styles.dotCoral} src={dotCoral} width={16} height={16} alt="" />
          <img className={styles.dotMintMd} src={dotMintMd} width={20} height={20} alt="" />
          <img className={styles.dotMintSm} src={dotMintSm} width={14} height={14} alt="" />
        </div>
      }
      topLink={
        <div className={styles.topLink}>
          <span>New to WatchWise?</span>
          <Link to="/signup" viewTransition state={SWAP} className={styles.pill}>
            Sign up
          </Link>
        </div>
      }
      artboard={
        <>
          <PosterCardMini poster={LAST_LAP} className={`${styles.cardLastLap} ww-enter`} />
          <PosterCardMini poster={PAPER_MOON} className={`${styles.cardPaperMoon} ww-enter vt-card-paper-moon`} />
          <PosterCardMini poster={NEON_TIDE} className={`${styles.cardNeonTide} ww-enter`} />
          <div className={`${styles.caption} vt-auth-caption`}>
            <p className={styles.captionTitle}>
              Pick up right
              <br />
              where you left off.
            </p>
            <p className={styles.captionText}>Your watchlist, ratings and film diary — synced on every screen.</p>
          </div>
        </>
      }
    >
      <div className={`${styles.form} ww-enter vt-auth-form`}>
        <div className={styles.heading}>
          <h1 className={styles.title}>
            Welcome
            <br />
            <span className={styles.accent}>back.</span>
          </h1>
          <p className={styles.subtitle}>Sign in to pick up your watchlist, ratings and lists.</p>
        </div>

        <div className={styles.social}>
          {PROVIDERS.map((provider) => (
            <SocialButton key={provider} provider={provider} action="Sign in" onSelect={handleSocial} />
          ))}
        </div>

        <OrDivider label="or sign in with email" />

        <form className={styles.formBody} onSubmit={handleSubmit} noValidate>
          <div className={styles.fields}>
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
              placeholder="••••••••••"
              autoComplete="current-password"
              required
              revealable
              error={errors.password}
            />
          </div>
          <div className={styles.options}>
            <Checkbox name="remember" defaultChecked>
              <span className={styles.remember}>Remember me</span>
            </Checkbox>
            <Link to="/forgot-password" className={styles.link}>
              Forgot password?
            </Link>
          </div>
          <ButtonPrimary type="submit" label="Sign in" fullWidth />
        </form>

        <p className={styles.footer}>
          New to WatchWise?{' '}
          <Link to="/signup" viewTransition state={SWAP} className={styles.link}>
            Create an account
          </Link>
        </p>
      </div>
    </AuthShell>
  )
}
