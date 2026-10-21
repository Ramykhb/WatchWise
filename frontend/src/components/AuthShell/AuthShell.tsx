import type { ReactNode } from 'react'
import { useLocation } from 'react-router'
import FitToViewport from '../FitToViewport/FitToViewport.tsx'
import Logo from '../Logo/Logo.tsx'
import { useFitScale } from '../../hooks/useFitScale.ts'
import styles from './AuthShell.module.css'

/** Router state set by the Sign in <-> Sign up links so the page swap morphs instead of replaying the entrance. */
export type AuthSwapState = { authSwap: true }

export const ARTBOARD_WIDTH = 720

type AuthShellProps = {
  /** Which half holds the form on desktop; the decorative artboard takes the other half. */
  formSide: 'left' | 'right'
  /** Logo theme on desktop (the logo sits on violet when the form is on the right). */
  logoTheme: 'light' | 'dark'
  background: ReactNode
  decor?: ReactNode
  topLink: ReactNode
  /** Decorative content laid out in a 720px-wide coordinate space (positions from Figma). */
  artboard: ReactNode
  children: ReactNode
}

/** Shared split layout for the Sign in / Sign up pages. */
export default function AuthShell({ formSide, logoTheme, background, decor, topLink, artboard, children }: AuthShellProps) {
  const location = useLocation()
  const swapped = (location.state as AuthSwapState | null)?.authSwap === true
  const { ref, scale } = useFitScale<HTMLDivElement>(ARTBOARD_WIDTH)

  return (
    <FitToViewport designHeight={910}>
      <div className={styles.page} data-entrance={swapped ? 'off' : 'on'}>
        <div className={styles.background} aria-hidden="true">
          {background}
        </div>

        <div className={[styles.stage, formSide === 'right' ? styles.formRight : styles.formLeft].join(' ')}>
          {decor}

          <header className={styles.header}>
            <span className={`${styles.logoWrap} vt-auth-logo`}>
              <Logo theme={logoTheme} className={styles.logoDesktop} />
              <Logo theme="light" className={styles.logoMobile} />
            </span>
            <div className="vt-auth-toplink">{topLink}</div>
          </header>

          <div className={styles.main}>
            <main className={styles.formCol}>{children}</main>
            <div ref={ref} className={styles.visualCol} aria-hidden="true">
              <div className={styles.artboard} style={{ transform: `scale(${scale})` }}>
                {artboard}
              </div>
            </div>
          </div>
        </div>
      </div>
    </FitToViewport>
  )
}
