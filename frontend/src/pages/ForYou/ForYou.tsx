import { useRef, useState } from 'react'
import { Link } from 'react-router'
import AppNav from '../../components/AppNav/AppNav.tsx'
import MovieTile from '../../components/MovieTile/MovieTile.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import bgBlob from '../../assets/foryou/bg-blob.svg'
import pinIcon from '../../assets/foryou/icon-pin.svg'
import rainLgIcon from '../../assets/foryou/icon-rain-lg.svg'
import sparkleIcon from '../../assets/foryou/icon-sparkle.svg'
import filmIcon from '../../assets/foryou/icon-film.svg'
import rainSmIcon from '../../assets/foryou/icon-rain-sm.svg'
import sparkleSmIcon from '../../assets/foryou/icon-sparkle-sm.svg'
import usersIcon from '../../assets/foryou/icon-users.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import { PROFILE, SHELVES, TASTE, WEATHER, type Shelf } from './forYouData.ts'
import styles from './ForYou.module.css'

const SHELF_ICONS: Record<Shelf['icon'], string> = {
  film: filmIcon,
  rain: rainSmIcon,
  sparkle: sparkleSmIcon,
  users: usersIcon,
}

export default function ForYou() {
  const pageRef = useRef<HTMLDivElement>(null)
  useEntranceAnimation(pageRef)

  // TODO: persist in the user's settings once the API exists.
  const [adaptToWeather, setAdaptToWeather] = useState(true)
  const shelves = SHELVES.filter((shelf) => adaptToWeather || !shelf.weather)

  return (
    <div ref={pageRef} className={styles.page}>
      <div className={styles.background} aria-hidden="true">
        <img className={styles.blob} src={bgBlob} width={700} height={602.524} alt="" />
      </div>

      <AppNav />

      <main className={styles.main}>
        <section className={styles.hero} aria-labelledby="for-you-title">
          <div className={styles.intro} data-enter data-enter-delay="0.1">
            <SplitHeadline
              id="for-you-title"
              className={styles.title}
              accentClassName={styles.accent}
              lines={[{ text: 'Picked for' }, { text: 'you,', accentEnd: `${PROFILE.name}.` }]}
            />
            <p className={styles.lead}>{PROFILE.summary}</p>
            <ul className={styles.taste} aria-label="Your taste">
              {TASTE.map((chip) => (
                <li key={chip.label}>
                  <Link to={chip.to} className={styles.tasteChip} style={{ background: chip.color }}>
                    {chip.label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          {/* TODO: read the weather for the user's location from a weather API. */}
          <aside className={styles.weather} aria-label="Weather" data-enter data-enter-delay="0.3" data-enter-rise="80">
            <div className={styles.weatherTop}>
              <div className={styles.temp}>
                <span className={styles.place}>
                  <img src={pinIcon} width={16} height={16} alt="" />
                  {WEATHER.place}
                </span>
                <span className={styles.degrees}>{WEATHER.temp}</span>
                <span className={styles.condition}>{WEATHER.condition}</span>
              </div>
              <span className={styles.weatherIcon} aria-hidden="true">
                <img src={rainLgIcon} width={64} height={64} alt="" />
              </span>
            </div>
            <div className={styles.mood}>
              <span className={styles.moodLabel}>
                <img src={sparkleIcon} width={16} height={16} alt="" />
                WEATHER MOOD
              </span>
              <p className={styles.moodTitle}>{WEATHER.mood}</p>
              <p className={styles.moodText}>{WEATHER.moodText}</p>
            </div>
            <label className={styles.switch}>
              <input
                type="checkbox"
                role="switch"
                checked={adaptToWeather}
                onChange={(event) => setAdaptToWeather(event.target.checked)}
              />
              <span className={styles.switchTrack} aria-hidden="true" />
              Adapt suggestions to weather
            </label>
          </aside>
        </section>

        {shelves.map((shelf) => (
          <section key={shelf.id} className={styles.shelf} aria-labelledby={`shelf-${shelf.id}`}>
            <div className={styles.sectionHeader}>
              <h2 id={`shelf-${shelf.id}`} className={styles.sectionTitle}>
                {shelf.title}
              </h2>
              <Link to={shelf.more} className={styles.sectionAction}>
                More →
              </Link>
            </div>
            <span className={styles.reason}>
              <img src={SHELF_ICONS[shelf.icon]} width={14} height={14} alt="" />
              {shelf.reason}
            </span>
            <div className={styles.row}>
              {shelf.movies.map((movie) => (
                <MovieTile key={movie.slug} movie={movie} />
              ))}
            </div>
          </section>
        ))}
      </main>
    </div>
  )
}
