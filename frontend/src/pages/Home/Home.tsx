import { LoadingIndicator } from '../../components/LoadingState/LoadingState.tsx'
import { useCallback, useEffect, useRef, useState } from 'react'
import { Link } from 'react-router'
import { Cloud, CloudDrizzle, CloudFog, CloudLightning, CloudRain, CloudSnow, CloudSun, Sun, type LucideIcon } from 'lucide-react'
import AppNav from '../../components/AppNav/AppNav.tsx'
import ButtonPrimary from '../../components/ButtonPrimary/ButtonPrimary.tsx'
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
import { RELEASE_GRADIENTS, gradientCss } from './homeData.ts'
import styles from './Home.module.css'
import WeeklyTrending from './WeeklyTrending.tsx'
import FollowingActivity from './FollowingActivity.tsx'
import { useAuth } from '../../contexts/useAuth.ts'
import { api, useApi } from '../../lib/api.ts'
import { look } from '../../lib/format.ts'
import { movieFor, tmdbIdOf } from '../../lib/movies.ts'
import { useWatchlist } from '../../lib/watchlist.ts'
import type { ApiHome } from '../../lib/types.ts'
import { formatWhen, localDateTime } from '../MovieNights/nightsData.ts'
import { loadGenres, toTileMovie, type MoviePage, type TmdbMovie } from '../Discover/discoverApi.ts'
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
  const { data: home, loading: homeLoading, error: homeError, reload: reloadHome } = useApi<ApiHome>('/home')
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
    ? <LoadingIndicator label="Checking current weather…" />
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
            style={{ background: gradientCss(pick?.gradient ?? ['#ccbfff', '#8c66d9', '#331a66']) }}
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
                    Tonight&apos;s pick · {currentWeather ? `a ${conditionText?.toLowerCase()}-weather pick` : 'a weather-inspired pick'}
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

        <WeeklyTrending />

        <ComingSoon />

        <div className={styles.social}>
          <FollowingActivity activity={activity} loading={homeLoading} error={homeError} reload={reloadHome} />

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

const DAY = 86_400_000
const RELATIVE_TIME = new Intl.RelativeTimeFormat('en-US', { numeric: 'auto' })

/** "tomorrow", "in 9 days", "in 3 weeks" or "in 2 months". */
function releasesIn(days: number) {
  if (days < 14) return RELATIVE_TIME.format(days, 'day')
  if (days < 60) return RELATIVE_TIME.format(Math.round(days / 7), 'week')
  return RELATIVE_TIME.format(Math.round(days / 30), 'month')
}

/**
 * The three most anticipated films from the upcoming list (it comes most popular first), soonest first.
 * Days count in the viewer's time zone, so a film that is already out here is skipped.
 */
function upcomingReleases(movies: TmdbMovie[], genres: Map<number, string>) {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  return movies
    .flatMap((movie) => {
      if (!movie.release_date) return []
      const date = new Date(`${movie.release_date}T00:00:00`)
      const days = Math.round((date.getTime() - today.getTime()) / DAY)
      if (days < 1) return []
      const genre = movie.genre_ids.map((id) => genres.get(id)).find(Boolean)
      return [{
        id: movie.id,
        title: movie.title,
        posterUrl: movie.poster_url,
        days,
        month: date.toLocaleDateString('en-US', { month: 'short' }).toUpperCase(),
        day: date.toLocaleDateString('en-US', { day: '2-digit' }),
        meta: [genre, releasesIn(days)].filter(Boolean).join(' · '),
      }]
    })
    .slice(0, 3)
    .sort((a, b) => a.days - b.days)
}

/** Coming soon: the next big releases from TMDB, each linking to its movie page. */
function ComingSoon() {
  const { data, error, loading, reload } = useApi<MoviePage>('/movies?list=upcoming')
  // Genre names for the meta line. Loaded on the side: the releases never wait for them.
  const [genres, setGenres] = useState<Map<number, string>>(() => new Map())

  useEffect(() => {
    let active = true
    loadGenres()
      .then((map) => active && setGenres(map))
      // Without the names, a release just says when it comes out.
      .catch((error: unknown) => console.error('Unable to load genre names:', error))
    return () => {
      active = false
    }
  }, [])

  const releases = data && upcomingReleases(data.movies, genres)

  return (
    <section className={styles.section} aria-labelledby="home-releases">
      <div className={styles.sectionHeader}>
        <h2 id="home-releases" className={styles.sectionTitle}>
          Coming soon
        </h2>
        <Link to="/releases" className={styles.sectionAction}>
          All releases →
        </Link>
      </div>
      {error && !loading ? (
        <p className={styles.releasesState} role="status">
          Upcoming releases are temporarily unavailable.{' '}
          <button type="button" onClick={reload}>Try again</button>
        </p>
      ) : releases?.length === 0 ? (
        <p className={styles.releasesState} role="status">No upcoming releases right now.</p>
      ) : (
        <div className={styles.releases} aria-busy={!releases}>
          {releases ? releases.map((release, index) => {
            const gradient = gradientCss(RELEASE_GRADIENTS[index % RELEASE_GRADIENTS.length])
            return (
              <div key={release.id} className={styles.release}>
                <div
                  className={release.posterUrl ? `${styles.date} ${styles.datePoster}` : styles.date}
                  style={{
                    // The gradient sits under the poster, so it shows if the poster fails to load.
                    background: release.posterUrl ? `center / cover no-repeat url("${release.posterUrl}"), ${gradient}` : gradient,
                  }}
                >
                  <span className={styles.month}>{release.month}</span>
                  <span className={styles.day}>{release.day}</span>
                </div>
                <div className={styles.releaseText}>
                  <p className={styles.releaseTitle}>
                    <Link to={`/movies/${release.id}`} className={styles.releaseLink}>{release.title}</Link>
                  </p>
                  <p className={styles.releaseMeta}>{release.meta}</p>
                </div>
                <button type="button" className={styles.calendar} aria-label={`Add ${release.title} to calendar`}>
                  <img src={calendarIcon} width={20} height={20} alt="" />
                </button>
              </div>
            )
          }) : Array.from({ length: 3 }, (_, index) => (
            <div key={index} className={styles.release} aria-hidden="true">
              <span className={`${styles.date} ${styles.releaseSkeleton}`} />
              <span className={styles.releaseText}>
                <span className={`${styles.releaseSkeleton} ${styles.releaseSkeletonTitle}`} />
                <span className={`${styles.releaseSkeleton} ${styles.releaseSkeletonMeta}`} />
              </span>
            </div>
          ))}
        </div>
      )}
    </section>
  )
}
