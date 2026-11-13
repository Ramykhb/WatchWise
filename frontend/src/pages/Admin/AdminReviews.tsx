import { AdminReviewsSkeleton } from '../../components/LoadingState/ScreenSkeletons.tsx'
import { useEffect, useMemo, useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import AdminPagination from '../../components/AdminPagination/AdminPagination.tsx'
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
import {
  adminApi,
  adminPosterUrl,
  useAdminReviews,
  useAdminStats,
  type AdminReview,
  type AdminReviewStatus,
} from '../../lib/adminApi.ts'
import { apiErrorMessage } from '../../lib/auth.ts'
import { colorOf, initialsOf, timeAgo } from '../../lib/format.ts'
import { GUIDELINES, REVIEW_SENTIMENTS } from './adminData.ts'
import styles from './AdminReviews.module.css'
import { bulkSummary, mutate } from '../../lib/adminTools.ts'
import { Field, Select } from './AdminToolsUI.tsx'
import { s } from './adminToolsState.tsx'

const TABS: { id: AdminReviewStatus; label: string }[] = [
  { id: 'flagged', label: 'Flagged' },
  { id: 'pending', label: 'Pending' },
  { id: 'approved', label: 'Approved' },
  { id: 'removed', label: 'Removed' },
]
const PER_PAGE = 20
const SENTIMENT_COLOR = { positive: 'var(--accent-mint)', neutral: 'var(--surface-violet-tint)', negative: 'var(--accent-coral)' }

const capitalize = (text: string) => text[0].toUpperCase() + text.slice(1)
const fmt = (n: number) => n.toLocaleString('en-US')
const authorOf = (review: AdminReview) => review.author.name ?? review.author.username ?? 'Deleted user'
const movieOf = (review: AdminReview) => review.movie.title ?? `${review.media_type === 'tv' ? 'TV show' : 'Movie'} #${review.tmdb_id}`
const reviewPath = (review: AdminReview) => `/movies/${review.media_type === 'tv' ? 'tv-' : ''}${review.tmdb_id}`

export default function AdminReviews() {
  const [params] = useSearchParams()
  const [tab, setTab] = useState<AdminReviewStatus>(params.has('user_id') ? 'approved' : 'flagged')
  const [authorId, setAuthorId] = useState(params.get('user_id') ?? '')
  const [filmId, setFilmId] = useState('')
  const [mediaType, setMediaType] = useState('')
  const [selection, setSelection] = useState<number[]>([])
  const [page, setPage] = useState(1)
  const [query, setQuery] = useState('')
  const [search, setSearch] = useState('')
  const [busy, setBusy] = useState(false)
  const [sentiment, setSentiment] = useState<(typeof REVIEW_SENTIMENTS)[number] | 'all'>('all')
  const [reason, setReason] = useState('all')
  const [note, setNote] = useState('')

  const { data, error, loading, setData, reload } = useAdminReviews({ status: tab, page, per_page: PER_PAGE, q: search, user_id: authorId ? Number(authorId) : undefined, tmdb_id: filmId ? Number(filmId) : undefined, media_type: mediaType || undefined, sentiment: sentiment === 'all' ? undefined : sentiment, reason: reason === 'all' ? undefined : reason })
  const { data: stats, reload: reloadStats } = useAdminStats()
  const reviews = useMemo(() => data?.data ?? [], [data])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setSearch(query.trim())
      setSelection([])
      setPage(1)
    }, 300)
    return () => window.clearTimeout(timer)
  }, [query])

  const bulk = async (status: 'approved' | 'rejected') => {
    if (busy || !selection.length) return
    if (!window.confirm(`${status === 'approved' ? 'Approve' : 'Remove text from'} ${selection.length} selected reviews?`)) return
    setBusy(true)
    try {
      const response = await mutate('reviews/bulk', 'post', { ids: selection, status })
      setNote(bulkSummary(response.results ?? [])); setSelection([]); reload(); reloadStats()
    } catch (error) { setNote(apiErrorMessage(error, 'Unable to moderate the selected reviews.')) }
    finally { setBusy(false) }
  }

  const reasons = ['Spam', 'Links', 'Spoilers', 'Offensive language', 'offensive language', 'spoiler', 'spam', 'awaiting classification']
  const visible = reviews

  const summary = data?.summary
  const shares = REVIEW_SENTIMENTS.map((type) => ({ type, ...(summary?.sentiment[type] ?? { count: 0, percent: 0 }) }))

  const decide = async (review: AdminReview, decision: 'approved' | 'rejected') => {
    if (busy) return
    setBusy(true)
    try {
      await adminApi.moderateReview(review.id, decision)
      setData((current) => ({ ...current, data: current.data.filter((r) => r.id !== review.id) }))
      setNote(`Review by ${authorOf(review)} ${decision === 'approved' ? 'approved' : 'removed'}.`)
      setPage(1)
      reload()
      reloadStats()
    } catch (moderationError) {
      setNote(apiErrorMessage(moderationError, 'Unable to update this review. Please try again.'))
    } finally {
      setBusy(false)
    }
  }

  const remove = (review: AdminReview) => {
    if (window.confirm(`Remove this review by ${authorOf(review)}? Its text is deleted and only the star rating stays.`)) {
      void decide(review, 'rejected')
    }
  }

  const hasFilters = query.trim() !== '' || sentiment !== 'all' || reason !== 'all'
  const counts: Partial<Record<AdminReviewStatus, number>> = {
    ...(summary ? { flagged: summary.flagged, removed: summary.removed } : {}),
    ...(stats ? { pending: stats.reviews_pending } : {}),
  }

  return (
    <div className={styles.page}>
      <header className={styles.topBar}>
        <div className={styles.titleBlock}>
          <h1 className={styles.title}>Review moderation</h1>
          <p className={styles.lead}>Review the written reviews members post and decide which stay.</p>
        </div>
        <div className={styles.controls}>
          <label className={styles.search}>
            <img src={searchIcon} width={16} height={16} alt="" />
            <input
              type="search"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Search reviews or users"
              aria-label="Search reviews or users on this page"
            />
          </label>
          <label className={styles.select}>
            <span className="sr-only">Sentiment</span>
            <select value={sentiment} onChange={(event) => { setSentiment(event.target.value as typeof sentiment); setSelection([]); setPage(1) }}>
              <option value="all">Sentiment: All</option>
              {REVIEW_SENTIMENTS.map((s) => (
                <option key={s} value={s}>
                  Sentiment: {capitalize(s)}
                </option>
              ))}
            </select>
            <img src={chevDownIcon} width={14} height={14} alt="" />
          </label>
          {reasons.length > 0 && (
            <label className={styles.select}>
              <span className="sr-only">Reason</span>
              <select value={reason} onChange={(event) => { setReason(event.target.value); setSelection([]); setPage(1) }}>
                <option value="all">Reason: All</option>
                {reasons.map((r) => (
                  <option key={r} value={r}>
                    Reason: {r}
                  </option>
                ))}
              </select>
              <img src={chevDownIcon} width={14} height={14} alt="" />
            </label>
          )}
        </div>
      </header>

      <div className={s.page}><div className={s.bar}>
        <Field label="Author ID"><input type="number" min="1" value={authorId} onChange={(e) => { setAuthorId(e.target.value); setPage(1); setSelection([]) }} /></Field>
        <Field label="Film TMDB ID"><input type="number" min="1" value={filmId} onChange={(e) => { setFilmId(e.target.value); setPage(1); setSelection([]) }} /></Field>
        <Select label="Media" value={mediaType} options={['', 'movie', 'tv']} onChange={(v) => { setMediaType(v); setPage(1); setSelection([]) }} />
        <button onClick={() => setSelection(reviews.map((r) => r.id))}>Select this page</button><button onClick={() => setSelection([])}>Clear selection</button>
        <button disabled={busy || !selection.length} onClick={() => void bulk('approved')}>Approve {selection.length} selected</button>
        <button disabled={busy || !selection.length} onClick={() => void bulk('rejected')}>Remove selected text</button>
      </div></div>
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
            onClick={() => {
              setTab(t.id)
              setSelection([])
              setPage(1)
            }}
          >
            <span>
              {t.label}
              {counts[t.id] !== undefined && <span className={styles.tabCount}>{fmt(counts[t.id]!)}</span>}
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
        <div id="review-queue" className={styles.queue} role="tabpanel" aria-labelledby={`tab-${tab}`} aria-busy={loading}>
          {!data && !error && <AdminReviewsSkeleton />}
          {!data && Boolean(error) && (
            <div className={styles.empty} role="alert">
              <div className={styles.emptyTitle}>{apiErrorMessage(error, 'Unable to load reviews.')}</div>
              {Boolean(error) && (
                <button type="button" className={styles.view} onClick={reload}>
                  Try again
                </button>
              )}
            </div>
          )}
          {data && visible.length === 0 && (
            <div className={styles.empty}>
              <img src={checkMintIcon} width={28} height={28} alt="" />
              <p className={styles.emptyTitle}>{hasFilters ? 'No reviews match these filters' : 'Nothing here'}</p>
              <p className={styles.emptyText}>
                {hasFilters ? 'Try another sentiment, reason or search.' : 'Reviews show up here as members post them.'}
              </p>
            </div>
          )}

          {visible.map((review) => {
            const author = authorOf(review)
            const poster = adminPosterUrl(review.movie.poster_path)
            return (
              <article key={review.id} className={styles.review} aria-label={`Review by ${author}`}>
                <label className={s.check}><input type="checkbox" checked={selection.includes(review.id)} onChange={(e) => setSelection((ids) => e.target.checked ? [...ids, review.id] : ids.filter((id) => id !== review.id))} />Select review #{review.id}</label>
                <div className={styles.head}>
                  <span className={styles.avatar} style={{ background: colorOf(review.author.id ?? 0) }} aria-hidden="true">
                    {initialsOf(author)}
                  </span>
                  <div className={styles.who}>
                    <p className={styles.user}>{author}</p>
                    <p className={styles.meta}>
                      on <Link to={reviewPath(review)}>{movieOf(review)}</Link>
                      {review.created_at && ` · ${timeAgo(review.created_at)}`}
                    </p>
                  </div>
                  <RatingStars value={review.rating} />
                  {review.sentiment && <Sentiment type={review.sentiment} />}
                </div>

                <div className={styles.context}>
                  {poster && <img className={styles.thumb} src={poster} alt="" />}
                  <p className={styles.text}>{review.content ?? 'The text of this review was removed.'}</p>
                </div>

                <div className={styles.footer}>
                  <div className={styles.reasons}>
                    {[...new Set([...review.reasons, ...(review.moderation_labels ?? (review.status === 'pending' ? ['awaiting classification'] : []))])].map((r) => (
                      <span key={r} className={styles.reason}>
                        <img src={flagIcon} width={13} height={13} alt="" />
                        {r}
                      </span>
                    ))}
                    {review.reports_count > 0 && (
                      <span className={styles.reports}>
                        {review.reports_count} report{review.reports_count === 1 ? '' : 's'}
                      </span>
                    )}
                  </div>

                  <div className={styles.actions}>
                    <Link to={`/admin/reviews/${review.id}`} className={styles.view}>
                      <img src={eyeIcon} width={14} height={14} alt="" />
                      View
                    </Link>
                    {review.status !== 'removed' && (
                      <button type="button" className={styles.remove} disabled={busy} onClick={() => remove(review)}>
                        <img src={trashIcon} width={14} height={14} alt="" />
                        Remove
                      </button>
                    )}
                    {(review.status === 'flagged' || review.status === 'pending') && (
                      <button type="button" className={styles.approve} disabled={busy} onClick={() => void decide(review, 'approved')}>
                        <img src={checkIcon} width={14} height={14} alt="" />
                        Approve
                      </button>
                    )}
                  </div>
                </div>
              </article>
            )
          })}

          {data && <AdminPagination meta={data.meta} shown={reviews.length} onPage={(next) => { setPage(next); setSelection([]) }} />}
        </div>

        <aside className={styles.side}>
          <section className={styles.card} aria-labelledby="sentiment-title">
            <h2 id="sentiment-title" className={styles.cardTitle}>
              Sentiment overview
            </h2>
            <p className={styles.cardSub}>All reviews with text</p>
            {!summary || summary.sentiment_total === 0 ? (
              <p className={styles.cardSub}>No reviews to compare.</p>
            ) : (
              <>
                <div
                  className={styles.stacked}
                  role="img"
                  aria-label={shares.map((s) => `${capitalize(s.type)} ${s.percent}%`).join(', ')}
                >
                  {shares.map((s) => (
                    <span key={s.type} style={{ flexGrow: s.percent, background: SENTIMENT_COLOR[s.type] }} />
                  ))}
                </div>
                <ul className={styles.shares}>
                  {shares.map((s) => (
                    <li key={s.type}>
                      <button
                        type="button"
                        className={styles.share}
                        aria-pressed={sentiment === s.type}
                        onClick={() => { setSentiment(sentiment === s.type ? 'all' : s.type); setSelection([]); setPage(1) }}
                        title={`Show ${s.type} reviews only`}
                      >
                        <Sentiment type={s.type} />
                        <span className={styles.shareVal}>
                          <span className={styles.sharePercent}>{s.percent}%</span>
                          <span className={styles.shareCount}>{fmt(s.count)}</span>
                        </span>
                      </button>
                    </li>
                  ))}
                </ul>
              </>
            )}
          </section>

          {stats && (
            <section className={styles.week} aria-labelledby="week-title">
              <h2 id="week-title" className={styles.weekTitle}>
                Site totals
              </h2>
              <dl className={styles.weekRows}>
                <div>
                  <dt>Awaiting moderation</dt>
                  <dd>{fmt(stats.reviews_pending)}</dd>
                </div>
                <div>
                  <dt>All reviews</dt>
                  <dd>{fmt(stats.reviews_total)}</dd>
                </div>
                <div>
                  <dt>New, last 7 days</dt>
                  <dd>{fmt(stats.new_reviews.last_7_days)}</dd>
                </div>
                <div>
                  <dt>New, last 30 days</dt>
                  <dd>{fmt(stats.new_reviews.last_30_days)}</dd>
                </div>
              </dl>
            </section>
          )}

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
