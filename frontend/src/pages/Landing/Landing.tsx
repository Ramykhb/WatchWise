import { Link, NavLink } from 'react-router'
import FitToViewport from '../../components/FitToViewport/FitToViewport.tsx'
import Logo from '../../components/Logo/Logo.tsx'
import ButtonPrimary from '../../components/ButtonPrimary/ButtonPrimary.tsx'
import TrendingCard, { type TrendingMovie } from '../../components/TrendingCard/TrendingCard.tsx'
import bgPeachBlob from '../../assets/landing/bg-peach-blob.svg'
import bgCoralWave from '../../assets/landing/bg-coral-wave.svg'
import bgVioletGround from '../../assets/landing/bg-violet-ground.svg'
import bgYellowBlob from '../../assets/landing/bg-yellow-blob.svg'
import decoRing from '../../assets/landing/deco-ring.png'
import dotCoral from '../../assets/landing/deco-dot-coral.svg'
import dotMintLg from '../../assets/landing/deco-dot-mint-lg.svg'
import dotMintSm from '../../assets/landing/deco-dot-mint-sm.svg'
import dotYellow from '../../assets/landing/deco-dot-yellow.svg'
import starBadgeIcon from '../../assets/landing/icon-star-badge.svg'
import plusInkIcon from '../../assets/landing/icon-plus-ink.svg'
import styles from './Landing.module.css'

const NAV_LINKS = [
  { to: '/discover', label: 'Discover' },
  { to: '/lists', label: 'Lists' },
  { to: '/community', label: 'Community' },
]

const STATS = [
  { value: '2M+', label: 'films logged' },
  { value: '480k', label: 'lists shared' },
  { value: '4.9', label: 'app rating' },
]

const TRENDING: TrendingMovie = {
  title: 'Neon Tide',
  posterTitle: ['NEON', 'TIDE'],
  rating: '4.8',
  meta: '2026 · Sci-fi adventure · 2h 14m',
  genres: ['Sci-fi', 'Adventure', 'Ocean'],
  rank: '#1 this week',
  watching: '12.4k watching',
}

export default function Landing() {
  return (
    <FitToViewport designHeight={1000}>
      <div className={styles.page}>
        {/* Background "ground" layers */}
        <div className={styles.background} aria-hidden="true">
          <img className={styles.bgLayer} src={bgPeachBlob} width={1440} height={1000} alt="" />
          <img className={`${styles.bgLayer} ${styles.wave}`} src={bgCoralWave} width={1440} height={1000} alt="" />
          <img className={`${styles.bgLayer} ${styles.wave}`} src={bgVioletGround} width={1440} height={1000} alt="" />
          <img className={`${styles.bgLayer} ${styles.wave}`} src={bgYellowBlob} width={1440} height={1000} alt="" />
        </div>

        <div className={styles.stage}>
          <header className={styles.header}>
            <Logo />
            <nav className={styles.nav} aria-label="Main">
              {NAV_LINKS.map((link) => (
                <NavLink key={link.to} to={link.to} className={styles.navLink}>
                  {link.label}
                </NavLink>
              ))}
              <Link to="/signin" className={styles.signIn}>
                Sign in
              </Link>
            </nav>
          </header>

          <main className={styles.main}>
            <section className={`${styles.hero} ${styles.enter}`} aria-labelledby="landing-title">
              <p className={styles.eyebrow}>
                <span className={styles.starBadge}>
                  <img src={starBadgeIcon} width={14} height={14} alt="" />
                </span>
                Loved by 2M+ film fans
              </p>
              <h1 id="landing-title" className={styles.headline}>
                <span>Track</span>
                <span>every</span>
                <span className={styles.accent}>frame.</span>
              </h1>
              <p className={styles.subheadline}>
                Log what you watch, rate what you love, and find your next favorite. Your personal film diary — playful,
                social, and beautifully organized.
              </p>
              <ButtonPrimary label="Get Started" to="/signup" />
              <dl className={styles.stats}>
                {STATS.map((stat) => (
                  <div key={stat.label} className={styles.stat}>
                    <dt>{stat.label}</dt>
                    <dd>{stat.value}</dd>
                  </div>
                ))}
              </dl>
            </section>

            <div className={styles.visual}>
              <img className={styles.ring} src={decoRing} width={260} height={260} alt="" aria-hidden="true" />
              <div className={`${styles.cardLift} ${styles.enter}`}>
                <TrendingCard movie={TRENDING} className={styles.card} />
              </div>
              <div className={`${styles.sticker} ${styles.pop}`} aria-hidden="true">
                <div className={styles.stickerFace}>
                  <span className={styles.stickerScore}>4.8</span>
                  <span className={styles.stickerLabel}>CRITICS</span>
                </div>
              </div>
              <div className={`${styles.toastWrap} ${styles.enter}`} role="status">
                <div className={styles.toast}>
                  <span className={styles.toastIcon}>
                    <img src={plusInkIcon} width={18} height={18} alt="" />
                  </span>
                  <span className={styles.toastText}>
                    <strong>Added to Watchlist</strong>
                    <span>Neon Tide · just now</span>
                  </span>
                </div>
              </div>
            </div>
          </main>

          <img className={`${styles.dot} ${styles.dotCoral}`} src={dotCoral} width={16} height={16} alt="" aria-hidden="true" />
          <img className={`${styles.dot} ${styles.dotMintLg}`} src={dotMintLg} width={22} height={22} alt="" aria-hidden="true" />
          <img className={`${styles.dot} ${styles.dotMintSm}`} src={dotMintSm} width={14} height={14} alt="" aria-hidden="true" />
          <img className={`${styles.dot} ${styles.dotYellow}`} src={dotYellow} width={12} height={12} alt="" aria-hidden="true" />
        </div>
      </div>
    </FitToViewport>
  )
}
