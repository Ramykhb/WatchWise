import { useEffect, useRef, useState, type FormEvent } from 'react'
import ConfirmDialog from '../../components/ConfirmDialog/ConfirmDialog.tsx'
import PillButton from '../../components/PillButton/PillButton.tsx'
import RatingStars from '../../components/RatingStars/RatingStars.tsx'
import ReviewCard, { type Review } from '../../components/ReviewCard/ReviewCard.tsx'
import Sentiment from '../../components/Sentiment/Sentiment.tsx'
import editIcon from '../../assets/movie/icon-edit.svg'
import trashIcon from '../../assets/movie/icon-trash.svg'
import { api } from '../../lib/api.ts'
import { apiErrorMessage } from '../../lib/auth.ts'
import { colorOf, initialsOf, longDate } from '../../lib/format.ts'
import type { MediaType } from '../../lib/movies.ts'
import type { ApiMovieReview, ApiReviewsPage } from '../../lib/types.ts'
import styles from './MovieDetails.module.css'

const MAX_REVIEW = 2000

const SORTS = {
  helpful: 'Most helpful',
  newest: 'Newest',
  highest: 'Highest rated',
  lowest: 'Lowest rated',
} as const
type SortKey = keyof typeof SORTS

type MovieReviewsProps = {
  tmdbId: number
  mediaType: MediaType
  /** The signed-in member, for the "Write a review" avatar. */
  me: { id: number; name: string }
  /** The member's current stars (from "Your rating"), the review form starts from them. */
  stars: number
  /** Called with the review's stars once it is saved: they are the member's rating too. */
  onStarsSaved: (stars: number) => void
}

const toCard = (review: ApiMovieReview, isMine: boolean): Review => ({
  id: String(review.id),
  initials: initialsOf(review.author.name),
  color: colorOf(review.author.id),
  name: isMine ? `${review.author.name} (you)` : review.author.name,
  date: longDate(review.created_at),
  rating: review.rating,
  sentiment: review.sentiment ?? 'neutral',
  body: review.content,
  helpful: review.helpful,
  spoilers: review.has_spoilers,
  moderationLabels: review.moderation_labels ?? [],
})

/**
 * The Movie page's Reviews section for any movie or TV show (GET/PUT/DELETE /api/reviews): the
 * summary, writing, editing and deleting your review, other members' approved reviews with
 * "helpful" votes, sorting and "Load more".
 */
export default function MovieReviews({ tmdbId, mediaType, me, stars, onStarsSaved }: MovieReviewsProps) {
  const [sort, setSort] = useState<SortKey>('helpful')
  const [data, setData] = useState<{ key: string; page: ApiReviewsPage } | null>(null)
  const [more, setMore] = useState<{ page: number; loading: boolean }>({ page: 1, loading: false })
  const [failed, setFailed] = useState(false)
  const [retries, setRetries] = useState(0)

  const [draft, setDraft] = useState('')
  const [draftRating, setDraftRating] = useState(stars || 4)
  const [spoilers, setSpoilers] = useState(false)
  const [editing, setEditing] = useState(false)
  const [saving, setSaving] = useState(false)
  const [pendingReview, setPendingReview] = useState<Review | null>(null)
  const composerRef = useRef<HTMLDivElement>(null)
  const [composerHeight, setComposerHeight] = useState<number>()
  const [confirmingDelete, setConfirmingDelete] = useState(false)
  const [note, setNote] = useState('')

  const key = `${mediaType}-${tmdbId}-${sort}-${retries}`
  const page = data?.key === key ? data.page : null

  // The first page of the current sort; later pages are added by "Load more".
  useEffect(() => {
    let active = true
    api
      .get<ApiReviewsPage>('/reviews', { params: { tmdb_id: tmdbId, type: mediaType, sort } })
      .then((response) => {
        if (!active) return
        setData({ key, page: response.data })
        setMore({ page: 1, loading: false })
        setFailed(false)
      })
      .catch(() => active && setFailed(true))
    return () => {
      active = false
    }
  }, [tmdbId, mediaType, sort, key])

  // The form follows "Your rating" until the member picks stars here.
  const [lastStars, setLastStars] = useState(stars)
  if (stars !== lastStars) {
    setLastStars(stars)
    if (!editing && stars > 0) setDraftRating(stars)
  }

  const mine = page?.mine ?? null

  const loadMore = async () => {
    if (!page || more.loading) return
    const next = more.page + 1
    setMore({ page: more.page, loading: true })
    try {
      const response = await api.get<ApiReviewsPage>('/reviews', { params: { tmdb_id: tmdbId, type: mediaType, sort, page: next } })
      setData((current) =>
        current && current.key === key
          ? { key, page: { ...current.page, reviews: [...current.page.reviews, ...response.data.reviews], has_more: response.data.has_more } }
          : current,
      )
      setMore({ page: next, loading: false })
    } catch {
      setMore((current) => ({ ...current, loading: false }))
    }
  }

  // Refresh the totals without unmounting the form, cards or already loaded pages.
  const refreshSummary = () => {
    api.get<ApiReviewsPage>('/reviews', { params: { tmdb_id: tmdbId, type: mediaType, sort } })
      .then(({ data: result }) => {
        setData((current) => current && current.key === key
          ? { ...current, page: { ...current.page, summary: result.summary } }
          : current)
      })
      .catch(() => { /* The saved review stays visible if refreshing the totals fails. */ })
  }

  const handlePost = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const content = draft.trim()
    if (!content || saving) return
    const wasEditing = editing
    const rating = draftRating
    setComposerHeight(composerRef.current?.getBoundingClientRect().height)
    setPendingReview({
      id: mine ? String(mine.id) : 'pending',
      initials: initialsOf(me.name),
      color: colorOf(me.id),
      name: `${me.name} (you)`,
      date: longDate(new Date().toISOString()),
      rating,
      sentiment: 'neutral',
      body: content,
      helpful: 0,
      spoilers,
    })
    setSaving(true)
    setEditing(false)
    setNote('')
    try {
      const response = await api.put<{ review: ApiMovieReview }>('/reviews/mine', {
        tmdb_id: tmdbId,
        type: mediaType,
        content,
        rating,
        has_spoilers: spoilers,
      })
      setData((current) => (current && current.key === key ? { key, page: { ...current.page, mine: response.data.review } } : current))
      onStarsSaved(rating)
      setDraft('')
      setSpoilers(false)
      setNote(response.data.review.approved ? 'Review posted.' : 'Review submitted for admin approval.')
      refreshSummary()
    } catch (error) {
      setEditing(wasEditing)
      setNote(apiErrorMessage(error, 'Couldn’t post your review. Please try again.'))
    } finally {
      setPendingReview(null)
      setSaving(false)
    }
  }

  const handleEdit = () => {
    if (!mine) return
    setDraft(mine.content)
    setDraftRating(mine.rating)
    setSpoilers(mine.has_spoilers)
    setEditing(true)
    document.getElementById('review-text')?.focus()
  }

  const handleDelete = async () => {
    if (!mine) return
    setNote('')
    await api.delete('/reviews/mine', { params: { tmdb_id: tmdbId, type: mediaType } })
    setData((current) => (current && current.key === key ? { key, page: { ...current.page, mine: null } } : current))
    setConfirmingDelete(false)
    setEditing(false)
    setNote('Review deleted. Your star rating stays.')
    setComposerHeight(undefined)
    refreshSummary()
  }

  // Shows at once and saves in the background; a failed save puts the old count back.
  const toggleHelpful = (review: ApiMovieReview) => {
    const marked = !review.marked_helpful
    const patch = (update: Partial<ApiMovieReview>) =>
      setData((current) =>
        current && current.key === key
          ? { key, page: { ...current.page, reviews: current.page.reviews.map((r) => (r.id === review.id ? { ...r, ...update } : r)) } }
          : current,
      )
    patch({ marked_helpful: marked, helpful: review.helpful + (marked ? 1 : -1) })
    const request = marked ? api.post<{ helpful: number }>(`/reviews/${review.id}/helpful`) : api.delete<{ helpful: number }>(`/reviews/${review.id}/helpful`)
    request
      .then((response) => patch({ helpful: response.data.helpful, marked_helpful: marked }))
      .catch(() => patch({ marked_helpful: review.marked_helpful, helpful: review.helpful }))
  }

  const summary = page?.summary
  const showForm = !pendingReview && (!mine || editing)
  const ownReview = pendingReview ?? (mine ? { ...toCard(mine, true), rating: stars || mine.rating } : null)

  return (
    <section className={styles.reviews} aria-labelledby="reviews-title" aria-busy={!page || undefined}>
      <div className={styles.sectionHeader}>
        <h2 id="reviews-title" className={styles.sectionTitle}>
          Reviews
        </h2>
        <label className={styles.sort}>
          <span className="sr-only">Sort reviews</span>
          <select value={sort} disabled={saving} onChange={(event) => setSort(event.target.value as SortKey)}>
            {Object.entries(SORTS).map(([value, label]) => (
              <option key={value} value={value}>
                Sort: {label}
              </option>
            ))}
          </select>
          <span aria-hidden="true">▾</span>
        </label>
      </div>

      {failed && !page && (
        <p className={styles.muted} role="alert">
          Couldn’t load reviews.{' '}
          <button type="button" className={styles.reviewsRetry} onClick={() => setRetries((n) => n + 1)}>
            Try again
          </button>
        </p>
      )}

      {summary && (
        <div className={styles.summary}>
          <div className={styles.summaryAverage}>
            <span className={styles.summaryValue}>{summary.average !== null ? summary.average.toFixed(1) : '–'}</span>
            <RatingStars value={Math.round(summary.average ?? 0)} label="Average rating" />
            <span className={styles.muted}>
              {summary.ratings.toLocaleString('en-US')} {summary.ratings === 1 ? 'rating' : 'ratings'} · {summary.reviews.toLocaleString('en-US')}{' '}
              {summary.reviews === 1 ? 'review' : 'reviews'}
            </span>
          </div>
          <ul className={styles.distribution} aria-label="Rating distribution">
            {summary.distribution.map((percent, index) => (
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
            <p className={styles.muted}>{summary.reviews > 0 ? 'Auto-classified with sentiment analysis' : 'No reviews yet'}</p>
            <div className={styles.stacked} aria-hidden="true">
              <span className={styles.stackPositive} style={{ flexGrow: summary.sentiment.positive }} />
              <span className={styles.stackNeutral} style={{ flexGrow: summary.sentiment.neutral }} />
              <span className={styles.stackNegative} style={{ flexGrow: summary.sentiment.negative }} />
            </div>
            <ul className={styles.legend}>
              {(['positive', 'neutral', 'negative'] as const).map((type) => (
                <li key={type}>
                  <Sentiment type={type} />
                  <strong>{summary.sentiment[type]}%</strong>
                </li>
              ))}
            </ul>
          </div>
        </div>
      )}

      <div ref={composerRef} style={{ minHeight: composerHeight }}>
        {page && showForm && (
          <form className={styles.write} onSubmit={handlePost} aria-labelledby="write-title">
            <div className={styles.writeHead}>
              <div className={styles.writeWho}>
                <span className={styles.avatar} style={{ background: colorOf(me.id) }} aria-hidden="true">
                  {initialsOf(me.name)}
                </span>
                <h3 id="write-title" className={styles.writeTitle}>
                  {editing ? 'Edit your review' : 'Write a review'}
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
                <input type="checkbox" role="switch" checked={spoilers} onChange={(event) => setSpoilers(event.target.checked)} />
                <span className={styles.switchTrack} aria-hidden="true" />
                Contains spoilers
              </label>
              <div className={styles.writeRight}>
                <span className={styles.muted}>
                  {draft.length} / {MAX_REVIEW}
                </span>
                {editing && (
                  <PillButton variant="outline" onClick={() => setEditing(false)}>
                    Cancel
                  </PillButton>
                )}
                <PillButton type="submit" disabled={!draft.trim() || saving}>
                  {saving ? 'Posting…' : editing ? 'Save review' : 'Post review'}
                </PillButton>
              </div>
            </div>
          </form>
        )}

        {ownReview && !editing && (
          <div className={styles.yourReview} aria-busy={saving || undefined}>
            <div className={styles.yourHead}>
              <span className={styles.yourChip} role="status">{saving ? 'Your review · checking…' : mine?.approved ? 'Your review' : 'Your review · waiting for approval'}</span>
              {!saving && <div className={styles.manage}>
                <button type="button" className={styles.edit} onClick={handleEdit}>
                  <img src={editIcon} width={14} height={14} alt="" />
                  Edit
                </button>
                <button type="button" className={styles.delete} onClick={() => setConfirmingDelete(true)}>
                  <img src={trashIcon} width={14} height={14} alt="" />
                  Delete
                </button>
              </div>}
            </div>
            {/* Your review's stars are your rating: changing "Your rating" above changes them too. */}
            <div className={saving ? styles.checkingReview : styles.checkedReview}>
              <ReviewCard review={ownReview} own canReply={false} pending={saving} />
            </div>
          </div>
        )}

      </div>

      <p className={styles.muted} role="status">
        {note}
      </p>

      {page?.reviews.map((review) => (
        <ReviewCard
          key={review.id}
          review={toCard(review, false)}
          markedHelpful={review.marked_helpful}
          onHelpful={() => toggleHelpful(review)}
          canReply={false}
        />
      ))}

      {page && page.reviews.length === 0 && !ownReview && <p className={styles.muted}>No reviews yet. Be the first to write one.</p>}

      {page?.has_more && (
        <PillButton variant="outline" className={styles.loadMore} onClick={() => void loadMore()} disabled={more.loading}>
          {more.loading ? 'Loading…' : 'Load more reviews'}
        </PillButton>
      )}
      <ConfirmDialog
        open={confirmingDelete}
        title="Delete your review?"
        message="Your review text will be removed. Your star rating stays. This can’t be undone."
        confirmLabel="Delete review"
        busyLabel="Deleting…"
        errorMessage="Couldn’t delete your review. Please try again."
        onConfirm={handleDelete}
        onCancel={() => setConfirmingDelete(false)}
      />
    </section>
  )
}
