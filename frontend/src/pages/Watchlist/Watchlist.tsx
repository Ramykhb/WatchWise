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
import { FILTERS, HISTORY, QUEUE, TASTE, TOTALS, type Availability, type HistoryEntry, type QueueItem } from './watchlistData.ts'
import styles from './Watchlist.module.css'

type Tab = 'watchlist' | 'watched' | 'history'

const SORTS = {
  recent: { label: 'Recently added', compare: (a: QueueItem, b: QueueItem) => b.addedOrder - a.addedOrder },
  title: { label: 'Title A–Z', compare: (a: QueueItem, b: QueueItem) => a.movie.title.localeCompare(b.movie.title) },
  rating: { label: 'Highest rated', compare: (a: QueueItem, b: QueueItem) => Number(b.movie.score) - Number(a.movie.score) },
  runtime: { label: 'Shortest first', compare: (a: QueueItem, b: QueueItem) => a.runtimeMinutes - b.runtimeMinutes },
}
type SortKey = keyof typeof SORTS

const MAX_TASTE = Math.max(...TASTE.map((t) => t.percent))
const INITIALLY_WATCHED = QUEUE.filter((item) => item.watched).length

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
          <li key={`${entry.date}-${entry.movie.slug}`}>
            <Link to={`/movies/${entry.movie.slug}`} className={styles.entry}>
              <span className={styles.date}>{entry.date}</span>
              <span className={styles.entryThumb} style={{ background: gradientCss(entry.movie.gradient) }} />
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

  // TODO: load and update the watchlist through the API once auth is connected.
  const [queue, setQueue] = useState(QUEUE)
  const [tab, setTab] = useState<Tab>('watchlist')
  const [filter, setFilter] = useState<Availability | 'all'>('all')
  const [sort, setSort] = useState<SortKey>('recent')
  const [shareNote, setShareNote] = useState('')

  const watchedNow = queue.filter((item) => item.watched).length
  const removed = QUEUE.length - queue.length
  const toWatch = TOTALS.toWatch - removed - (watchedNow - INITIALLY_WATCHED)
  const watchedTotal = TOTALS.watched + (watchedNow - INITIALLY_WATCHED)

  const shown = queue.filter((item) => filter === 'all' || item.availability === filter).sort(SORTS[sort].compare)

  const toggleWatched = (slug: string) =>
    setQueue((items) => items.map((item) => (item.movie.slug === slug ? { ...item, watched: !item.watched } : item)))
  const remove = (slug: string) => setQueue((items) => items.filter((item) => item.movie.slug !== slug))

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
                {TOTALS.queuedHours}h queued
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
                <div className={styles.toolbar}>
                  <div className={styles.filters} role="group" aria-label="Filter by availability">
                    {FILTERS.map((f) => (
                      <button
                        key={f.id}
                        type="button"
                        className={filter === f.id ? styles.filterOn : styles.filter}
                        aria-pressed={filter === f.id}
                        onClick={() => setFilter(f.id)}
                      >
                        {f.label}
                      </button>
                    ))}
                  </div>
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

                {shown.length === 0 && <p className={styles.empty}>Nothing here yet — try another filter.</p>}

                <ul className={styles.items}>
                  {shown.map((item) => (
                    <li key={item.movie.slug} className={styles.item}>
                      <Link
                        to={`/movies/${item.movie.slug}`}
                        className={styles.thumb}
                        style={{ background: gradientCss(item.movie.gradient) }}
                        aria-label={item.movie.title}
                      />
                      <div className={styles.info}>
                        <Link to={`/movies/${item.movie.slug}`} className={styles.itemTitle}>
                          {item.movie.title}
                        </Link>
                        <p className={styles.details}>{item.details}</p>
                        <div className={styles.meta}>
                          <span className={item.availability === 'soon' ? styles.chipYellow : styles.chipMint}>
                            {item.availabilityLabel}
                          </span>
                          <span className={styles.added}>{item.added}</span>
                        </div>
                      </div>
                      <button
                        type="button"
                        className={item.watched ? styles.watchedOn : styles.watched}
                        aria-pressed={item.watched}
                        onClick={() => toggleWatched(item.movie.slug)}
                      >
                        <img src={item.watched ? checkIcon : eyeIcon} width={16} height={16} alt="" />
                        {item.watched ? 'Watched' : 'Mark as watched'}
                      </button>
                      <button
                        type="button"
                        className={styles.remove}
                        aria-label={`Remove ${item.movie.title} from watchlist`}
                        onClick={() => remove(item.movie.slug)}
                      >
                        <img src={trashIcon} width={18} height={18} alt="" />
                      </button>
                    </li>
                  ))}
                </ul>
              </>
            )}

            {tab === 'watched' && (
              <ul className={styles.items}>
                {HISTORY.map((entry) => (
                  <li key={entry.movie.slug} className={styles.item}>
                    <Link
                      to={`/movies/${entry.movie.slug}`}
                      className={styles.thumb}
                      style={{ background: gradientCss(entry.movie.gradient) }}
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
                  </li>
                ))}
              </ul>
            )}

            {tab === 'history' && (
              <div className={styles.historyFull}>
                <HistoryList entries={HISTORY} />
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
              <HistoryList entries={HISTORY} />
            </section>

            <section className={styles.taste} aria-labelledby="taste-title">
              <h2 id="taste-title" className={styles.cardTitleLight}>
                What you watch
              </h2>
              <ul className={styles.bars}>
                {TASTE.map((t) => (
                  <li key={t.genre} className={styles.bar}>
                    <span className={styles.barLabels}>
                      <span>{t.genre}</span>
                      <span className={styles.barPercent}>{t.percent}%</span>
                    </span>
                    {/* Bars are scaled to the top genre, as in the Figma frame. */}
                    <span className={styles.track}>
                      <span className={styles.fill} style={{ width: `${(t.percent / MAX_TASTE) * 100}%`, background: t.color }} />
                    </span>
                  </li>
                ))}
              </ul>
            </section>
          </aside>
        </div>
      </main>
    </div>
  )
}
