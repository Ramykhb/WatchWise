import { useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router'
import { useApi } from '../../lib/api.ts'
import { mutate, queryPath, useStaffPage, type Rating, type RatingFilm, type Burst } from '../../lib/adminTools.ts'
import AdminPagination from '../../components/AdminPagination/AdminPagination.tsx'
import { Page, Field, Select, Person, Metrics, LoadState } from './AdminToolsUI.tsx'
import { useAction, dateText, s } from './adminToolsState.tsx'
export function AdminRatings() {
  const [params] = useSearchParams()
  const [page, setPage] = useState(1)
  const [filters, setFilters] = useState({
    q: '',
    user_id: params.get('user_id') ?? '',
    tmdb_id: '',
    media_type: '',
    rating: '',
  })
  const result = useStaffPage<Rating>('ratings', { ...filters, page })
  const action = useAction()
  const change = (key: keyof typeof filters, value: string) => {
    setFilters((f) => ({ ...f, [key]: value }))
    setPage(1)
  }
  const summary = result.data?.summary as
    | {
        total: number
        average: number | null
        distribution: Record<string, number>
      }
    | undefined
  return (
    <Page title="Star ratings" lead="Inspect ratings without review text. Stars retained after review moderation are protected.">
      <Link to="/admin/ratings/bursts">Inspect rating bursts →</Link>
      <div className={s.bar}>
        <Field label="Member name">
          <input type="search" value={filters.q} onChange={(e) => change('q', e.target.value)} />
        </Field>
        {(['user_id', 'tmdb_id'] as const).map((key) => (
          <Field key={key} label={key === 'user_id' ? 'Member ID' : 'TMDB ID'}>
            <input type="number" min="1" value={filters[key]} onChange={(e) => change(key, e.target.value)} />
          </Field>
        ))}
        <Select label="Media" value={filters.media_type} options={['', 'movie', 'tv']} onChange={(v) => change('media_type', v)} />
        <Select label="Stars" value={filters.rating} options={['', '1', '2', '3', '4', '5']} onChange={(v) => change('rating', v)} />
      </div>
      <LoadState {...result} />
      {action.notice}
      {summary && (
        <>
          <Metrics values={{ total: summary.total, average: summary.average }} />
          <Metrics values={Object.fromEntries(Object.entries(summary.distribution).map(([k, v]) => [`${k} stars`, v]))} />
        </>
      )}
      <section className={s.card}>
        <div className={s.scroll}>
          <table className={s.table}>
            <thead>
              <tr>
                <th>Member</th>
                <th>Film</th>
                <th>Stars</th>
                <th>Created</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              {result.data?.data.map((r) => (
                <tr key={r.id}>
                  <td>
                    <Person person={r.author} />
                  </td>
                  <td>
                    <Link to={`/admin/ratings/films/${r.tmdb_id}?media_type=${r.media_type}`}>
                      {r.film?.title ?? `${r.media_type} #${r.tmdb_id}`}
                    </Link>
                  </td>
                  <td>{r.rating}</td>
                  <td>{dateText(r.created_at)}</td>
                  <td>
                    {r.moderated ? (
                      <Link to={`/admin/reviews/${r.id}`}>Review moderation</Link>
                    ) : (
                      <button
                        disabled={action.busy}
                        className={s.danger}
                        onClick={() => {
                          if (confirm('Permanently delete this star rating?'))
                            void action.run(() => mutate(`ratings/${r.id}`, 'delete'), 'Rating removed.', result.reload)
                        }}
                      >
                        Delete
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {result.data?.data.length === 0 && <p>No matching ratings.</p>}
        {result.data && <AdminPagination meta={result.data.meta} shown={result.data.data.length} onPage={setPage} />}
      </section>
    </Page>
  )
}
export function AdminRatingFilm() {
  const { id } = useParams()
  const [params] = useSearchParams()
  const type = params.get('media_type') === 'tv' ? 'tv' : 'movie'
  const result = useApi<RatingFilm>(queryPath(`/admin/ratings/films/${id}`, { media_type: type }))
  const film = result.data
  return (
    <Page title={film?.film?.title ?? `${type} #${id} ratings`}>
      <Link to="/admin/ratings">← Ratings</Link>
      <LoadState {...result} />
      {film && (
        <>
          <Metrics
            values={{
              total: film.total,
              average: film.average,
              reviews_with_text: film.with_text_count,
            }}
          />
          <section className={s.card}>
            <h2>Star distribution</h2>
            <Metrics values={Object.fromEntries(Object.entries(film.distribution).map(([k, v]) => [`${k} stars`, v]))} />
            <p>First rating: {dateText(film.first_rated_at)}</p>
            <p>Last rating: {dateText(film.last_rated_at)}</p>
          </section>
        </>
      )}
    </Page>
  )
}
export function AdminRatingBursts() {
  const [hours, setHours] = useState('1')
  const [min, setMin] = useState('20')
  const [page, setPage] = useState(1)
  const result = useStaffPage<Burst>('ratings/bursts', { hours, min, page })
  return (
    <Page
      title="Rating bursts"
      lead="Members who submitted many star ratings in a recent window. Inspect their activity before taking action."
    >
      <Link to="/admin/ratings">← Ratings</Link>
      <div className={s.bar}>
        <Field label="Trailing hours (1–24)">
          <input
            type="number"
            min="1"
            max="24"
            value={hours}
            onChange={(e) => {
              setHours(e.target.value)
              setPage(1)
            }}
          />
        </Field>
        <Field label="Minimum ratings (5–500)">
          <input
            type="number"
            min="5"
            max="500"
            value={min}
            onChange={(e) => {
              setMin(e.target.value)
              setPage(1)
            }}
          />
        </Field>
      </div>
      <LoadState {...result} />
      <section className={s.card}>
        {result.data?.data.map((r) => (
          <article key={r.user.id}>
            <h2>
              <Person person={r.user} />
            </h2>
            <p>
              {r.count} ratings · {dateText(r.window_start)} to {dateText(r.window_end)}
            </p>
            <p>Joined {dateText(r.user.created_at)}</p>
            <Link to={`/admin/ratings?user_id=${r.user.id}`}>Inspect ratings</Link>
          </article>
        ))}
        {result.data?.data.length === 0 && <p>No bursts in this window.</p>}
        {result.data && <AdminPagination meta={result.data.meta} shown={result.data.data.length} onPage={setPage} />}
      </section>
    </Page>
  )
}
