import { useCallback, useEffect, useRef, useState } from 'react'
import axios from 'axios'
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
import { PROFILE, SHELVES, TASTE, WEATHER_MOOD, type Shelf } from './forYouData.ts'
import styles from './ForYou.module.css'

const SHELF_ICONS: Record<Shelf['icon'], string> = {
  film: filmIcon,
  rain: rainSmIcon,
  sparkle: sparkleSmIcon,
  users: usersIcon,
}

const BACKEND_URL = import.meta.env.VITE_BACKEND_URL

type WeatherResponse = {
  location: {
    latitude: number
    longitude: number
    is_default: boolean
  }
  current: {
    temperature_2m: number
    relative_humidity_2m: number
    apparent_temperature: number
    weather_code: number
    precipitation: number
    wind_speed_10m: number
  }
}

const weatherCondition = (code: number): string => {
  if (code === 0) return 'Clear sky'
  if ([1, 2, 3].includes(code)) return 'Partly cloudy'
  if ([45, 48].includes(code)) return 'Foggy'
  if ([51, 53, 55, 56, 57].includes(code)) return 'Drizzle'
  if ([61, 63, 65, 66, 67, 80, 81, 82].includes(code)) return 'Rain'
  if ([71, 73, 75, 77, 85, 86].includes(code)) return 'Snow'
  if ([95, 96, 99].includes(code)) return 'Thunderstorm'

  return 'Variable conditions'
}

export default function ForYou() {
  const pageRef = useRef<HTMLDivElement>(null)
  const [locationStatus, setLocationStatus] = useState<
    'requesting' | 'granted' | 'unavailable'
  >('requesting')
  const [weather, setWeather] = useState<WeatherResponse | null>(null)
  const [weatherError, setWeatherError] = useState(false)
  const [weatherLoading, setWeatherLoading] = useState(true)
  useEntranceAnimation(pageRef)

  // TODO: persist in the user's settings once the API exists.
  const [adaptToWeather, setAdaptToWeather] = useState(true)
  const shelves = SHELVES.filter((shelf) => adaptToWeather || !shelf.weather)

  const fetchWeather = useCallback(async (latitude?: number, longitude?: number) => {
    setWeatherLoading(true)
    setWeatherError(false)

    try {
      const { data } = await axios.get<WeatherResponse>(`${BACKEND_URL}/api/weather`, {
        params: {
          ...(latitude !== undefined && longitude !== undefined ? { latitude, longitude } : {}),
        },
      })
      setWeather(data)
    } catch (error) {
      console.error('Unable to load weather:', error)
      setWeatherError(true)
    } finally {
      setWeatherLoading(false)
    }
  }, [])

  const requestLocation = useCallback(() => {
    if (typeof navigator === 'undefined' || !navigator.geolocation) {
      setLocationStatus('unavailable')
      void fetchWeather()
      return
    }

    setLocationStatus('requesting')
    navigator.geolocation.getCurrentPosition(
      ({ coords }) => {
        setLocationStatus('granted')
        console.log('User location:', {
          latitude: coords.latitude,
          longitude: coords.longitude,
        })
        void fetchWeather(coords.latitude, coords.longitude)
      },
      () => {
        setLocationStatus('unavailable')
        void fetchWeather()
      },
    )
  }, [fetchWeather])

  useEffect(() => {
    const requestId = window.setTimeout(requestLocation, 0)

    return () => window.clearTimeout(requestId)
  }, [requestLocation])

  const place = weather?.location.is_default ? 'Beirut · now' : 'Your location · now'
  const current = weather?.current
  const condition = current ? weatherCondition(current.weather_code) : 'Loading weather…'

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

          <aside
            className={styles.weather}
            aria-label="Weather"
            aria-busy={weatherLoading}
            data-enter
            data-enter-delay="0.3"
            data-enter-rise="80"
          >
            <div className={styles.weatherTop}>
              <div className={styles.temp}>
                <span className={styles.place}>
                  <img src={pinIcon} width={16} height={16} alt="" />
                  {place}
                </span>
                <span className={styles.degrees}>
                  {weatherLoading ? (
                    <span className={styles.loadingValue}>Loading…</span>
                  ) : (
                    `${Math.round(current?.temperature_2m ?? 0)}°`
                  )}
                </span>
                <span className={styles.condition}>
                  {condition}
                  {current && ` · feels like ${Math.round(current.apparent_temperature)}°`}
                </span>
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
              <p className={styles.moodTitle}>{WEATHER_MOOD.mood}</p>
              <p className={styles.moodText}>
                {weatherError
                  ? 'Weather data is temporarily unavailable. Please try again shortly.'
                  : WEATHER_MOOD.moodText}
              </p>
            </div>
            {current && (
              <p className={styles.weatherDetails}>
                Humidity {current.relative_humidity_2m}% · Precipitation {current.precipitation} mm ·
                Wind {Math.round(current.wind_speed_10m)} km/h
              </p>
            )}
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
            {locationStatus === 'unavailable' && (
              <div className={styles.locationPrompt} role="status">
                <p className={styles.locationNote}>
                  Allow location services to personalize weather suggestions.
                </p>
              </div>
            )}
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
