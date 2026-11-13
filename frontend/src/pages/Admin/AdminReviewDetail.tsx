import { Link, useNavigate, useParams } from 'react-router'
import { useApi } from '../../lib/api.ts'
import { mutate, type ReviewDetail } from '../../lib/adminTools.ts'
import { Page, Person, Reports, Metrics, LoadState } from './AdminToolsUI.tsx'
import { useAction, dateText, s } from './adminToolsState.tsx'
export default function AdminReviewDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const result = useApi<{ review: ReviewDetail }>(`/admin/reviews/${id}`)
  const r = result.data?.review
  const action = useAction()
  return (
    <Page title={`Review #${id}`}>
      <Link to="/admin/reviews">← Reviews</Link>
      <LoadState {...result} />
      {action.notice}
      {r && (
        <>
          <section className={s.card}>
            <h2>{r.movie.title ?? `${r.media_type} #${r.tmdb_id}`}</h2>
            <p>
              By <Person person={r.author?.id ? { id: r.author.id, name: r.author.name ?? 'Member' } : null} /> · {r.rating} stars ·{' '}
              {r.status} · {dateText(r.created_at)}
            </p>
            <p>{r.content ?? 'The text was removed.'}</p>
            <p>
              Classification: {r.moderation_labels?.join(', ') || 'No labels'} · Sentiment: {r.sentiment ?? 'Unknown'}
            </p>
            <Metrics
              values={{
                helpful_votes: r.helpful_votes,
                author_reviews: r.author.reviews_with_text,
                author_removed_reviews: r.author.removed_reviews,
                open_reports_against_author_reviews: r.author.open_reports_against_their_reviews,
              }}
            />
            <div className={s.bar}>
              {r.status !== 'removed' && (
                <>
                  <button
                    disabled={action.busy}
                    onClick={() =>
                      void action.run(
                        () =>
                          mutate(`reviews/${id}`, 'patch', {
                            status: 'approved',
                          }),
                        'Review approved.',
                        result.reload,
                      )
                    }
                  >
                    Approve
                  </button>
                  <button
                    disabled={action.busy}
                    className={s.danger}
                    onClick={() => {
                      if (confirm('Remove the text of this review? Its star rating stays.'))
                        void action.run(
                          () =>
                            mutate(`reviews/${id}`, 'patch', {
                              status: 'rejected',
                            }),
                          'Review text removed.',
                          result.reload,
                        )
                    }}
                  >
                    Remove text
                  </button>
                  <button
                    disabled={action.busy || !r.content}
                    onClick={() =>
                      void action.run(
                        () =>
                          mutate(`reviews/${id}`, 'patch', {
                            has_spoilers: !r.has_spoilers,
                          }),
                        'Spoiler flag updated.',
                        result.reload,
                      )
                    }
                  >
                    {r.has_spoilers ? 'Clear spoiler flag' : 'Flag spoilers'}
                  </button>
                </>
              )}
              <button
                disabled={action.busy}
                className={s.danger}
                onClick={() => {
                  if (confirm('Permanently delete this review and its star rating?'))
                    void action.run(
                      () => mutate(`reviews/${id}`, 'delete'),
                      'Review deleted.',
                      () => navigate('/admin/reviews'),
                    )
                }}
              >
                Delete review and stars
              </button>
            </div>
          </section>
          <Reports reports={r.reports} />
        </>
      )}
    </Page>
  )
}
