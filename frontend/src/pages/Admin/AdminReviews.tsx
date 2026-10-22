import { useMemo, useState } from 'react'
import { Link } from 'react-router'
import Sentiment from '../../components/Sentiment/Sentiment.tsx'
import RatingStars from '../../components/RatingStars/RatingStars.tsx'
import chevDownIcon from '../../assets/watchlist/icon-chev-down.svg'
import searchIcon from '../../assets/listdetail/icon-search.svg'
import eyeIcon from '../../assets/profile/icon-eye.svg'
import checkIcon from '../../assets/watchlist/icon-check-sm.svg'
import flagIcon from '../../assets/admin/icon-flag-sm.svg'
import warnIcon from '../../assets/admin/icon-warn.svg'
import trashIcon from '../../assets/admin/icon-trash.svg'
import bookIcon from '../../assets/admin/icon-book.svg'
import checkMintIcon from '../../assets/admin/icon-check-mint.svg'
import { gradientCss } from '../Home/homeData.ts'
import {
  FLAG_REASONS,
  GUIDELINES,
  MODERATION_REVIEWS,
  MODERATION_WEEK,
  REVIEW_SENTIMENTS,
  REVIEW_TOTALS,
  SENTIMENT_SHARE,
  type FlagReason,
  type ModerationReview,
  type ModerationStatus,
  type ReviewSentiment,
} from './adminData.ts'
import styles from './AdminReviews.module.css'

type Tab = 'flagged' | 'all' | 'approved' | 'removed'

const TABS: { id: Tab; label: string }[] = [
  { id: 'flagged', label: 'Flagged' },
  { id: 'all', label: 'All reviews' },
  { id: 'approved', label: 'Approved' },
  { id: 'removed', label: 'Removed' },
]

const SENTIMENT_ORDER: Record<ReviewSentiment, number> = { negative: 0, neutral: 1, positive: 2 }
const capitalize = (text: string) => text[0].toUpperCase() + text.slice(1)

type Note = { text: string; undo?: () => void }

export default function AdminReviews() {
  // TODO: load the queue from the admin API and send each decision to it.
  const [reviews, setReviews] = useState(MODERATION_REVIEWS)
  const [warned, setWarned] = useState<Set<string>>(() => new Set())
  const [tab, setTab] = useState<Tab>('flagged')
  const [query, setQuery] = useState('')
  const [sentiment, setSentiment] = useState<ReviewSentiment | 'all'>('all')
  const [reason, setReason] = useState<FlagReason | 'all'>('all')
  const [note, setNote] = useState<Note | null>(null)

  // Decisions taken here move the site-wide totals too.
  const handled = (status: ModerationStatus) =>
    reviews.filter((r) => r.status === status && MODERATION_REVIEWS.find((o) => o.id === r.id)?.status === 'flagged').length
  const flaggedCount = REVIEW_TOTALS.flagged - handled('approved') - handled('removed')
  const removedCount = REVIEW_TOTALS.removed + handled('removed')
  const counts: Partial<Record<Tab, number>> = { flagged: flaggedCount, removed: removedCount }

  const visible = useMemo(() => {
    const q = query.trim().toLowerCase()
    return reviews
      .filter((r) => tab === 'all' || r.status === tab)
      .filter((r) => sentiment === 'all' || r.sentiment === sentiment)
      .filter((r) => reason === 'all' || r.reasons.includes(reason))
      .filter((r) => !q || [r.user, r.text, r.movie.title].some((field) => field.toLowerCase().includes(q)))
      .sort((a, b) => SENTIMENT_ORDER[a.sentiment] - SENTIMENT_ORDER[b.sentiment] || b.reports - a.reports)
  }, [reviews, tab, sentiment, reason, query])

  const setStatus = (review: ModerationReview, status: ModerationStatus) => {
    const previous = review.status
    setReviews((list) => list.map((r) => (r.id === review.id ? { ...r, status } : r)))
    setNote({
      text: `Review by ${review.user} ${status === 'removed' ? 'removed' : 'approved'}.`,
      undo: () => {
        setReviews((list) => list.map((r) => (r.id === review.id ? { ...r, status: previous } : r)))
        setNote({ text: `Review by ${review.user} is back in the queue.` })
      },
    })
  }

  const remove = (review: ModerationReview) => {
    if (window.confirm(`Remove this review by ${review.user}? It will be hidden from ${review.movie.title}.`)) {
      setStatus(review, 'removed')
    }
  }

  const warn = (review: ModerationReview) => {
    setWarned((set) => new Set(set).add(review.id))
    setNote({ text: `${review.user} was warned about the community guidelines.` })
  }

  const hasFilters = query.trim() !== '' || sentiment !== 'all' || reason !== 'all'

  return (
    <div className={styles.page}>
      <header className={styles.topBar}>
        <div className={styles.titleBlock}>
          <h1 className={styles.title}>Review moderation</h1>
          <p className={styles.lead}>Flagged reviews are auto-sorted by sentiment and report count.</p>
        </div>
        <div className={styles.controls}>
          <label className={styles.search}>
            <img src={searchIcon} width={16} height={16} alt="" />
            <input
              type="search"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Search reviews or users"
              aria-label="Search reviews or users"
            />
          </label>
          <label className={styles.select}>
            <span className="sr-only">Sentiment</span>
            <select value={sentiment} onChange={(event) => setSentiment(event.target.value as ReviewSentiment | 'all')}>
              <option value="all">Sentiment: All</option>
              {REVIEW_SENTIMENTS.map((s) => (
                <option key={s} value={s}>
                  Sentiment: {capitalize(s)}
                </option>
              ))}
            </select>
            <img src={chevDownIcon} width={14} height={14} alt="" />
          </label>
          <label className={styles.select}>
            <span className="sr-only">Reason</span>
            <select value={reason} onChange={(event) => setReason(event.target.value as FlagReason | 'all')}>
              <option value="all">Reason: All</option>
              {FLAG_REASONS.map((r) => (
                <option key={r} value={r}>
                  Reason: {r}
                </option>
              ))}
            </select>
            <img src={chevDownIcon} width={14} height={14} alt="" />
          </label>
        </div>
      </header>

      <div className={styles.tabs} role="tablist" aria-label="Review status">
        {TABS.map((t) => (
          <button
            key={t.id}
            type="button"
            role="tab"
            id={`tab-${t.id}`}
            aria-selected={tab === t.id}
            aria-controls="review-queue"
            className={tab === t.id ? styles.tabOn : styles.tab}
            onClick={() => setTab(t.id)}
          >
            <span>
              {t.label}
              {counts[t.id] !== undefined && <span className={styles.tabCount}>{counts[t.id]!.toLocaleString('en-US')}</span>}
            </span>
            <span className={styles.indicator} aria-hidden="true" />
          </button>
        ))}
      </div>

      {note && (
        <p className={styles.note} role="status">
          {note.text}
          {note.undo && (
            <button type="button" className={styles.undo} onClick={note.undo}>
              Undo
            </button>
          )}
        </p>
      )}

      <div className={styles.body}>
        <div id="review-queue" className={styles.queue} role="tabpanel" aria-labelledby={`tab-${tab}`}>
          {visible.length === 0 && (
            <div className={styles.empty}>
              <img src={checkMintIcon} width={28} height={28} alt="" />
              <p className={styles.emptyTitle}>{hasFilters ? 'No reviews match these filters' : 'Nothing here'}</p>
              <p className={styles.emptyText}>
                {hasFilters ? 'Try another sentiment, reason or search.' : 'Reviews show up here as they are flagged and handled.'}
              </p>
            </div>
          )}

          {visible.map((review) => (
            <article key={review.id} className={styles.review} aria-label={`Review by ${review.user}`}>
              <div className={styles.head}>
                <span className={styles.avatar} style={{ background: review.color }} aria-hidden="true">
                  {review.initials}
                </span>
                <div className={styles.who}>
                  <p className={styles.user}>{review.user}</p>
                  <p className={styles.meta}>
                    on <Link to={`/movies/${review.movie.slug}`}>{review.movie.title}</Link> · {review.time}
                  </p>
                </div>
                <RatingStars value={review.rating} />
                <Sentiment type={review.sentiment} />
              </div>

              <div className={styles.context}>
                <span className={styles.thumb} style={{ background: gradientCss(review.movie.gradient) }} />
                <p className={styles.text}>{review.text}</p>
              </div>

              <div className={styles.footer}>
                <div className={styles.reasons}>
                  {review.reasons.map((r) => (
                    <span key={r} className={styles.reason}>
                      <img src={flagIcon} width={13} height={13} alt="" />
                      {r}
                    </span>
                  ))}
                  <span className={styles.reports}>
                    {review.reports} report{review.reports === 1 ? '' : 's'}
                  </span>
                </div>

                <div className={styles.actions}>
                  <Link to={`/movies/${review.movie.slug}`} className={styles.view}>
                    <img src={eyeIcon} width={14} height={14} alt="" />
                    View
                  </Link>
                  {review.status === 'flagged' ? (
                    <>
                      <button type="button" className={styles.warn} onClick={() => warn(review)} disabled={warned.has(review.id)}>
                        <img src={warnIcon} width={14} height={14} alt="" />
                        {warned.has(review.id) ? 'Warned' : 'Warn user'}
                      </button>
                      <button type="button" className={styles.remove} onClick={() => remove(review)}>
                        <img src={trashIcon} width={14} height={14} alt="" />
                        Remove
                      </button>
                      <button type="button" className={styles.approve} onClick={() => setStatus(review, 'approved')}>
                        <img src={checkIcon} width={14} height={14} alt="" />
                        Approve
                      </button>
                    </>
                  ) : (
                    <>
                      <span className={review.status === 'removed' ? styles.badgeRemoved : styles.badgeApproved}>
                        {capitalize(review.status)}
                      </span>
                      <button type="button" className={styles.view} onClick={() => setStatus(review, 'flagged')}>
                        Reopen
                      </button>
                    </>
                  )}
                </div>
              </div>
            </article>
          ))}
        </div>

        <aside className={styles.side}>
          <section className={styles.card} aria-labelledby="sentiment-title">
            <h2 id="sentiment-title" className={styles.cardTitle}>
              Sentiment overview
            </h2>
            <p className={styles.cardSub}>All reviews · last 30 days</p>
            <div
              className={styles.stacked}
              role="img"
              aria-label={SENTIMENT_SHARE.map((s) => `${capitalize(s.type)} ${s.percent}%`).join(', ')}
            >
              {SENTIMENT_SHARE.map((s) => (
                <span key={s.type} style={{ flexGrow: s.percent, background: s.color }} />
              ))}
            </div>
            <ul className={styles.shares}>
              {SENTIMENT_SHARE.map((s) => (
                <li key={s.type}>
                  <button
                    type="button"
                    className={styles.share}
                    aria-pressed={sentiment === s.type}
                    onClick={() => setSentiment(sentiment === s.type ? 'all' : s.type)}
                    title={`Show ${s.type} reviews only`}
                  >
                    <Sentiment type={s.type} />
                    <span className={styles.shareVal}>
                      <span className={styles.sharePercent}>{s.percent}%</span>
                      <span className={styles.shareCount}>{s.count}</span>
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          </section>

          <section className={styles.week} aria-labelledby="week-title">
            <h2 id="week-title" className={styles.weekTitle}>
              This week
            </h2>
            <dl className={styles.weekRows}>
              <div>
                <dt>Reviews flagged</dt>
                <dd>{MODERATION_WEEK.flagged}</dd>
              </div>
              <div>
                <dt>Removed</dt>
                <dd>{MODERATION_WEEK.removed + handled('removed')}</dd>
              </div>
              <div>
                <dt>Approved</dt>
                <dd>{MODERATION_WEEK.approved + handled('approved')}</dd>
              </div>
              <div>
                <dt>Avg. response</dt>
                <dd>{MODERATION_WEEK.avgResponse}</dd>
              </div>
            </dl>
          </section>

          <section className={styles.card} aria-labelledby="guidelines-title">
            <h2 id="guidelines-title" className={styles.guideTitle}>
              <img src={bookIcon} width={18} height={18} alt="" />
              Community guidelines
            </h2>
            <ul className={styles.rules}>
              {GUIDELINES.map((rule) => (
                <li key={rule}>
                  <img src={checkMintIcon} width={14} height={14} alt="" />
                  {rule}
                </li>
              ))}
            </ul>
          </section>
        </aside>
      </div>
    </div>
  )
}
