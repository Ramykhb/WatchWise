import { useEffect, useId, useMemo, useRef, useState, type FormEvent, type RefObject } from 'react'
import { Link, useLocation, useNavigate, useParams } from 'react-router'
import axios from 'axios'
import AppNav from '../../components/AppNav/AppNav.tsx'
import MovieTile from '../../components/MovieTile/MovieTile.tsx'
import PillButton from '../../components/PillButton/PillButton.tsx'
import RatingStars from '../../components/RatingStars/RatingStars.tsx'
import ReviewCard, { type Review } from '../../components/ReviewCard/ReviewCard.tsx'
import Sentiment from '../../components/Sentiment/Sentiment.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import Placeholder from '../Placeholder/Placeholder.tsx'
import heroSun from '../../assets/movie/hero-sun.svg'
import heroPosterWave from '../../assets/movie/hero-poster-wave.svg'
import heroWave from '../../assets/movie/hero-wave.svg'
import starLg from '../../assets/movie/icon-star-lg.svg'
import starOutlineLg from '../../assets/movie/icon-star-outline-lg.svg'
import playIcon from '../../assets/movie/icon-play.svg'
import playLgIcon from '../../assets/movie/icon-play-lg.svg'
import checkIcon from '../../assets/movie/icon-check.svg'
import plusIcon from '../../assets/landing/icon-plus-cream.svg'
import eyeIcon from '../../assets/movie/icon-eye.svg'
import listIcon from '../../assets/movie/icon-list.svg'
import calendarIcon from '../../assets/movie/icon-calendar.svg'
import shareIcon from '../../assets/movie/icon-share.svg'
import trailerSun from '../../assets/movie/trailer-sun.svg'
import trailerWave from '../../assets/movie/trailer-wave.svg'
import editIcon from '../../assets/movie/icon-edit.svg'
import trashIcon from '../../assets/movie/icon-trash.svg'
import calendarInkIcon from '../../assets/movie/icon-calendar-ink.svg'
import pinIcon from '../../assets/movie/icon-pin.svg'
import mapWater from '../../assets/movie/map-water.svg'
import mapRoads from '../../assets/movie/map-roads.svg'
import mapPin from '../../assets/movie/map-pin.svg'
import mapPinYou from '../../assets/movie/map-pin-you.svg'
import chatIcon from '../../assets/home/icon-chat.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import { gradientCss } from '../Home/homeData.ts'
import { loadGenres } from '../Discover/discoverApi.ts'
import { getMovieDetails, type Community, type MovieDetails as Details } from './movieData.ts'
import { fetchMovieDetails, toDetails, type TmdbMovieDetails as ApiMovie } from './movieApi.ts'
import AddToListDialog from './AddToListDialog.tsx'
import { tmdbIdOf } from '../../lib/movies.ts'
import { api } from '../../lib/api.ts'
import { useAuth } from '../../contexts/useAuth.ts'
import styles from './MovieDetails.module.css'

/** What the last request for a TMDB movie returned, tagged with the request it answers. */
type Loaded = { key: string; movie: ApiMovie | null; failed: boolean }

const MAX_REVIEW = 2000

const SORTS = {
  helpful: { label: 'Most helpful', compare: (a: Review, b: Review) => b.helpful - a.helpful },
  newest: { label: 'Newest', compare: (a: Review, b: Review) => Date.parse(b.date) - Date.parse(a.date) },
  highest: { label: 'Highest rated', compare: (a: Review, b: Review) => b.rating - a.rating },
  lowest: { label: 'Lowest rated', compare: (a: Review, b: Review) => a.rating - b.rating },
}
type SortKey = keyof typeof SORTS

// Cinema pins on the 388×170 Figma map (the violet one is "you").
const PINS = [
  { left: 90, top: 50 },
  { left: 250, top: 96 },
  { left: 320, top: 30 },
]

type Premiere = Community['premiere']

function googleCalendarUrl(title: string, premiere: Premiere) {
  const params = new URLSearchParams({
    action: 'TEMPLATE',
    text: `${title} — ${premiere.title}`,
    dates: `${premiere.start}/${premiere.end}`,
  })
  return `https://calendar.google.com/calendar/render?${params}`
}

function downloadIcs(slug: string, title: string, premiere: Premiere) {
  const ics = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//WatchWise//EN',
    'BEGIN:VEVENT',
    `UID:${slug}-premiere@watchwise`,
    `DTSTART:${premiere.start}`,
    `DTEND:${premiere.end}`,
    `SUMMARY:${title} — ${premiere.title}`,
    'END:VEVENT',
    'END:VCALENDAR',
  ].join('\r\n')
  const url = URL.createObjectURL(new Blob([ics], { type: 'text/calendar' }))
  const link = document.createElement('a')
  link.href = url
  link.download = `${slug}-premiere.ics`
  link.click()
  URL.revokeObjectURL(url)
}

export default function MovieDetails() {
  const { slug = '' } = useParams()
  // TMDB movies have numeric ids (/movies/438631) and TV shows "tv-" ids (/movies/tv-1399); the design's mock movies have names (/movies/neon-tide).
  return /^(tv-)?\d+$/.test(slug) ?<TmdbMovieDetails id={slug} /> : <MockMovieDetails slug={slug} />
}

function MockMovieDetails({ slug }: { slug: string }) {
  const details = getMovieDetails(slug)
  if (!details) return <Placeholder title="Movie not found" notFound />
  // Keyed by slug so local state (rating, watchlist, review draft) resets when moving between movies.
  return <MovieDetailsPage key={details.movie.slug} details={details} />
}

function TmdbMovieDetails({ id }: { id: string }) {
  const [loaded, setLoaded] = useState<Loaded | null>(null)
  const [retries, setRetries] = useState(0)
  // Genre names for the "More like this" tiles. Loaded on the side: the movie never waits for them.
  const [genres, setGenres] = useState<Map<number, string>>(() => new Map())
  const key = `${id}-${retries}`

  useEffect(() => {
    let active = true
    loadGenres()
      .then((map) => active && setGenres(map))
      .catch((error: unknown) => console.error('Unable to load genre names:', error))
    return () => {
      active = false
    }
  }, [])

  useEffect(() => {
    const controller = new AbortController()
    fetchMovieDetails(id, controller.signal)
      .then((movie) => setLoaded({ key, movie, failed: false }))
      .catch((error: unknown) => {
        if (axios.isCancel(error)) return
        console.error('Unable to load the movie:', error)
        setLoaded({ key, movie: null, failed: true })
      })
    return () => controller.abort()
  }, [id, key])

  const apiMovie = loaded?.key === key ? loaded.movie : null
  const details = useMemo(() => (apiMovie ? toDetails(apiMovie, genres) : null), [apiMovie, genres])

  if (loaded?.key !== key) return <MovieDetailsSkeleton />

  if (loaded.failed) {
    return (
      <div className={styles.page}>
        <AppNav />
        <main>
          <section className={styles.hero}>
            <div className={styles.loadError} role="alert">
              <h1 className={styles.loadErrorTitle}>We couldn't load this movie</h1>
              <p className={styles.loadErrorText}>
                The movie service didn't answer. Check your connection, then try again.
              </p>
              <div className={styles.actions}>
                <button type="button" className={styles.actionTrailer} onClick={() => setRetries((n) => n + 1)}>
                  Try again
                </button>
                <Link to="/discover" className={styles.actionOutline}>
                  Back to Discover
                </Link>
              </div>
            </div>
          </section>
          <img className={styles.heroWave} src={heroWave} width={1440} height={70} alt="" />
        </main>
      </div>
    )
  }

  if (!details) return <Placeholder title="Movie not found" notFound />
  return <MovieDetailsPage key={id} details={details} />
}

/** Grey shapes in the page's own layout while a TMDB movie loads, so nothing jumps when it arrives. */
function MovieDetailsSkeleton() {
  return (
    <div className={styles.page}>
      <AppNav />
      <main aria-busy="true">
        <p className="sr-only" role="status">
          Loading movie…
        </p>
        <section className={styles.hero} aria-hidden="true">
          <div className={styles.heroInner}>
            <div className={`${styles.poster} ${styles.skeletonOnViolet}`} />
            <div className={styles.info}>
              <div className={styles.tags}>
                {[64, 92, 56].map((width) => (
                  <span key={width} className={`${styles.skeletonPill} ${styles.skeletonOnViolet}`} style={{ width }} />
                ))}
              </div>
              <span className={`${styles.skeletonTitle} ${styles.skeletonOnViolet}`} />
              <span className={`${styles.skeletonLine} ${styles.skeletonOnViolet}`} style={{ width: '55%' }} />
              <div className={styles.ratings}>
                {[150, 90, 150].map((width, index) => (
                  <span key={index} className={`${styles.skeletonBlock} ${styles.skeletonOnViolet}`} style={{ width }} />
                ))}
              </div>
              <div className={styles.skeletonParagraph}>
                {['100%', '96%', '88%', '62%'].map((width) => (
                  <span key={width} className={`${styles.skeletonLine} ${styles.skeletonOnViolet}`} style={{ width }} />
                ))}
              </div>
              <div className={styles.actions}>
                {[150, 140, 170, 130].map((width) => (
                  <span key={width} className={`${styles.skeletonButton} ${styles.skeletonOnViolet}`} style={{ width }} />
                ))}
              </div>
            </div>
          </div>
        </section>
        <img className={styles.heroWave} src={heroWave} width={1440} height={70} alt="" />

        <div className={styles.body} aria-hidden="true">
          <div className={styles.main}>
            <div className={styles.section}>
              <span className={`${styles.skeletonHeading} ${styles.skeletonOnCream}`} />
              <ul className={styles.people}>
                {[1, 2, 3, 4, 5, 6].map((n) => (
                  <li key={n} className={styles.skeletonPerson}>
                    <span className={`${styles.skeletonAvatar} ${styles.skeletonOnCream}`} />
                    <span className={`${styles.skeletonLine} ${styles.skeletonOnCream}`} style={{ width: '80%' }} />
                    <span className={`${styles.skeletonLine} ${styles.skeletonOnCream}`} style={{ width: '60%' }} />
                  </li>
                ))}
              </ul>
            </div>
            <div className={styles.section}>
              <span className={`${styles.skeletonHeading} ${styles.skeletonOnCream}`} />
              <div className={`${styles.video} ${styles.skeletonOnCream}`} />
            </div>
          </div>
          <div className={styles.sidebar}>
            <div className={styles.details}>
              <span className={`${styles.skeletonHeading} ${styles.skeletonOnCream}`} style={{ width: 90 }} />
              <div className={styles.skeletonFacts}>
                {[1, 2, 3, 4, 5, 6].map((n) => (
                  <span key={n} className={`${styles.skeletonLine} ${styles.skeletonOnCream}`} />
                ))}
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  )
}

function MovieDetailsPage({ details }: { details: Details }) {
  const pageRef = useRef<HTMLDivElement>(null)
  const trailerRef = useRef<HTMLElement>(null)
  const calendarRef = useRef<HTMLElement>(null)
  useEntranceAnimation(pageRef)

  // WatchWise's own data (reviews, premiere, showtimes, room). Only the design's mock movies have it;
  // TMDB movies don't, so those sections are hidden rather than filled with sample data.
  const { movie, community } = details
  // Lists and ratings hold TMDB movie ids; TV shows ("tv-1399") use a separate id range, so they can't be added yet.
  const listTmdbId = tmdbIdOf(movie)
  const canAddToList = Number.isInteger(listTmdbId) && listTmdbId > 0
  const canRate = canAddToList

  // Your star rating is saved per film (GET/PUT /api/ratings/{tmdbId}) and loaded when the page opens.
  // The ratings API needs a signed-in member: a guest's 401 would be taken for an expired session and
  // sign them out of the page, so nothing is requested until there is a user.
  const { user } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const signedIn = user !== null
  const [userRating, setUserRating] = useState(0)
  const [ratingLoaded, setRatingLoaded] = useState(false)
  const ratingReady = !canRate || !signedIn || ratingLoaded
  const [ratingNote, setRatingNote] = useState('')
  const [hoverRating, setHoverRating] = useState(0)
  const ratingHintId = useId()

  useEffect(() => {
    if (!canRate || !signedIn) return
    let active = true
    api
      .get<{ rating: number | null }>(`/ratings/${listTmdbId}`)
      .then((response) => active && setUserRating(response.data.rating ?? 0))
      .catch(() => undefined)
      .finally(() => active && setRatingLoaded(true))
    return () => {
      active = false
    }
  }, [canRate, signedIn, listTmdbId])

  // Shows at once and saves in the background; a failed save puts the previous stars back.
  // A guest is sent to Sign in first and comes back to this movie afterwards.
  const rate = (stars: number) => {
    if (!canRate) return
    if (!signedIn) {
      navigate('/signin', { state: { from: location } })
      return
    }
    if (!ratingReady || stars === userRating) return
    const previous = userRating
    setUserRating(stars)
    setRatingNote('')
    api
      .put(`/ratings/${listTmdbId}`, { rating: stars })
      .then(() => setRatingNote(`Saved · ${stars} star${stars > 1 ? 's' : ''}`))
      .catch(() => {
        setUserRating(previous)
        setRatingNote("Couldn't save your rating. Please try again.")
      })
  }

  // The mock movies start from the design's state (in the watchlist).
  // TODO: load and save the watchlist and "watched" through the API.
  const [onWatchlist, setOnWatchlist] = useState(Boolean(community))
  const [watched, setWatched] = useState(false)
  const [shareNote, setShareNote] = useState('')
  const [playing, setPlaying] = useState(false)
  // "Add to list" pop-up: 'open', 'closing' (exit animation) or closed.
  const [listDialog, setListDialog] = useState<'open' | 'closing' | null>(null)
  const listHintId = useId()

  const [yourReview, setYourReview] = useState<Review | null>(community?.yourReview ?? null)
  const [draft, setDraft] = useState('')
  const [draftRating, setDraftRating] = useState(4)
  const [spoilers, setSpoilers] = useState(false)
  const [sort, setSort] = useState<SortKey>('helpful')
  const [selectedTime, setSelectedTime] = useState<string | null>(null)

  const reviews = [...(community?.reviews ?? [])].sort(SORTS[sort].compare)
  const shownRating = hoverRating || userRating

  const scrollTo = (ref: RefObject<HTMLElement | null>) =>
    ref.current?.scrollIntoView({ behavior: 'smooth', block: 'start' })

  const handleShare = async () => {
    const url = window.location.href
    try {
      if (navigator.share) {
        await navigator.share({ title: movie.title, url })
        return
      }
      await navigator.clipboard.writeText(url)
      setShareNote('Link copied')
    } catch {
      setShareNote('')
    }
  }

  const handlePostReview = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const body = draft.trim()
    if (!body || !community) return
    // TODO: POST the review; sentiment comes back from the backend's sentiment analysis.
    setYourReview({
      ...community.yourReview,
      date: new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
      rating: draftRating,
      body: spoilers ? `[Spoiler] ${body}` : body,
      helpful: 0,
    })
    setDraft('')
    setSpoilers(false)
  }

  const handleEdit = () => {
    if (!yourReview) return
    setDraft(yourReview.body)
    setDraftRating(yourReview.rating)
    setYourReview(null)
    document.getElementById('review-text')?.focus()
  }

  return (
    <div ref={pageRef} className={styles.page}>
      <AppNav />

      <main>
        <section className={styles.hero} aria-labelledby="movie-title">
          <div className={styles.heroInner}>
            <div
              className={styles.poster}
              style={{ background: gradientCss(movie.gradient) }}
              data-enter
              data-enter-delay="0.1"
              data-enter-rise="80"
            >
              {movie.posterUrl ? (
                <img className={styles.posterImage} src={movie.posterUrl} alt={`${movie.title} poster`} />
              ) : (
                <>
                  <img className={styles.posterSun} src={heroSun} width={170} height={170} alt="" />
                  <img className={styles.posterWave} src={heroPosterWave} width={320} height={220} alt="" />
                  <p className={styles.posterTitle} aria-hidden="true">
                    <span>{movie.posterTitle[0]}</span>
                    <span>{movie.posterTitle[1]}</span>
                  </p>
                </>
              )}
            </div>

            <div className={styles.info} data-enter data-enter-delay="0.25">
              <ul className={styles.tags}>
                {details.tags.map((tag) => (
                  <li key={tag.label} className={tag.highlight ? styles.tagHighlight : styles.tag}>
                    {tag.label}
                  </li>
                ))}
              </ul>
              <SplitHeadline id="movie-title" className={styles.title} lines={[{ text: movie.title }]} />
              <p className={styles.meta}>
                {details.year} · {details.runtime} · {details.language} · Directed by{' '}
                <strong>{details.director}</strong>
              </p>

              <div className={styles.ratings}>
                {community && (
                  <>
                    <div className={styles.average}>
                      <span className={styles.averageValue}>{movie.score}</span>
                      <div className={styles.averageText}>
                        <RatingStars value={5} label="WatchWise average" />
                        <span>{community.ratingCount}</span>
                      </div>
                    </div>
                    <span className={styles.divider} aria-hidden="true" />
                  </>
                )}
                <div className={styles.tmdb}>
                  <span className={styles.ratingLabel}>TMDB</span>
                  <span className={styles.tmdbValue}>{details.tmdbScore}</span>
                </div>
                <span className={styles.divider} aria-hidden="true" />
                <div className={styles.yourRating}>
                  <span className={styles.ratingLabel} id="your-rating-label">
                    YOUR RATING
                  </span>
                  {/* aria-disabled (not disabled) keeps the stars focusable on TV shows, so the reason is announced. */}
                  <div
                    className={styles.bigStars}
                    role="radiogroup"
                    aria-labelledby="your-rating-label"
                    aria-describedby={canRate ? undefined : ratingHintId}
                    aria-disabled={!canRate || undefined}
                    aria-busy={!ratingReady || undefined}
                    onMouseLeave={() => setHoverRating(0)}
                  >
                    {[1, 2, 3, 4, 5].map((n) => (
                      <button
                        key={n}
                        type="button"
                        role="radio"
                        aria-checked={userRating === n}
                        aria-label={`${n} star${n > 1 ? 's' : ''}`}
                        aria-disabled={!canRate || undefined}
                        className={styles.bigStar}
                        onMouseEnter={() => canRate && setHoverRating(n)}
                        onClick={() => rate(n)}
                      >
                        <img src={n <= shownRating ? starLg : starOutlineLg} width={22} height={22} alt="" />
                      </button>
                    ))}
                  </div>
                  {!canRate ? (
                    <span id={ratingHintId} className={styles.ratingNote}>
                      TV shows can’t be rated yet
                    </span>
                  ) : (
                    <span className={styles.ratingNote} role="status">
                      {ratingNote}
                    </span>
                  )}
                </div>
              </div>

              <p className={styles.overview}>{details.overview}</p>

              <div className={styles.actions}>
                <button
                  type="button"
                  className={styles.actionTrailer}
                  onClick={() => {
                    setPlaying(true)
                    scrollTo(trailerRef)
                  }}
                >
                  <img src={playIcon} width={16} height={16} alt="" />
                  Watch trailer
                </button>
                <button
                  type="button"
                  className={onWatchlist ? styles.actionOn : styles.actionOutline}
                  aria-pressed={onWatchlist}
                  onClick={() => setOnWatchlist((value) => !value)}
                >
                  <img src={onWatchlist ? checkIcon : plusIcon} width={16} height={16} alt="" />
                  {onWatchlist ? 'In watchlist' : 'Add to watchlist'}
                </button>
                <button
                  type="button"
                  className={watched ? styles.actionWatched : styles.actionOutline}
                  aria-pressed={watched}
                  onClick={() => setWatched((value) => !value)}
                >
                  <img src={eyeIcon} width={16} height={16} alt="" />
                  {watched ? 'Watched' : 'Mark as watched'}
                </button>
                {/* aria-disabled (not disabled) keeps it focusable, so keyboard and screen-reader users reach the reason. */}
                <button
                  type="button"
                  className={styles.actionOutline}
                  aria-haspopup={canAddToList ? 'dialog' : undefined}
                  aria-disabled={!canAddToList || undefined}
                  aria-describedby={canAddToList ? undefined : listHintId}
                  onClick={() => canAddToList && setListDialog('open')}
                >
                  <img src={listIcon} width={16} height={16} alt="" />
                  Add to list
                </button>
                {!canAddToList && (
                  <span id={listHintId} className={styles.actionHint}>
                    TV shows can’t be added to lists yet
                  </span>
                )}
                {community && (
                  <button
                    type="button"
                    className={styles.iconAction}
                    aria-label="Add the premiere to your calendar"
                    onClick={() => scrollTo(calendarRef)}
                  >
                    <img src={calendarIcon} width={22} height={22} alt="" />
                  </button>
                )}
                <button type="button" className={styles.iconAction} aria-label="Share" onClick={handleShare}>
                  <img src={shareIcon} width={22} height={22} alt="" />
                </button>
                <span className={styles.shareNote} role="status">
                  {shareNote}
                </span>
              </div>
            </div>
          </div>
        </section>
        <img className={styles.heroWave} src={heroWave} width={1440} height={70} alt="" />

        <div className={styles.body}>
          <div className={styles.main}>
            <section className={styles.section} aria-labelledby="cast-title">
              <div className={styles.sectionHeader}>
                <h2 id="cast-title" className={styles.sectionTitle}>
                  Cast &amp; crew
                </h2>
                <Link to={`/movies/${movie.slug}/credits`} className={styles.sectionAction}>
                  Full credits →
                </Link>
              </div>
              <ul className={styles.people}>
                {details.cast.map((person) => {
                  const content = (
                    <>
                      <span className={styles.photo} style={{ background: gradientCss(person.gradient) }}>
                        {person.photoUrl ? (
                          <img className={styles.photoImage} src={person.photoUrl} alt="" loading="lazy" />
                        ) : (
                          person.initials
                        )}
                      </span>
                      <span className={styles.personName} title={person.name}>
                        {person.name}
                      </span>
                      <span className={styles.personRole}>{person.role}</span>
                    </>
                  )
                  return (
                    <li key={`${person.name}-${person.role}`}>
                      {person.slug ? (
                        <Link to={`/people/${person.slug}`} className={styles.person}>
                          {content}
                        </Link>
                      ) : (
                        <div className={styles.person}>{content}</div>
                      )}
                    </li>
                  )
                })}
              </ul>
            </section>

            <section ref={trailerRef} className={styles.section} aria-labelledby="trailer-title">
              <div className={styles.sectionHeader}>
                <h2 id="trailer-title" className={styles.sectionTitle}>
                  Trailer
                </h2>
                <Link to={`/movies/${movie.slug}/videos`} className={styles.sectionAction}>
                  More videos →
                </Link>
              </div>
              <div className={styles.video} style={{ background: gradientCss(details.trailer.gradient) }}>
                {playing && details.trailer.youtubeKey ? (
                  <iframe
                    className={styles.videoFrame}
                    src={`https://www.youtube-nocookie.com/embed/${details.trailer.youtubeKey}?autoplay=1&rel=0`}
                    title={`${movie.title} — ${details.trailer.label}`}
                    allow="autoplay; encrypted-media; picture-in-picture; fullscreen"
                    allowFullScreen
                  />
                ) : (
                  <>
                    {details.trailer.backdropUrl ? (
                      <img className={styles.videoBackdrop} src={details.trailer.backdropUrl} alt="" loading="lazy" />
                    ) : (
                      <>
                        <img className={styles.videoSun} src={trailerSun} width={260} height={260} alt="" />
                        <img className={styles.videoWave} src={trailerWave} width={800} height={220} alt="" />
                      </>
                    )}
                    <button
                      type="button"
                      className={styles.play}
                      aria-label={`Play ${details.trailer.label}`}
                      aria-pressed={playing}
                      onClick={() => setPlaying((value) => !value)}
                    >
                      <img src={playLgIcon} width={43} height={43} alt="" />
                    </button>
                    <span className={styles.videoChip}>
                      {playing ? 'Trailer coming soon' : details.trailer.label}
                    </span>
                  </>
                )}
              </div>
            </section>

            {community && (
              <section className={styles.reviews} aria-labelledby="reviews-title">
                <div className={styles.sectionHeader}>
                  <h2 id="reviews-title" className={styles.sectionTitle}>
                    Reviews
                  </h2>
                  <label className={styles.sort}>
                    <span className="sr-only">Sort reviews</span>
                    <select value={sort} onChange={(event) => setSort(event.target.value as SortKey)}>
                      {Object.entries(SORTS).map(([key, option]) => (
                        <option key={key} value={key}>
                          Sort: {option.label}
                        </option>
                      ))}
                    </select>
                    <span aria-hidden="true">▾</span>
                  </label>
                </div>
  
                <div className={styles.summary}>
                  <div className={styles.summaryAverage}>
                    <span className={styles.summaryValue}>{community.reviewSummary.average}</span>
                    <RatingStars value={5} label="Average review" />
                    <span className={styles.muted}>{community.reviewSummary.count}</span>
                  </div>
                  <ul className={styles.distribution} aria-label="Rating distribution">
                    {community.reviewSummary.distribution.map((percent, index) => (
                      <li key={index} className={styles.distRow}>
                        <span className={styles.distStar}>{5 - index}</span>
                        <span className={styles.track}>
                          <span className={styles.fill} style={{ width: `${percent}%` }} />
                        </span>
                        <span className={styles.muted}>{percent}%</span>
                      </li>
                    ))}
                  </ul>
                  <div className={styles.sentiment}>
                    <p className={styles.sentimentTitle}>Review sentiment</p>
                    <p className={styles.muted}>Auto-classified with sentiment analysis</p>
                    <div className={styles.stacked} aria-hidden="true">
                      <span className={styles.stackPositive} style={{ flexGrow: community.reviewSummary.sentiment.positive }} />
                      <span className={styles.stackNeutral} style={{ flexGrow: community.reviewSummary.sentiment.neutral }} />
                      <span className={styles.stackNegative} style={{ flexGrow: community.reviewSummary.sentiment.negative }} />
                    </div>
                    <ul className={styles.legend}>
                      {(['positive', 'neutral', 'negative'] as const).map((type) => (
                        <li key={type}>
                          <Sentiment type={type} />
                          <strong>{community.reviewSummary.sentiment[type]}%</strong>
                        </li>
                      ))}
                    </ul>
                  </div>
                </div>
  
                <form className={styles.write} onSubmit={handlePostReview} aria-labelledby="write-title">
                  <div className={styles.writeHead}>
                    <div className={styles.writeWho}>
                      <span className={styles.avatar} style={{ background: 'var(--accent-mint)' }} aria-hidden="true">
                        MR
                      </span>
                      <h3 id="write-title" className={styles.writeTitle}>
                        Write a review
                      </h3>
                    </div>
                    <div className={styles.pick}>
                      <span className={styles.muted}>Your rating</span>
                      <RatingStars value={draftRating} onChange={setDraftRating} label="Your rating for this review" />
                    </div>
                  </div>
                  <label className="sr-only" htmlFor="review-text">
                    Your review
                  </label>
                  <textarea
                    id="review-text"
                    className={styles.textarea}
                    value={draft}
                    maxLength={MAX_REVIEW}
                    onChange={(event) => setDraft(event.target.value)}
                    placeholder="What did you think? Keep it spoiler-free, or tag it below…"
                  />
                  <div className={styles.writeFoot}>
                    <label className={styles.switch}>
                      <input
                        type="checkbox"
                        role="switch"
                        checked={spoilers}
                        onChange={(event) => setSpoilers(event.target.checked)}
                      />
                      <span className={styles.switchTrack} aria-hidden="true" />
                      Contains spoilers
                    </label>
                    <div className={styles.writeRight}>
                      <span className={styles.muted}>
                        {draft.length} / {MAX_REVIEW}
                      </span>
                      <PillButton type="submit" disabled={!draft.trim()}>
                        Post review
                      </PillButton>
                    </div>
                  </div>
                </form>
  
                {yourReview && (
                  <div className={styles.yourReview}>
                    <div className={styles.yourHead}>
                      <span className={styles.yourChip}>Your review</span>
                      <div className={styles.manage}>
                        <button type="button" className={styles.edit} onClick={handleEdit}>
                          <img src={editIcon} width={14} height={14} alt="" />
                          Edit
                        </button>
                        <button type="button" className={styles.delete} onClick={() => setYourReview(null)}>
                          <img src={trashIcon} width={14} height={14} alt="" />
                          Delete
                        </button>
                      </div>
                    </div>
                    <ReviewCard review={yourReview} />
                  </div>
                )}
  
                {reviews.map((review) => (
                  <ReviewCard key={review.id} review={review} />
                ))}
  
                {/* TODO: page through reviews from the API. */}
                <PillButton variant="outline" className={styles.loadMore}>
                  Load more reviews
                </PillButton>
              </section>
            )}
          </div>

          <aside className={styles.sidebar} aria-label="Movie details">
            <section className={styles.details} aria-labelledby="details-title">
              <h2 id="details-title" className={styles.cardTitle}>
                Details
              </h2>
              <dl className={styles.facts}>
                {details.facts.map((fact) => (
                  <div key={fact.label} className={styles.fact}>
                    <dt>{fact.label}</dt>
                    <dd>{fact.value}</dd>
                  </div>
                ))}
              </dl>
              <span className={styles.source}>Data from TMDB</span>
            </section>

            {community && (
              <>
                <section ref={calendarRef} className={styles.calendar} aria-labelledby="premiere-title">
                  <div className={styles.calendarHead}>
                    <span className={styles.calendarIcon} aria-hidden="true">
                      <img src={calendarInkIcon} width={20} height={20} alt="" />
                    </span>
                    <div>
                      <h2 id="premiere-title" className={styles.calendarTitle}>
                        {community.premiere.title}
                      </h2>
                      <p className={styles.calendarWhen}>{community.premiere.when}</p>
                    </div>
                  </div>
                  <div className={styles.calendarButtons}>
                    <PillButton href={googleCalendarUrl(movie.title, community.premiere)}>Google Calendar</PillButton>
                    <PillButton variant="outline" onClick={() => downloadIcs(movie.slug, movie.title, community.premiere)}>
                      Download .ics
                    </PillButton>
                  </div>
                </section>
    
                <section className={styles.showtimes} aria-labelledby="showtimes-title">
                  <div className={styles.showtimesHead}>
                    <h2 id="showtimes-title" className={styles.cardTitleSm}>
                      Showtimes near you
                    </h2>
                    <span className={styles.city}>
                      <img src={pinIcon} width={14} height={14} alt="" />
                      {community.city}
                    </span>
                  </div>
                  <div className={styles.map} aria-hidden="true">
                    <img className={styles.mapLayer} src={mapWater} width={388} height={170} alt="" />
                    <img className={styles.mapLayer} src={mapRoads} width={388} height={170} alt="" />
                    {PINS.map((pin) => (
                      <img
                        key={`${pin.left}-${pin.top}`}
                        className={styles.mapPin}
                        style={{ left: pin.left, top: pin.top }}
                        src={mapPin}
                        width={24}
                        height={24}
                        alt=""
                      />
                    ))}
                    <img className={styles.mapPin} style={{ left: 170, top: 120 }} src={mapPinYou} width={24} height={24} alt="" />
                  </div>
                  {community.cinemas.map((cinema) => (
                    <div key={cinema.name} className={styles.cinema}>
                      <div className={styles.cinemaHead}>
                        <span className={styles.cinemaName}>{cinema.name}</span>
                        <span className={styles.muted}>{cinema.distance}</span>
                      </div>
                      <div className={styles.times}>
                        {cinema.times.map((time) => {
                          const id = `${cinema.name} ${time}`
                          return (
                            <button
                              key={time}
                              type="button"
                              className={selectedTime === id ? styles.timeOn : styles.time}
                              aria-pressed={selectedTime === id}
                              aria-label={`${time} at ${cinema.name}`}
                              onClick={() => setSelectedTime(selectedTime === id ? null : id)}
                            >
                              {time}
                            </button>
                          )
                        })}
                      </div>
                    </div>
                  ))}
                  <PillButton to="/cinemas" variant="soft">
                    Open cinema map
                  </PillButton>
                </section>
    
                <section className={styles.room} aria-labelledby="room-title">
                  <div className={styles.roomHead}>
                    <img src={chatIcon} width={20} height={20} alt="" />
                    <span>LIVE ROOM</span>
                  </div>
                  <h2 id="room-title" className={styles.roomTitle}>
                    {movie.title} — {community.room.title}
                  </h2>
                  <p className={styles.roomText}>{community.room.text}</p>
                  <div className={styles.roomFoot}>
                    <div className={styles.guests} aria-label={`${community.room.guests.length + community.room.extra} fans online`}>
                      {community.room.guests.map((guest) => (
                        <span key={guest.initials} className={styles.guest} style={{ background: guest.color }} aria-hidden="true">
                          {guest.initials}
                        </span>
                      ))}
                      <span className={styles.guest} style={{ background: 'var(--surface-violet-tint)' }} aria-hidden="true">
                        +{community.room.extra}
                      </span>
                    </div>
                    <PillButton to="/rooms" variant="accent">
                      Join room
                    </PillButton>
                  </div>
                </section>
              </>
            )}
          </aside>
        </div>

        <section className={styles.similar} aria-labelledby="similar-title">
          <div className={styles.sectionHeader}>
            <h2 id="similar-title" className={styles.sectionTitle}>
              More like this
            </h2>
            <Link to="/discover" className={styles.sectionAction}>
              See all →
            </Link>
          </div>
          <div className={styles.row}>
            {details.similar.map((similar) => (
              <MovieTile key={similar.slug} movie={similar} />
            ))}
          </div>
        </section>
      </main>

      {listDialog && (
        <AddToListDialog
          open={listDialog === 'open'}
          tmdbId={listTmdbId}
          title={movie.title}
          onClose={() => setListDialog('closing')}
          onClosed={() => setListDialog(null)}
        />
      )}
    </div>
  )
}
