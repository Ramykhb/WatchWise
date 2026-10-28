import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react'
import AdminPagination from '../../components/AdminPagination/AdminPagination.tsx'
import TextField from '../../components/TextField/TextField.tsx'
import Toggle from '../../components/Toggle/Toggle.tsx'
import searchIcon from '../../assets/listdetail/icon-search.svg'
import dbIcon from '../../assets/admin/icon-db.svg'
import editIcon from '../../assets/movie/icon-edit.svg'
import closeIcon from '../../assets/listmodal/icon-x.svg'
import xCreamIcon from '../../assets/admin/icon-x-cream.svg'
import {
  adminApi,
  adminPosterUrl,
  useAdminMovies,
  useMovieGenres,
  type AdminMovie,
  type AdminMovieChanges,
  type AdminMovieSync,
} from '../../lib/adminApi.ts'
import { apiErrorMessage } from '../../lib/auth.ts'
import { timeAgo } from '../../lib/format.ts'
import styles from './AdminMovies.module.css'

const PER_PAGE = 20
const POSTER_BACKDROP = 'var(--surface-violet-tint)'

const formatRuntime = (minutes: number | null) =>
  minutes && minutes > 0 ? `${Math.floor(minutes / 60)}h ${String(minutes % 60).padStart(2, '0')}m` : '—'

const statusOf = (movie: AdminMovie) => (movie.is_hidden ? 'Hidden' : 'Published')
const STATUS_CLASS = { Published: 'chipMint', Hidden: 'chipCoral' } as const

type EditorProps = {
  movie: AdminMovie
  genreNames: Map<number, string>
  onSave: (changes: AdminMovieChanges) => Promise<string | null>
  onClose: () => void
  /** Focus the form and scroll to it (not when the page first loads with it open). */
  focusOnOpen: boolean
}

function MovieEditor({ movie, genreNames, onSave, onClose, focusOnOpen }: EditorProps) {
  const [title, setTitle] = useState(movie.title)
  const [overview, setOverview] = useState(movie.overview ?? '')
  const [director, setDirector] = useState(movie.director ?? '')
  const [runtime, setRuntime] = useState(movie.runtime ? String(movie.runtime) : '')
  const [releaseDate, setReleaseDate] = useState(movie.release_date ?? '')
  const [genreIds, setGenreIds] = useState(movie.genre_ids)
  const [visible, setVisible] = useState(!movie.is_hidden)
  const [featured, setFeatured] = useState(movie.is_featured)
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)
  const panel = useRef<HTMLFormElement>(null)
  const poster = adminPosterUrl(movie.poster_path)
  const remaining = [...genreNames].filter(([id]) => !genreIds.includes(id) && genreNames.size > 0)

  useEffect(() => {
    if (!focusOnOpen) return
    // On narrow screens the panel sits above the table, so bring it into view.
    panel.current?.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
    panel.current?.querySelector('input')?.focus({ preventScroll: true })
  }, [focusOnOpen])

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const trimmed = title.trim()
    if (!trimmed) return setError('Give the movie a title.')
    const runtimeValue = runtime.trim() === '' ? null : Number(runtime)
    if (runtimeValue !== null && (!Number.isInteger(runtimeValue) || runtimeValue < 1 || runtimeValue > 1000)) {
      return setError('Runtime has to be a whole number of minutes between 1 and 1000.')
    }
    if (releaseDate.trim() !== '' && !/^\d{4}-\d{2}-\d{2}$/.test(releaseDate.trim())) return setError('Write the release date as YYYY-MM-DD.')
    const changes: AdminMovieChanges = {}
    if (trimmed !== movie.title) changes.title = trimmed
    if (overview.trim() !== (movie.overview ?? '')) changes.overview = overview.trim() === '' ? null : overview.trim()
    if (director.trim() !== (movie.director ?? '')) changes.director = director.trim() === '' ? null : director.trim()
    if (runtimeValue !== movie.runtime) changes.runtime = runtimeValue
    if (releaseDate.trim() !== (movie.release_date ?? '')) changes.release_date = releaseDate.trim() === '' ? null : releaseDate.trim()
    if (genreIds.length !== movie.genre_ids.length || genreIds.some((id, index) => id !== movie.genre_ids[index])) changes.genre_ids = genreIds
    if (!visible !== movie.is_hidden) changes.is_hidden = !visible
    if (featured !== movie.is_featured) changes.is_featured = featured
    if (Object.keys(changes).length === 0) return setError('Nothing changed yet.')
    setError('')
    setSaving(true)
    const failure = await onSave(changes)
    setSaving(false)
    if (failure) setError(failure)
  }

  return (
    <form ref={panel} className={styles.editor} onSubmit={(event) => void submit(event)} aria-labelledby="editor-title" noValidate>
      <div className={styles.editorHead}>
        <div className={styles.editorTitleBlock}>
          <h2 id="editor-title" className={styles.editorTitle}>
            Edit movie
          </h2>
          <span className={styles.chipViolet}>TMDB #{movie.tmdb_id}</span>
        </div>
        <button type="button" className={styles.close} onClick={onClose} aria-label="Close editor">
          <img src={closeIcon} width={15} height={15} alt="" />
        </button>
      </div>

      <div className={styles.posterRow}>
        {poster ? (
          <img className={styles.poster} src={poster} alt={`${movie.title} poster`} />
        ) : (
          <span className={styles.poster} style={{ background: POSTER_BACKDROP }} />
        )}
      </div>

      <TextField label="Title" value={title} onChange={(e) => setTitle(e.target.value)} required />
      <div className={styles.pair}>
        <TextField
          label="Release date"
          value={releaseDate}
          onChange={(e) => setReleaseDate(e.target.value)}
          placeholder="YYYY-MM-DD"
          inputMode="numeric"
        />
        <TextField
          label="Runtime (min)"
          type="number"
          min={1}
          max={1000}
          inputMode="numeric"
          value={runtime}
          onChange={(e) => setRuntime(e.target.value)}
        />
      </div>
      <TextField label="Director" value={director} onChange={(e) => setDirector(e.target.value)} />

      <fieldset className={styles.genres}>
        <legend className={styles.fieldLabel}>Genres</legend>
        <div className={styles.genreChips}>
          {genreIds.map((id) => (
            <button
              key={id}
              type="button"
              className={styles.genreChip}
              onClick={() => setGenreIds(genreIds.filter((g) => g !== id))}
              aria-label={`Remove ${genreNames.get(id) ?? `genre ${id}`}`}
            >
              <img src={xCreamIcon} width={13} height={13} alt="" />
              {genreNames.get(id) ?? `Genre ${id}`}
            </button>
          ))}
          {remaining.length > 0 && (
            <label className={styles.addGenre}>
              <span aria-hidden="true">+ Add</span>
              <select
                value=""
                aria-label="Add a genre"
                onChange={(e) => e.target.value && setGenreIds([...genreIds, Number(e.target.value)])}
              >
                <option value="" disabled>
                  Add a genre
                </option>
                {remaining.map(([id, name]) => (
                  <option key={id} value={id}>
                    {name}
                  </option>
                ))}
              </select>
            </label>
          )}
        </div>
      </fieldset>

      <label className={styles.description}>
        <span className={styles.fieldLabel}>Description</span>
        <textarea rows={4} value={overview} onChange={(e) => setOverview(e.target.value)} maxLength={5000} />
      </label>

      <div className={styles.toggleRow}>
        <span id="featured-label" className={styles.fieldLabel}>
          Featured on Home
        </span>
        <Toggle className={styles.toggle} checked={featured} onChange={setFeatured} labelledBy="featured-label" />
      </div>
      <div className={styles.toggleRow}>
        <span id="visible-label" className={styles.fieldLabel}>
          Visible to users
        </span>
        <Toggle className={styles.toggle} checked={visible} onChange={setVisible} labelledBy="visible-label" />
      </div>

      {error && (
        <p className={styles.formError} role="alert">
          {error}
        </p>
      )}

      <div className={styles.editorFooter}>
        <button type="submit" className={styles.solid} disabled={saving}>
          {saving ? 'Saving…' : 'Save changes'}
        </button>
      </div>
    </form>
  )
}

function SyncSummary({ sync }: { sync: AdminMovieSync }) {
  return (
    <div className={styles.syncStatus}>
      <img src={dbIcon} width={18} height={18} alt="" />
      <p className={styles.syncText}>
        Last synced {sync.last_synced_at ? timeAgo(sync.last_synced_at) : 'never'} · {sync.cached.toLocaleString('en-US')} movies cached ·{' '}
        {sync.overrides} local overrides
      </p>
    </div>
  )
}

export default function AdminMovies() {
  const [query, setQuery] = useState('')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [editing, setEditing] = useState<number | null>(null)
  const [note, setNote] = useState('')

  // Search the API once typing pauses.
  useEffect(() => {
    const timer = window.setTimeout(() => {
      setSearch(query.trim())
      setPage(1)
    }, 300)
    return () => window.clearTimeout(timer)
  }, [query])

  const { data, error, loading, setData, reload } = useAdminMovies({ q: search, page, per_page: PER_PAGE })
  const { data: genreData } = useMovieGenres()
  const genreNames = useMemo(() => new Map((genreData?.genres ?? []).map((g) => [g.id, g.name])), [genreData])
  const movies = useMemo(() => data?.data ?? [], [data])
  const editingMovie = movies.find((m) => m.tmdb_id === editing) ?? null

  const save = async (changes: AdminMovieChanges): Promise<string | null> => {
    if (!editingMovie) return null
    try {
      const updated = await adminApi.updateMovie(editingMovie.tmdb_id, changes)
      setData((current) => ({ ...current, data: current.data.map((m) => (m.tmdb_id === updated.tmdb_id ? { ...m, ...updated } : m)) }))
      setNote(`Changes to ${updated.title} saved.`)
      return null
    } catch (saveError) {
      return apiErrorMessage(saveError, 'Unable to save this movie. Please try again.')
    }
  }

  return (
    <div className={styles.page}>
      <header className={styles.topBar}>
        <div className={styles.titleBlock}>
          <h1 className={styles.title}>Movies</h1>
          <p className={styles.lead}>Local movie data cached from TMDB, plus your own overrides.</p>
        </div>
        <div className={styles.controls}>
          <label className={styles.search}>
            <img src={searchIcon} width={16} height={16} alt="" />
            <input
              type="search"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Search title…"
              aria-label="Search by title"
            />
          </label>
        </div>
      </header>

      {data && <SyncSummary sync={data.summary.sync} />}

      {note && (
        <p className={styles.note} role="status">
          {note}
        </p>
      )}

      <div className={styles.body}>
        <section className={styles.tableCard} aria-label="Movies" aria-busy={loading}>
          <div className={styles.scroll}>
            <table className={styles.table}>
              <colgroup>
                <col />
                <col className={styles.colRuntime} />
                <col className={styles.colStatus} />
                <col className={styles.colActions} />
              </colgroup>
              <thead>
                <tr>
                  <th scope="col">MOVIE</th>
                  <th scope="col">RUNTIME</th>
                  <th scope="col">STATUS</th>
                  <th scope="col">ACTIONS</th>
                </tr>
              </thead>
              <tbody>
                {!data && (
                  <tr>
                    <td colSpan={4} className={styles.empty} role={error ? 'alert' : 'status'}>
                      {error ? (
                        <>
                          {apiErrorMessage(error, 'Unable to load movies.')}{' '}
                          <button type="button" className={styles.outline} onClick={reload}>
                            Try again
                          </button>
                        </>
                      ) : (
                        'Loading movies…'
                      )}
                    </td>
                  </tr>
                )}
                {data && movies.length === 0 && (
                  <tr>
                    <td colSpan={4} className={styles.empty}>
                      {search ? `No movies match “${search}”.` : 'No movies in the local catalogue yet.'}
                    </td>
                  </tr>
                )}
                {movies.map((movie) => {
                  const status = statusOf(movie)
                  const poster = adminPosterUrl(movie.poster_path)
                  const meta = [movie.release_date?.slice(0, 4), movie.genre_ids.map((id) => genreNames.get(id)).filter(Boolean).join(', ')].filter(Boolean).join(' · ')
                  return (
                    <tr key={movie.tmdb_id} className={movie.tmdb_id === editing ? styles.rowSelected : undefined}>
                      <td>
                        <span className={styles.movie}>
                          {poster ? (
                            <img className={styles.thumb} src={poster} alt="" />
                          ) : (
                            <span className={styles.thumb} style={{ background: POSTER_BACKDROP }} />
                          )}
                          <span className={styles.movieText}>
                            <span className={styles.movieTitle}>{movie.title}</span>
                            {meta && <span className={styles.movieMeta}>{meta}</span>}
                          </span>
                        </span>
                      </td>
                      <td className={styles.muted}>{formatRuntime(movie.runtime)}</td>
                      <td>
                        <span className={styles[STATUS_CLASS[status]]}>{status}</span>
                        {movie.is_edited && <span className={styles.chipYellow}>Edited</span>}
                        {movie.is_featured && <span className={styles.chipYellow}>Featured</span>}
                      </td>
                      <td>
                        <span className={styles.actions}>
                          <button
                            type="button"
                            className={styles.actionCream}
                            onClick={() => setEditing(movie.tmdb_id)}
                            aria-label={`Edit ${movie.title}`}
                            aria-pressed={movie.tmdb_id === editing}
                          >
                            <img src={editIcon} width={14} height={14} alt="" />
                          </button>
                        </span>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
          {data && (
            <AdminPagination
              meta={data.meta}
              shown={movies.length}
              onPage={(next) => {
                setPage(next)
                setEditing(null)
              }}
            />
          )}
        </section>

        {editingMovie && <MovieEditor key={editingMovie.tmdb_id} movie={editingMovie} genreNames={genreNames} onSave={save} onClose={() => setEditing(null)} focusOnOpen />}
      </div>
    </div>
  )
}
