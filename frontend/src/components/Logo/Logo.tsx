import { Link } from 'react-router'
import logoMark from '../../assets/landing/logo-mark.svg'
import styles from './Logo.module.css'

type LogoProps = {
  theme?: 'light' | 'dark'
  className?: string
}

/** WatchWise lockup. Light = on cream/white grounds; Dark = on violet ground. */
export default function Logo({ theme = 'light', className }: LogoProps) {
  return (
    <Link
      to="/"
      className={[styles.logo, styles[theme], className].filter(Boolean).join(' ')}
      aria-label="WatchWise home"
    >
      <img src={logoMark} width={44} height={44} alt="" />
      <span className={styles.wordmark}>
        Watch<span className={styles.accent}>Wise</span>
      </span>
    </Link>
  )
}
