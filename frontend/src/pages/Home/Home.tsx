import { useCallback, useEffect, useRef, useState } from 'react'
import { Link } from 'react-router'
import { Cloud, CloudDrizzle, CloudFog, CloudLightning, CloudRain, CloudSnow, CloudSun, Sun, type LucideIcon } from 'lucide-react'
import AppNav from '../../components/AppNav/AppNav.tsx'
import ButtonPrimary from '../../components/ButtonPrimary/ButtonPrimary.tsx'
import MovieTile from '../../components/MovieTile/MovieTile.tsx'
import PillButton from '../../components/PillButton/PillButton.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import bgCoralBlob from '../../assets/home/bg-coral-blob.svg'
import bgVioletBlob from '../../assets/home/bg-violet-blob.svg'
import moonImg from '../../assets/home/pick-moon.svg'
import pickWave from '../../assets/home/pick-wave.svg'
import sparkleIcon from '../../assets/home/icon-sparkle.svg'
import playIcon from '../../assets/home/icon-play.svg'
import plusIcon from '../../assets/home/icon-plus.svg'
import checkIcon from '../../assets/watchlist/icon-check-sm.svg'
import calendarIcon from '../../assets/home/icon-calendar.svg'
import calendarSmIcon from '../../assets/home/icon-calendar-sm.svg'
import chatIcon from '../../assets/home/icon-chat.svg'
import onlineDot from '../../assets/home/dot-online.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import { BECAUSE_YOU_LOVED, RELEASES, TONIGHTS_PICK, gradientCss } from './homeData.ts'
import styles from './Home.module.css'
import WeeklyTrending from './WeeklyTrending.tsx'
import { useAuth } from '../../contexts/useAuth.ts'
import { api, useApi } from '../../lib/api.ts'
import { look, shortName, timeAgo } from '../../lib/format.ts'
import { movieFor, tmdbIdOf } from '../../lib/movies.ts'
import { useWatchlist } from '../../lib/watchlist.ts'
import type { ApiActivity, ApiHome } from '../../lib/types.ts'
import { formatWhen, localDateTime } from '../MovieNights/nightsData.ts'
import { toTileMovie, type TmdbMovie } from '../Discover/discoverApi.ts'
import type { Movie } from '../../components/MovieTile/MovieTile.tsx'

type WeatherResponse = {
  location: { latitude: number; longitude: number; is_default: boolean }
  current: {
    temperature_2m: number
    relative_humidity_2m: number
    apparent_temperature: number
    weather_code: number
    precipitation: number
    wind_speed_10m: number
  }
}

type WeatherMood = { title: string; mood: string; description: string }
type WeatherCache = { cachedAt: number; weather: WeatherResponse; mood?: WeatherMood }
const WEATHER_CACHE_KEY = 'watchwise:weather-mood:v1'
const WEATHER_CACHE_TTL = 60 * 60 * 1000
const RECOMMENDATION_CACHE_KEY = 'watchwise:weather-recommendations:v1'
type WeatherRecommendation = TmdbMovie & { reason: string }
type RecommendationCache = { cachedAt: number; latitude: number; longitude: number; movies: WeatherRecommendation[] }

function readRecommendationCache(location?: WeatherResponse['location']): RecommendationCache | null {
  if (!location) return null
  try {
    const raw = localStorage.getItem(RECOMMENDATION_CACHE_KEY)
    if (!raw) return null
    const cache = JSON.parse(raw) as Partial<RecommendationCache>
    const age = Date.now() - (cache.cachedAt ?? 0)
    const validMovies = Array.isArray(cache.movies) && cache.movies.every((movie) =>
      typeof movie?.id === 'number' && typeof movie.title === 'string' && typeof movie.reason === 'string',
    )
    if (cache.latitude !== location.latitude || cache.longitude !== location.longitude || !validMovies || age < 0 || age >= WEATHER_CACHE_TTL) {
      localStorage.removeItem(RECOMMENDATION_CACHE_KEY)
      return null
    }
    return cache as RecommendationCache
  } catch {
    try { localStorage.removeItem(RECOMMENDATION_CACHE_KEY) } catch { /* Storage may be unavailable. */ }
    return null
  }
}

function randomRecommendation(movies: WeatherRecommendation[]): Movie | null {
  if (movies.length === 0) return null
  const recommendation = movies[Math.floor(Math.random() * movies.length)]
  return { ...toTileMovie(recommendation, new Map()), recommendationReason: recommendation.reason }
}

function readWeatherCache(): WeatherCache | null {
  try {
    const raw = localStorage.getItem(WEATHER_CACHE_KEY)
    if (!raw) return null
    const cache = JSON.parse(raw) as Partial<WeatherCache>
    const age = Date.now() - (cache.cachedAt ?? 0)
    if (
      typeof cache.cachedAt !== 'number' || age < 0 || age >= WEATHER_CACHE_TTL ||
      typeof cache.weather?.location?.latitude !== 'number' ||
      typeof cache.weather.location.longitude !== 'number' ||
      typeof cache.weather.location.is_default !== 'boolean' ||
      typeof cache.weather.current?.temperature_2m !== 'number' ||
      typeof cache.weather.current.weather_code !== 'number' ||
      (cache.mood !== undefined && (typeof cache.mood.mood !== 'string' || typeof cache.mood.description !== 'string'))
    ) {
      localStorage.removeItem(WEATHER_CACHE_KEY)
      return null
    }
    return cache as WeatherCache
  } catch {
    try { localStorage.removeItem(WEATHER_CACHE_KEY) } catch { /* Storage may be unavailable. */ }
    return null
  }
}

function weatherLabel(code: number) {
  if (code === 0) return 'Clear sky'
  if ([1, 2, 3].includes(code)) return 'Partly cloudy'
  if ([45, 48].includes(code)) return 'Foggy'
  if ([51, 53, 55, 56, 57].includes(code)) return 'Drizzle'
  if ([61, 63, 65, 66, 67, 80, 81, 82].includes(code)) return 'Rain'
  if ([71, 73, 75, 77, 85, 86].includes(code)) return 'Snow'
  if ([95, 96, 99].includes(code)) return 'Thunderstorm'
  return 'Variable conditions'
}

const WEATHER_ICONS: Record<string, LucideIcon> = {
  'Clear sky': Sun,
  'Partly cloudy': CloudSun,
  Foggy: CloudFog,
  Drizzle: CloudDrizzle,
  Rain: CloudRain,
  Snow: CloudSnow,
  Thunderstorm: CloudLightning,
}

/** One line of "From people you follow": who, what, which film and where it links. */
function describe(item: ApiActivity) {
  const movie = movieFor(item.kind === 'list' ? (item.list.posters[0] ?? 0) : (item.tmdb_id ?? 0))
  switch (item.kind) {
    case 'rated':
      return { movie, action: 'rated', target: movie.title, suffix: '★'.repeat(item.rating), to: `/movies/${movie.slug}` }
    case 'reviewed':
      return { movie, action: 'reviewed', target: movie.title, suffix: `— “${item.review.content.slice(0, 60)}${item.review.content.length > 60 ? '…' : ''}”`, to: `/movies/${movie.slug}` }
    case 'list':
      return { movie, action: 'updated the public list', target: `“${item.list.title}”`, suffix: '', to: `/lists/${item.list.id}` }
    case 'watchlist':
      return { movie, action: 'added', target: movie.title, suffix: 'to their watchlist', to: `/movies/${movie.slug}` }
  }
}

// Kept short: "Good afternoon," is too wide for the two-line 84px headline.
function greeting(hour = new Date().getHours()) {
  if (hour < 12) return 'Good morning,'
  if (hour < 18) return 'Good day,'
  return 'Good evening,'
}

export default function Home() {
  const pageRef = useRef<HTMLDivElement>(null)
  // Tonight's pick "+ Watchlist" adds the film to the member's default Watchlist (and can take it off).
  const watchlist = useWatchlist()
  useEntranceAnimation(pageRef)
  const { firstName } = useAuth()
  const home = useApi<ApiHome>('/home').data
  const activity = home?.activity ?? []
  const [initialWeatherCache] = useState(readWeatherCache)
  const [initialRecommendationCache] = useState(() => readRecommendationCache(initialWeatherCache?.weather.location))
  const [weatherPick, setWeatherPick] = useState<Movie | null>(() => randomRecommendation(initialRecommendationCache?.movies ?? []))
  const [pickLoading, setPickLoading] = useState(initialRecommendationCache === null)
  const [weather, setWeather] = useState<WeatherResponse | null>(() => initialWeatherCache?.weather ?? null)
  const [weatherTimestamp, setWeatherTimestamp] = useState<number | null>(() => initialWeatherCache?.cachedAt ?? null)
  const [weatherLoading, setWeatherLoading] = useState(initialWeatherCache === null)
  const [weatherError, setWeatherError] = useState(false)
  const [locationStatus, setLocationStatus] = useState<'checking' | 'requesting' | 'granted' | 'unavailable'>('checking')

  const fetchWeatherRecommendations = useCallback(async (latitude: number, longitude: number) => {
    setPickLoading(true)
    try {
      const { data } = await api.get<{ movies: WeatherRecommendation[] }>('/movie-recommendations', {
        params: { latitude, longitude },
      })
      const movies = Array.isArray(data.movies) ? data.movies : []
      const cachedAt = Date.now()
      try {
        localStorage.setItem(RECOMMENDATION_CACHE_KEY, JSON.stringify({ cachedAt, latitude, longitude, movies } satisfies RecommendationCache))
      } catch { /* Keep recommendations usable if browser storage is unavailable. */ }
      setWeatherPick(randomRecommendation(movies))
    } catch (error) {
      console.error('Unable to load Home weather recommendations:', error)
      setWeatherPick(null)
    } finally {
      setPickLoading(false)
    }
  }, [])

  const fetchWeather = useCallback(async (latitude?: number, longitude?: number) => {
    setWeatherLoading(true)
    setWeatherError(false)
    try {
      const { data } = await api.get<WeatherResponse>('/weather', {
        params: latitude !== undefined && longitude !== undefined ? { latitude, longitude } : {},
      })
      const cachedAt = Date.now()
      setWeather(data)
      void fetchWeatherRecommendations(data.location.latitude, data.location.longitude)
      setWeatherTimestamp(cachedAt)
      try {
        localStorage.setItem(WEATHER_CACHE_KEY, JSON.stringify({ cachedAt, weather: data } satisfies WeatherCache))
      } catch {
        // Keep the live weather usable if browser storage is unavailable.
      }
      try {
        const { data: mood } = await api.post<WeatherMood>('/weather-mood', {
          condition: weatherLabel(data.current.weather_code),
          temperature: data.current.temperature_2m,
          humidity: data.current.relative_humidity_2m,
          wind: data.current.wind_speed_10m,
          precipitation: data.current.precipitation,
        })
        localStorage.setItem(WEATHER_CACHE_KEY, JSON.stringify({ cachedAt, weather: data, mood } satisfies WeatherCache))
      } catch (error) {
        console.error('Unable to refresh the shared weather mood cache:', error)
      }
    } catch (error) {
      console.error('Unable to load Home weather:', error)
      setWeatherError(true)
      setWeatherTimestamp(null)
      setPickLoading(false)
    } finally {
      setWeatherLoading(false)
    }
  }, [fetchWeatherRecommendations])

  const useDefaultLocation = useCallback(() => {
    try { localStorage.removeItem(WEATHER_CACHE_KEY) } catch { /* Storage may be unavailable. */ }
    try { localStorage.removeItem(RECOMMENDATION_CACHE_KEY) } catch { /* Storage may be unavailable. */ }
    setWeather(null)
    setWeatherPick(null)
    setPickLoading(true)
    setWeatherTimestamp(null)
    void fetchWeather()
  }, [fetchWeather])

  useEffect(() => {
    if (initialWeatherCache && !initialRecommendationCache) {
      void fetchWeatherRecommendations(initialWeatherCache.weather.location.latitude, initialWeatherCache.weather.location.longitude)
    }
  }, [fetchWeatherRecommendations, initialRecommendationCache, initialWeatherCache])

  const requestLocation = useCallback(() => {
    if (!navigator.geolocation) {
      setLocationStatus('unavailable')
      if (!initialWeatherCache?.weather.location.is_default) useDefaultLocation()
      return
    }
    setLocationStatus('requesting')
    navigator.geolocation.getCurrentPosition(
      ({ coords }) => {
        setLocationStatus('granted')
        void fetchWeather(coords.latitude, coords.longitude)
      },
      () => {
        setLocationStatus('unavailable')
        if (!initialWeatherCache?.weather.location.is_default) useDefaultLocation()
      },
    )
  }, [fetchWeather, initialWeatherCache, useDefaultLocation])

  useEffect(() => {
    let active = true
    let permission: PermissionStatus | null = null
    const syncPermission = () => {
      if (!active || !permission) return
      if (permission.state === 'denied') {
        setLocationStatus('unavailable')
        if (!initialWeatherCache?.weather.location.is_default) useDefaultLocation()
      } else if (permission.state === 'granted' && initialWeatherCache && !initialWeatherCache.weather.location.is_default) {
        setLocationStatus('granted')
      } else {
        requestLocation()
      }
    }
    if (!navigator.permissions?.query) {
      requestLocation()
      return () => { active = false }
    }
    void navigator.permissions.query({ name: 'geolocation' }).then((status) => {
      if (!active) return
      permission = status
      status.addEventListener('change', syncPermission)
      syncPermission()
    }).catch(() => {
      if (active) requestLocation()
    })
    return () => {
      active = false
      permission?.removeEventListener('change', syncPermission)
    }
  }, [initialWeatherCache, requestLocation, useDefaultLocation])

  const requestFreshWeather = useCallback(() => {
    if (locationStatus === 'unavailable') useDefaultLocation()
    else requestLocation()
  }, [locationStatus, requestLocation, useDefaultLocation])

  useEffect(() => {
    if (weatherTimestamp === null) return
    const timeout = window.setTimeout(
      requestFreshWeather,
      Math.max(0, weatherTimestamp + WEATHER_CACHE_TTL - Date.now()),
    )
    return () => window.clearTimeout(timeout)
  }, [requestFreshWeather, weatherTimestamp])

  const canShowWeather = locationStatus === 'granted' || (locationStatus === 'unavailable' && weather?.location.is_default === true)
  const currentWeather = canShowWeather ? weather : null
  const condition = currentWeather ? weatherLabel(currentWeather.current.weather_code) : null
  const conditionText = condition === 'Clear sky' ? 'Sunny'
    : condition === 'Rain' ? 'Rainy'
    : condition === 'Drizzle' ? 'Drizzly'
      : condition === 'Snow' ? 'Snowy'
        : condition === 'Thunderstorm' ? 'Stormy'
          : condition
  const WeatherIcon = condition ? WEATHER_ICONS[condition] ?? Cloud : Cloud
  const weatherSummary = (locationStatus === 'checking' || locationStatus === 'requesting' || weatherLoading) && !currentWeather
    ? 'Checking current weather…'
    : weatherError && !currentWeather
      ? 'Weather is temporarily unavailable.'
      : `${Math.round(currentWeather?.current.temperature_2m ?? 0)}° · ${conditionText ?? 'Current conditions'} in ${currentWeather?.location.is_default ? 'Beirut' : 'your area'}`

  const pick = weatherPick
  const pickId = pick ? tmdbIdOf(pick) : 0
  const onWatchlist = pickId > 0 && watchlist.has(pickId)
  const togglePick = () => {
    if (!pick || !watchlist.loaded || !(pickId > 0)) return
    const request = onWatchlist ? watchlist.remove(pickId) : watchlist.add(pickId)
    request.catch(() => undefined)
  }
  const night = home?.next_night

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
              <WeatherIcon size={14} aria-hidden="true" />
              {weatherSummary}
            </span>
            <SplitHeadline
              id="home-title"
              className={styles.title}
              accentClassName={styles.accent}
              lines={[{ text: greeting() }, { text: `${firstName}.`, accent: true }]}
            />
            <p className={styles.lead}>
              {condition
                ? `${conditionText} weather near ${currentWeather?.location.is_default ? 'Beirut' : 'you'} is shaping today’s movie picks.`
                : 'Today’s movie picks adapt to the current weather.'}
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
            style={{ background: gradientCss((pick ?? TONIGHTS_PICK.movie).gradient) }}
            aria-busy={pickLoading}
            data-enter
            data-enter-delay="0.3"
            data-enter-rise="80"
          >
            {pick ? (
              <>
                {pick.posterUrl && <img className={styles.pickPoster} src={pick.posterUrl} alt={`${pick.title} poster`} />}
                <img className={styles.pickMoon} src={moonImg} width={220} height={220} alt="" />
                <img className={styles.pickWave} src={pickWave} width={660} height={220} alt="" />
                <div className={styles.pickInfo}>
                  <span className={styles.chipYellow}>
                    <img src={sparkleIcon} width={14} height={14} alt="" />
                    Tonight&apos;s pick · {currentWeather ? `a ${conditionText?.toLowerCase()}-weather pick` : TONIGHTS_PICK.reason}
                  </span>
                  <h2 className={styles.pickTitle}>
                    <Link to={`/movies/${pick.slug}`} className={styles.pickLink}>{pick.title}</Link>
                  </h2>
                  <p className={styles.pickMeta}>{pick.recommendationReason}</p>
                  <div className={styles.pickActions}>
                    <Link to={`/movies/${pick.slug}#trailer`} className={styles.trailer}>
                      <img src={playIcon} width={14} height={14} alt="" />
                      Watch trailer
                    </Link>
                    <button
                      type="button"
                      className={styles.watchlist}
                      aria-pressed={onWatchlist}
                      aria-busy={(pickId > 0 && watchlist.isPending(pickId)) || undefined}
                      onClick={togglePick}
                    >
                      <img src={onWatchlist ? checkIcon : plusIcon} width={14} height={14} alt="" />
                      {onWatchlist ? 'On watchlist' : 'Watchlist'}
                    </button>
                  </div>
                </div>
              </>
            ) : pickLoading ? (
              <div className={styles.pickLoading} role="status" aria-label="Loading tonight’s movie pick">
                <span className={`${styles.pickSkeleton} ${styles.pickSkeletonLabel}`} />
                <span className={`${styles.pickSkeleton} ${styles.pickSkeletonTitle}`} />
                <span className={`${styles.pickSkeleton} ${styles.pickSkeletonMeta}`} />
                <span className={`${styles.pickSkeleton} ${styles.pickSkeletonAction}`} />
              </div>
            ) : (
              <p className={styles.pickUnavailable} role="status">Tonight&apos;s pick is temporarily unavailable.</p>
            )}
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

        <WeeklyTrending />

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
            {activity.map((item) => {
              const line = describe(item)
              const face = look(item.user)
              return (
                <Link key={item.id} to={line.to} className={styles.activityItem}>
                  <span className={styles.avatar} style={{ background: face.color }}>
                    {face.initials}
                  </span>
                  <span className={styles.activityText}>
                    <span className={styles.activityLine}>
                      <strong>{shortName(item.user.name)}</strong> {line.action} <strong>{line.target}</strong>
                      {line.suffix && ` ${line.suffix}`}
                    </span>
                    <span className={styles.activityTime}>{timeAgo(item.created_at)}</span>
                  </span>
                  <span className={styles.thumb} style={{ background: gradientCss(line.movie.gradient) }} />
                </Link>
              )
            })}
            {home && activity.length === 0 && (
              <p className={styles.activityTime}>
                Nothing yet — <Link to="/discover">follow people</Link> to see what they watch, rate and list.
              </p>
            )}
          </section>

          <div className={styles.side}>
            <section className={styles.rooms} aria-labelledby="home-rooms">
              <div className={styles.roomsHead}>
                <img src={chatIcon} width={20} height={20} alt="" />
                <h2 id="home-rooms" className={styles.cardTitleLight}>
                  Discussion rooms
                </h2>
              </div>
              {(home?.rooms ?? []).map((room) => (
                <Link key={room.id} to={`/rooms/${room.id}`} className={styles.room}>
                  <span className={styles.roomName}>{room.name}</span>
                  <span className={styles.online}>
                    <img src={onlineDot} width={8} height={8} alt="" />
                    {room.members} {room.members === 1 ? 'member' : 'members'}
                  </span>
                </Link>
              ))}
              <PillButton to="/rooms" variant="accent">
                Join a room
              </PillButton>
            </section>

            {night ? (
            <section className={styles.night} aria-labelledby="home-night">
              <span className={styles.chipYellowTint}>
                <img src={calendarSmIcon} width={14} height={14} alt="" />
                {formatWhen(localDateTime(night.scheduled_at))}
              </span>
              <h2 id="home-night" className={styles.nightTitle}>
                {night.title}
              </h2>
              <p className={styles.nightText}>
                {night.leader
                  ? `${movieFor(night.leader.tmdb_id).title} is leading with ${night.leader.votes} vote${night.leader.votes === 1 ? '' : 's'}.`
                  : 'No votes yet — pick your favourite from the shortlist.'}
              </p>
              <div className={styles.nightFoot}>
                <div className={styles.guests}>
                  {night.guests.slice(0, 3).map((guest) => (
                    <span key={guest.id} className={styles.guest} style={{ background: look(guest).color }}>
                      {look(guest).initials}
                    </span>
                  ))}
                  {night.guests.length > 3 && (
                    <span className={styles.guest} style={{ background: 'var(--surface-violet-tint)' }}>
                      +{night.guests.length - 3}
                    </span>
                  )}
                </div>
                <PillButton to={`/movie-nights/${night.id}`}>Vote now</PillButton>
              </div>
            </section>
            ) : (
            <section className={styles.night} aria-labelledby="home-night">
              <h2 id="home-night" className={styles.nightTitle}>
                No movie night planned
              </h2>
              <p className={styles.nightText}>Get friends together, shortlist a few films and vote on the winner.</p>
              <div className={styles.nightFoot}>
                <PillButton to="/movie-nights">Plan one</PillButton>
              </div>
            </section>
            )}
          </div>
        </div>
      </main>
    </div>
  )
}
