import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router'
import Sentiment from '../../components/Sentiment/Sentiment.tsx'
import RatingStars from '../../components/RatingStars/RatingStars.tsx'
import chevDownIcon from '../../assets/watchlist/icon-chev-down.svg'
import searchIcon from '../../assets/listdetail/icon-search.svg'
import eyeIcon from '../../assets/profile/icon-eye.svg'
import checkIcon from '../../assets/watchlist/icon-check-sm.svg'
import flagIcon from '../../assets/admin/icon-flag-sm.svg'
import trashIcon from '../../assets/admin/icon-trash.svg'
import bookIcon from '../../assets/admin/icon-book.svg'
import checkMintIcon from '../../assets/admin/icon-check-mint.svg'
import { api } from '../../lib/api.ts'
import { apiErrorMessage } from '../../lib/auth.ts'
import { colorOf, initialsOf, longDate } from '../../lib/format.ts'
import { GUIDELINES, REVIEW_SENTIMENTS, type ReviewSentiment } from './adminData.ts'
import styles from './AdminReviews.module.css'

type Tab = 'flagged' | 'approved'
const TABS: { id: Tab; label: string }[] = [
  { id: 'flagged', label: 'Flagged' },
  { id: 'approved', label: 'Approved' },
]
const FLAG_REASONS = ['Offensive language', 'Spoiler', 'Spam', 'Awaiting classification'] as const
type FlagReason = typeof FLAG_REASONS[number]
type ApiReview = {
  id: number
  tmdb_id: number
  media_type: 'movie' | 'tv'
  content: string
  rating: number
  sentiment: ReviewSentiment | null
  is_approved: boolean
  moderation_labels: string[] | null
  author: { id: number | null; name: string | null; username: string | null }
  created_at: string | null
}
type ReviewsPage = { data: ApiReview[]; meta: { current_page: number; last_page: number; total: number } }

const capitalize = (text: string) => text[0].toUpperCase() + text.slice(1)

export default function AdminReviews() {
  const [data, setData] = useState<ReviewsPage | null>(null)
  const [tab, setTab] = useState<Tab>('flagged')
  const [page, setPage] = useState(1)
  const [revision, setRevision] = useState(0)
  const [query, setQuery] = useState('')
  const [sentiment, setSentiment] = useState<ReviewSentiment | 'all'>('all')
  const [reason, setReason] = useState<FlagReason | 'all'>('all')
  const [note, setNote] = useState('')
  const [loading, setLoading] = useState(true)
  const [failed, setFailed] = useState(false)
  const [busy, setBusy] = useState<number | null>(null)

  useEffect(() => {
    let active = true
    api.get<ReviewsPage>('/admin/reviews', { params: {
      status: tab === 'flagged' ? 'pending' : 'approved', page,
      q: query.trim() || undefined,
      sentiment: sentiment === 'all' ? undefined : sentiment,
      reason: reason === 'all' ? undefined : reason.toLowerCase(),
    } })
      .then(({ data: result }) => {
        if (active) { setData(result); setFailed(false) }
      })
      .catch((error) => {
        if (active) { setFailed(true); setNote(apiErrorMessage(error, 'Couldn’t load reviews.')) }
      })
      .finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [tab, page, revision, query, sentiment, reason])

  const reviews = useMemo(() => (data?.data ?? []).map((review) => ({
    ...review,
    user: review.author.name ?? review.author.username ?? 'Deleted user',
    initials: initialsOf(review.author.name ?? '?'),
    color: colorOf(review.author.id ?? 0),
    movie: { slug: `${review.media_type === 'tv' ? 'tv-' : ''}${review.tmdb_id}`, title: `${review.media_type === 'tv' ? 'TV show' : 'Movie'} #${review.tmdb_id}` },
    time: review.created_at ? longDate(review.created_at) : '',
    sentiment: review.sentiment ?? 'neutral',
    text: review.content,
    reasons: (review.moderation_labels === null ? ['Awaiting classification'] : review.moderation_labels.map(capitalize)) as FlagReason[],
  })), [data])

  const resetPage = () => {
    setPage(1)
    setData(null)
    setLoading(true)
  }

  const decide = async (review: ApiReview, status: 'approved' | 'rejected') => {
    if (busy !== null) return
    setBusy(review.id)
    try {
      await api.patch(`/admin/reviews/${review.id}`, { status })
      setNote(status === 'approved' ? 'Review approved and published.' : 'Review removed. Its star rating was kept.')
      setData(null)
      setLoading(true)
      setPage(1)
      setRevision((n) => n + 1)
    } catch (error) {
      setNote(apiErrorMessage(error, 'Couldn’t save this decision.'))
    } finally {
      setBusy(null)
    }
  }

  const hasFilters = query.trim() !== '' || sentiment !== 'all' || reason !== 'all'

  return (
    <div className={styles.page}>
      <header className={styles.topBar}>
        <div className={styles.titleBlock}>
          <h1 className={styles.title}>Review moderation</h1>
          <p className={styles.lead}>Groq flags offensive language, spoilers and spam for your approval.</p>
        </div>
        <div className={styles.controls}>
          <label className={styles.search}>
            <img src={searchIcon} width={16} height={16} alt="" />
            <input
              type="search"
              value={query}
              maxLength={200}
              onChange={(event) => { setQuery(event.target.value); resetPage() }}
              placeholder="Search reviews or users"
              aria-label="Search reviews or users"
            />
          </label>
          <label className={styles.select}>
            <span className="sr-only">Sentiment</span>
            <select value={sentiment} onChange={(event) => { setSentiment(event.target.value as ReviewSentiment | 'all'); resetPage() }}>
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
            <select value={reason} onChange={(event) => { setReason(event.target.value as FlagReason | 'all'); resetPage() }}>
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
            onClick={() => { if (tab !== t.id) { setTab(t.id); setPage(1); setData(null); setLoading(true) } }}
          >
            <span>
              {t.label}
              {tab === t.id && data && <span className={styles.tabCount}>{data.meta.total.toLocaleString('en-US')}</span>}
            </span>
            <span className={styles.indicator} aria-hidden="true" />
          </button>
        ))}
      </div>

      {note && (
        <p className={styles.note} role="status">
          {note}
        </p>
      )}

      <div className={styles.body}>
        <div id="review-queue" className={styles.queue} role="tabpanel" aria-labelledby={`tab-${tab}`}>
          {loading && <p role="status">Loading reviews…</p>}
          {failed && <button type="button" onClick={() => { setLoading(true); setRevision((n) => n + 1) }}>Try again</button>}
          {!loading && !failed && reviews.length === 0 && (
            <div className={styles.empty}>
              <img src={checkMintIcon} width={28} height={28} alt="" />
              <p className={styles.emptyTitle}>{hasFilters ? 'No reviews match these filters' : 'Nothing here'}</p>
              <p className={styles.emptyText}>
                {hasFilters ? 'Try another sentiment, reason or search.' : 'Reviews show up here as they are flagged and handled.'}
              </p>
            </div>
          )}

          {!loading && !failed && reviews.map((review) => (
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
                </div>

                <div className={styles.actions}>
                  <Link to={`/movies/${review.movie.slug}`} className={styles.view}>
                    <img src={eyeIcon} width={14} height={14} alt="" />
                    View
                  </Link>
                  <button type="button" className={styles.remove} onClick={() => void decide(review, 'rejected')} disabled={busy !== null}>
                    <img src={trashIcon} width={14} height={14} alt="" />
                    {review.is_approved ? 'Remove' : 'Decline'}
                  </button>
                  {!review.is_approved ? (
                    <button type="button" className={styles.approve} onClick={() => void decide(review, 'approved')} disabled={busy !== null}>
                      <img src={checkIcon} width={14} height={14} alt="" />
                      Approve
                    </button>
                  ) : <span className={styles.badgeApproved}>Approved</span>}
                </div>
              </div>
            </article>
          ))}
          {data && data.meta.last_page > 1 && (
            <div className={styles.actions}>
              <button type="button" disabled={loading || page <= 1} onClick={() => { setLoading(true); setPage((n) => n - 1) }}>Previous</button>
              <span>Page {page} of {data.meta.last_page}</span>
              <button type="button" disabled={loading || page >= data.meta.last_page} onClick={() => { setLoading(true); setPage((n) => n + 1) }}>Next</button>
            </div>
          )}
        </div>

        <aside className={styles.side}>
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
