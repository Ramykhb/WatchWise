import { useRef, useState } from 'react'
import { Link } from 'react-router'
import AppNav from '../../components/AppNav/AppNav.tsx'
import ButtonPrimary from '../../components/ButtonPrimary/ButtonPrimary.tsx'
import MovieTile from '../../components/MovieTile/MovieTile.tsx'
import PillButton from '../../components/PillButton/PillButton.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import bgCoralBlob from '../../assets/home/bg-coral-blob.svg'
import bgVioletBlob from '../../assets/home/bg-violet-blob.svg'
import rainIcon from '../../assets/home/icon-rain.svg'
import moonImg from '../../assets/home/pick-moon.svg'
import pickWave from '../../assets/home/pick-wave.svg'
import sparkleIcon from '../../assets/home/icon-sparkle.svg'
import playIcon from '../../assets/home/icon-play.svg'
import plusIcon from '../../assets/home/icon-plus.svg'
import calendarIcon from '../../assets/home/icon-calendar.svg'
import calendarSmIcon from '../../assets/home/icon-calendar-sm.svg'
import chatIcon from '../../assets/home/icon-chat.svg'
import onlineDot from '../../assets/home/dot-online.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import {
  ACTIVITY,
  BECAUSE_YOU_LOVED,
  MOVIE_NIGHT,
  POPULAR,
  RELEASES,
  ROOMS,
  TONIGHTS_PICK,
  WEATHER,
  gradientCss,
} from './homeData.ts'
import styles from './Home.module.css'

// TODO: take the name from the signed-in user once auth is connected.
const USER_NAME = 'Maya'

// Kept short: "Good afternoon," is too wide for the two-line 84px headline.
function greeting(hour = new Date().getHours()) {
  if (hour < 12) return 'Good morning,'
  if (hour < 18) return 'Good day,'
  return 'Good evening,'
}

export default function Home() {
  const pageRef = useRef<HTMLDivElement>(null)
  const [onWatchlist, setOnWatchlist] = useState(false)
  useEntranceAnimation(pageRef)

  const pick = TONIGHTS_PICK.movie

  return (
    <div ref={pageRef} className={styles.page}>
      <div className={styles.background} aria-hidden="true">
        <img className={styles.coralBlob} src={bgCoralBlob} width={300} height={260} alt="" />
        <img className={styles.violetBlob} src={bgVioletBlob} width={760} height={560} alt="" />
      </div>

      <AppNav />

      <main className={styles.main}>
        <section className={styles.hero} aria-labelledby="home-title">
          <div className={styles.greeting} data-enter data-enter-delay="0.1">
            <span className={styles.chipWhite}>
              <img src={rainIcon} width={14} height={14} alt="" />
              {WEATHER}
            </span>
            <SplitHeadline
              id="home-title"
              className={styles.title}
              accentClassName={styles.accent}
              lines={[{ text: greeting() }, { text: `${USER_NAME}.`, accent: true }]}
            />
            <p className={styles.lead}>
              Rainy nights call for cozy thrillers and big-hearted dramas. We picked a few based on your ratings and
              the weather.
            </p>
            <div className={styles.ctas}>
              <ButtonPrimary label="Browse picks" to="/for-you" />
              <PillButton to="/movie-nights" variant="outline">
                Plan a movie night
              </PillButton>
            </div>
          </div>

          <article
            className={styles.pick}
            style={{ background: gradientCss(pick.gradient) }}
            data-enter
            data-enter-delay="0.3"
            data-enter-rise="80"
          >
            <img className={styles.pickMoon} src={moonImg} width={220} height={220} alt="" />
            <img className={styles.pickWave} src={pickWave} width={660} height={220} alt="" />
            <div className={styles.pickInfo}>
              <span className={styles.chipYellow}>
                <img src={sparkleIcon} width={14} height={14} alt="" />
                Tonight&apos;s pick · {TONIGHTS_PICK.reason}
              </span>
              <h2 className={styles.pickTitle}>
                <Link to={`/movies/${pick.slug}`} className={styles.pickLink}>
                  {pick.title}
                </Link>
              </h2>
              <p className={styles.pickMeta}>{TONIGHTS_PICK.details}</p>
              <div className={styles.pickActions}>
                <button type="button" className={styles.trailer}>
                  <img src={playIcon} width={14} height={14} alt="" />
                  Watch trailer
                </button>
                <button
                  type="button"
                  className={styles.watchlist}
                  aria-pressed={onWatchlist}
                  onClick={() => setOnWatchlist((value) => !value)}
                >
                  <img src={plusIcon} width={14} height={14} alt="" />
                  {onWatchlist ? 'On watchlist' : 'Watchlist'}
                </button>
              </div>
            </div>
          </article>
        </section>

        <section className={styles.section} aria-labelledby="home-because">
          <div className={styles.sectionHeader}>
            <h2 id="home-because" className={styles.sectionTitle}>
              {BECAUSE_YOU_LOVED.title}
            </h2>
            <Link to="/for-you" className={styles.sectionAction}>
              See all →
            </Link>
          </div>
          <ul className={styles.reasons}>
            {BECAUSE_YOU_LOVED.reasons.map((reason) => (
              <li key={reason} className={styles.chipWhite}>
                {reason}
              </li>
            ))}
          </ul>
          <div className={styles.row}>
            {BECAUSE_YOU_LOVED.movies.map((movie) => (
              <MovieTile key={movie.slug} movie={movie} />
            ))}
          </div>
        </section>

        <section className={styles.section} aria-labelledby="home-popular">
          <div className={styles.sectionHeader}>
            <h2 id="home-popular" className={styles.sectionTitle}>
              Popular this week
            </h2>
            <Link to="/discover" className={styles.sectionAction}>
              View on Discover →
            </Link>
          </div>
          <div className={styles.row}>
            {POPULAR.map((movie) => (
              <MovieTile key={movie.slug} movie={movie} />
            ))}
          </div>
        </section>

        <section className={styles.section} aria-labelledby="home-releases">
          <div className={styles.sectionHeader}>
            <h2 id="home-releases" className={styles.sectionTitle}>
              Coming soon
            </h2>
            <Link to="/releases" className={styles.sectionAction}>
              All releases →
            </Link>
          </div>
          <div className={styles.releases}>
            {RELEASES.map((release) => (
              <div key={release.title} className={styles.release}>
                <div className={styles.date} style={{ background: gradientCss(release.gradient) }}>
                  <span className={styles.month}>{release.month}</span>
                  <span className={styles.day}>{release.day}</span>
                </div>
                <div className={styles.releaseText}>
                  <p className={styles.releaseTitle}>{release.title}</p>
                  <p className={styles.releaseMeta}>{release.meta}</p>
                </div>
                <button type="button" className={styles.calendar} aria-label={`Add ${release.title} to calendar`}>
                  <img src={calendarIcon} width={20} height={20} alt="" />
                </button>
              </div>
            ))}
          </div>
        </section>

        <div className={styles.social}>
          <section className={styles.activity} aria-labelledby="home-activity">
            <h2 id="home-activity" className={styles.cardTitle}>
              From people you follow
            </h2>
            {ACTIVITY.map((item) => (
              <Link key={item.name} to={`/movies/${item.movie.slug}`} className={styles.activityItem}>
                <span className={styles.avatar} style={{ background: item.color }}>
                  {item.initials}
                </span>
                <span className={styles.activityText}>
                  <span className={styles.activityLine}>
                    <strong>{item.name}</strong> {item.action} <strong>{item.target}</strong>
                    {item.suffix && ` ${item.suffix}`}
                  </span>
                  <span className={styles.activityTime}>{item.time}</span>
                </span>
                <span className={styles.thumb} style={{ background: gradientCss(item.movie.gradient) }} />
              </Link>
            ))}
          </section>

          <div className={styles.side}>
            <section className={styles.rooms} aria-labelledby="home-rooms">
              <div className={styles.roomsHead}>
                <img src={chatIcon} width={20} height={20} alt="" />
                <h2 id="home-rooms" className={styles.cardTitleLight}>
                  Live discussion rooms
                </h2>
              </div>
              {ROOMS.map((room) => (
                <Link key={room.name} to="/rooms" className={styles.room}>
                  <span className={styles.roomName}>{room.name}</span>
                  <span className={styles.online}>
                    <img src={onlineDot} width={8} height={8} alt="" />
                    {room.online} online
                  </span>
                </Link>
              ))}
              <PillButton to="/rooms" variant="accent">
                Join a room
              </PillButton>
            </section>

            <section className={styles.night} aria-labelledby="home-night">
              <span className={styles.chipYellowTint}>
                <img src={calendarSmIcon} width={14} height={14} alt="" />
                {MOVIE_NIGHT.when}
              </span>
              <h2 id="home-night" className={styles.nightTitle}>
                {MOVIE_NIGHT.title}
              </h2>
              <p className={styles.nightText}>{MOVIE_NIGHT.text}</p>
              <div className={styles.nightFoot}>
                <div className={styles.guests}>
                  {MOVIE_NIGHT.guests.map((guest) => (
                    <span key={guest.initials} className={styles.guest} style={{ background: guest.color }}>
                      {guest.initials}
                    </span>
                  ))}
                  <span className={styles.guest} style={{ background: 'var(--surface-violet-tint)' }}>
                    +{MOVIE_NIGHT.extraGuests}
                  </span>
                </div>
                <PillButton to="/movie-nights">Vote now</PillButton>
              </div>
            </section>
          </div>
        </div>
      </main>
    </div>
  )
}
