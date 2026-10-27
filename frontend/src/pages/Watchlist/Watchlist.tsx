import { useRef, useState } from 'react'
import { Link } from 'react-router'
import AppNav from '../../components/AppNav/AppNav.tsx'
import ButtonPrimary from '../../components/ButtonPrimary/ButtonPrimary.tsx'
import PillButton from '../../components/PillButton/PillButton.tsx'
import RatingStars from '../../components/RatingStars/RatingStars.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import clockIcon from '../../assets/watchlist/icon-clock.svg'
import checkSmIcon from '../../assets/watchlist/icon-check-sm.svg'
import filmIcon from '../../assets/watchlist/icon-film.svg'
import chevDownIcon from '../../assets/watchlist/icon-chev-down.svg'
import eyeIcon from '../../assets/watchlist/icon-eye.svg'
import trashIcon from '../../assets/watchlist/icon-trash.svg'
import checkIcon from '../../assets/person/icon-check.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import { gradientCss } from '../Home/homeData.ts'
import { api, useApi } from '../../lib/api.ts'
import { posterAt, useMovies } from '../../lib/movieDetails.ts'
import type { Movie } from '../../components/MovieTile/MovieTile.tsx'
import type { ApiWatchItem } from '../../lib/types.ts'
import { tasteOf, toHistory, toQueueItem, type HistoryEntry, type QueueItem } from './watchlistData.ts'
import styles from './Watchlist.module.css'

type WatchlistResponse = { items: ApiWatchItem[]; ratings: Record<string, number> }

type Tab = 'watchlist' | 'watched' | 'history'

const SORTS = {
  recent: { label: 'Recently added', compare: (a: QueueItem, b: QueueItem) => b.addedOrder - a.addedOrder },
  title: { label: 'Title A–Z', compare: (a: QueueItem, b: QueueItem) => a.movie.title.localeCompare(b.movie.title) },
  rating: { label: 'Highest rated', compare: (a: QueueItem, b: QueueItem) => Number(b.movie.score) - Number(a.movie.score) },
  runtime: { label: 'Shortest first', compare: (a: QueueItem, b: QueueItem) => a.runtimeMinutes - b.runtimeMinutes },
}
type SortKey = keyof typeof SORTS

/** A small poster over the film's gradient, which shows when there is no image (or it fails). */
const thumbBackground = (movie: Movie) => {
  const image = posterAt(movie.posterUrl, 'w185')
  return image ? `center / cover no-repeat url("${image}"), ${gradientCss(movie.gradient)}` : gradientCss(movie.gradient)
}

/** History entries grouped under their month heading, in order. */
function byMonth(entries: HistoryEntry[]) {
  const months: { month: string; entries: HistoryEntry[] }[] = []
  for (const entry of entries) {
    const last = months.at(-1)
    if (last?.month === entry.month) last.entries.push(entry)
    else months.push({ month: entry.month, entries: [entry] })
  }
  return months
}

function HistoryList({ entries }: { entries: HistoryEntry[] }) {
  return byMonth(entries).map(({ month, entries: monthEntries }) => (
    <div key={month}>
      <p className={styles.month}>{month}</p>
      <ul className={styles.entries}>
        {monthEntries.map((entry) => (
          <li key={entry.id}>
            <Link to={`/movies/${entry.movie.slug}`} className={styles.entry}>
              <span className={styles.date}>{entry.date}</span>
              <span className={styles.entryThumb} style={{ background: thumbBackground(entry.movie) }} />
              <span className={styles.entryText}>
                <span className={styles.entryTitle}>{entry.movie.title}</span>
                <RatingStars value={entry.rating} label={`Your rating for ${entry.movie.title}`} />
              </span>
            </Link>
          </li>
        ))}
      </ul>
    </div>
  ))
}

export default function Watchlist() {
  const pageRef = useRef<HTMLDivElement>(null)
  const tabsRef = useRef<HTMLDivElement>(null)
  useEntranceAnimation(pageRef)

  // Rows of the watchlists table; marking as watched and removing are saved through the API.
  const { data, setData, reload, loading } = useApi<WatchlistResponse>('/watchlist')
  const items = data?.items ?? []
  const [tab, setTab] = useState<Tab>('watchlist')
  const [sort, setSort] = useState<SortKey>('recent')
  const [shareNote, setShareNote] = useState('')

  // Real titles, years, genres and posters from TMDB (the rows only store the film's id).
  const movieOf = useMovies(items.map((item) => item.tmdb_id))
  const history = toHistory(items, data?.ratings ?? {}, movieOf)
  const queue = items.filter((item) => item.status !== 'watched').map((item) => toQueueItem(item, movieOf))
  const toWatch = queue.length
  const watchedTotal = history.length
  const queuedHours = Math.round(queue.reduce((sum, item) => sum + item.runtimeMinutes, 0) / 60)
  const taste = tasteOf(history.map((entry) => entry.movie))
  const maxTaste = Math.max(1, ...taste.map((t) => t.percent))

  const shown = [...queue].sort(SORTS[sort].compare)

  const toggleWatched = (id: number) => {
    const item = items.find((i) => i.id === id)
    if (!item) return
    const status = item.status === 'watched' ? 'to_watch' : 'watched'
    setData((current) => ({
      ...current,
      items: current.items.map((i) => (i.id === id ? { ...i, status, watched_at: status === 'watched' ? new Date().toISOString() : null } : i)),
    }))
    api.patch(`/watchlist/${id}`, { status }).catch(reload)
  }
  const remove = (id: number) => {
    setData((current) => ({ ...current, items: current.items.filter((i) => i.id !== id) }))
    api.delete(`/watchlist/${id}`).catch(reload)
  }

  const handleShare = async () => {
    const url = window.location.href
    try {
      if (navigator.share) {
        await navigator.share({ title: 'My WatchWise watchlist', url })
        return
      }
      await navigator.clipboard.writeText(url)
      setShareNote('Link copied')
    } catch {
      setShareNote('')
    }
  }

  const showHistory = () => {
    setTab('history')
    tabsRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }

  const tabs: { id: Tab; label: string; count?: number }[] = [
    { id: 'watchlist', label: 'Watchlist', count: toWatch },
    { id: 'watched', label: 'Watched', count: watchedTotal },
    { id: 'history', label: 'History' },
  ]

  return (
    <div ref={pageRef} className={styles.page}>
      <AppNav />

      <main className={styles.main}>
        <header className={styles.header} data-enter data-enter-delay="0.1">
          <div className={styles.titleBlock}>
            <SplitHeadline id="watchlist-title" className={styles.title} lines={[{ text: 'My watchlist' }]} />
            <ul className={styles.stats}>
              <li className={styles.statWhite}>
                <img src={clockIcon} width={14} height={14} alt="" />
                {toWatch} to watch
              </li>
              <li className={styles.statMint}>
                <img src={checkSmIcon} width={14} height={14} alt="" />
                {watchedTotal} watched
              </li>
              <li className={styles.statYellow}>
                <img src={filmIcon} width={14} height={14} alt="" />
                {queuedHours}h queued
              </li>
            </ul>
          </div>
          <div className={styles.actions}>
            <span className={styles.shareNote} role="status">
              {shareNote}
            </span>
            <PillButton variant="outline" onClick={handleShare}>
              Share watchlist
            </PillButton>
            <ButtonPrimary label="Find something to watch" to="/for-you" />
          </div>
        </header>

        <div ref={tabsRef} className={styles.tabs} role="tablist" aria-label="Watchlist sections">
          {tabs.map((t) => (
            <button
              key={t.id}
              type="button"
              role="tab"
              aria-selected={tab === t.id}
              className={styles.tab}
              onClick={() => setTab(t.id)}
            >
              <span>
                {t.label}
                {t.count !== undefined && <span className={styles.tabCount}>{t.count}</span>}
              </span>
            </button>
          ))}
        </div>

        <div className={styles.body}>
          <section className={styles.queue} aria-label={tabs.find((t) => t.id === tab)!.label} data-enter data-enter-delay="0.25">
            {tab === 'watchlist' && (
              <>
                {/* TODO: availability filters (in cinemas / streaming / coming soon) once TMDB data is connected. */}
                <div className={styles.toolbar}>
                  <span />
                  <label className={styles.sort}>
                    <span className="sr-only">Sort watchlist</span>
                    <select value={sort} onChange={(event) => setSort(event.target.value as SortKey)}>
                      {Object.entries(SORTS).map(([key, option]) => (
                        <option key={key} value={key}>
                          {option.label}
                        </option>
                      ))}
                    </select>
                    <img src={chevDownIcon} width={14} height={14} alt="" />
                  </label>
                </div>

                {shown.length === 0 && !loading && (
                  <p className={styles.empty}>Your watchlist is empty — find something on Discover or For You.</p>
                )}

                <ul className={styles.items}>
                  {shown.map((item) => (
                    <li key={item.id} className={styles.item}>
                      <Link
                        to={`/movies/${item.movie.slug}`}
                        className={styles.thumb}
                        style={{ background: thumbBackground(item.movie) }}
                        aria-label={item.movie.title}
                      />
                      <div className={styles.info}>
                        <Link to={`/movies/${item.movie.slug}`} className={styles.itemTitle}>
                          {item.movie.title}
                        </Link>
                        <p className={styles.details}>{item.details}</p>
                        <div className={styles.meta}>
                          <span className={styles.added}>{item.added}</span>
                        </div>
                      </div>
                      <button
                        type="button"
                        className={item.watched ? styles.watchedOn : styles.watched}
                        aria-pressed={item.watched}
                        onClick={() => toggleWatched(item.id)}
                      >
                        <img src={item.watched ? checkIcon : eyeIcon} width={16} height={16} alt="" />
                        {item.watched ? 'Watched' : 'Mark as watched'}
                      </button>
                      <button
                        type="button"
                        className={styles.remove}
                        aria-label={`Remove ${item.movie.title} from watchlist`}
                        onClick={() => remove(item.id)}
                      >
                        <img src={trashIcon} width={18} height={18} alt="" />
                      </button>
                    </li>
                  ))}
                </ul>
              </>
            )}

            {tab === 'watched' && history.length === 0 && !loading && <p className={styles.empty}>No watched films yet.</p>}
            {tab === 'watched' && (
              <ul className={styles.items}>
                {history.map((entry) => (
                  <li key={entry.id} className={styles.item}>
                    <Link
                      to={`/movies/${entry.movie.slug}`}
                      className={styles.thumb}
                      style={{ background: thumbBackground(entry.movie) }}
                      aria-label={entry.movie.title}
                    />
                    <div className={styles.info}>
                      <Link to={`/movies/${entry.movie.slug}`} className={styles.itemTitle}>
                        {entry.movie.title}
                      </Link>
                      <p className={styles.details}>{entry.movie.meta}</p>
                      <div className={styles.meta}>
                        <RatingStars value={entry.rating} label={`Your rating for ${entry.movie.title}`} />
                        <span className={styles.added}>Watched {entry.date}</span>
                      </div>
                    </div>
                    <button type="button" className={styles.watchedOn} aria-pressed onClick={() => toggleWatched(entry.id)}>
                      <img src={checkIcon} width={16} height={16} alt="" />
                      Watched
                    </button>
                  </li>
                ))}
              </ul>
            )}

            {tab === 'history' && (
              <div className={styles.historyFull}>
                <HistoryList entries={history} />
              </div>
            )}
          </section>

          <aside className={styles.side} data-enter data-enter-delay="0.35">
            <section className={styles.history} aria-labelledby="history-title">
              <div className={styles.historyHead}>
                <h2 id="history-title" className={styles.cardTitle}>
                  Watch history
                </h2>
                <button type="button" className={styles.seeAll} onClick={showHistory}>
                  See all
                </button>
              </div>
              {history.length > 0 ? <HistoryList entries={history.slice(0, 5)} /> : <p className={styles.added}>Films you mark as watched show up here.</p>}
            </section>

            {taste.length > 0 && (
            <section className={styles.taste} aria-labelledby="taste-title">
              <h2 id="taste-title" className={styles.cardTitleLight}>
                What you watch
              </h2>
              <ul className={styles.bars}>
                {taste.map((t) => (
                  <li key={t.genre} className={styles.bar}>
                    <span className={styles.barLabels}>
                      <span>{t.genre}</span>
                      <span className={styles.barPercent}>{t.percent}%</span>
                    </span>
                    {/* Bars are scaled to the top genre, as in the Figma frame. */}
                    <span className={styles.track}>
                      <span className={styles.fill} style={{ width: `${(t.percent / maxTaste) * 100}%`, background: t.color }} />
                    </span>
                  </li>
                ))}
              </ul>
            </section>
            )}
          </aside>
        </div>
      </main>
    </div>
  )
}
