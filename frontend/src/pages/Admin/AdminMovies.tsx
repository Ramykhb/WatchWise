import { useEffect, useMemo, useRef, useState, type ChangeEvent, type FormEvent } from 'react'
import TextField from '../../components/TextField/TextField.tsx'
import Toggle from '../../components/Toggle/Toggle.tsx'
import searchIcon from '../../assets/listdetail/icon-search.svg'
import syncIcon from '../../assets/admin/icon-sync.svg'
import plusIcon from '../../assets/landing/icon-plus-cream.svg'
import dbIcon from '../../assets/admin/icon-db.svg'
import checkIcon from '../../assets/watchlist/icon-check-sm.svg'
import starIcon from '../../assets/auth/icon-star-sm.svg'
import editIcon from '../../assets/movie/icon-edit.svg'
import trashIcon from '../../assets/movie/icon-trash.svg'
import closeIcon from '../../assets/listmodal/icon-x.svg'
import uploadIcon from '../../assets/admin/icon-upload.svg'
import xCreamIcon from '../../assets/admin/icon-x-cream.svg'
import { gradientCss } from '../Home/homeData.ts'
import { ADMIN_MOVIES, MOVIE_GENRES, MOVIE_SYNC, type AdminMovie, type MovieFields } from './adminData.ts'
import styles from './AdminMovies.module.css'

const MAX_POSTER_BYTES = 2 * 1024 * 1024
const NEW_MOVIE_GRADIENT: AdminMovie['gradient'] = ['#ccbfff', '#8c66d9', '#331a66']
const CONTENT_KEYS = ['title', 'releaseDate', 'runtime', 'director', 'genres', 'description'] as const

const EMPTY_FIELDS: MovieFields = {
  title: '',
  releaseDate: '',
  runtime: 0,
  director: '',
  genres: [],
  description: '',
  visible: false,
  featured: false,
}

const formatRuntime = (minutes: number) =>
  minutes > 0 ? `${Math.floor(minutes / 60)}h ${String(minutes % 60).padStart(2, '0')}m` : '—'

const statusOf = (movie: AdminMovie) => (movie.visible ? 'Published' : movie.source === 'Local' ? 'Draft' : 'Hidden')

/** A TMDB movie counts as "Edited" once any of its content differs from TMDB's copy. */
const isOverridden = (fields: MovieFields, tmdb: MovieFields) =>
  CONTENT_KEYS.some((key) => JSON.stringify(fields[key]) !== JSON.stringify(tmdb[key]))

const pickFields = (movie: AdminMovie): MovieFields => ({
  title: movie.title,
  releaseDate: movie.releaseDate,
  runtime: movie.runtime,
  director: movie.director,
  genres: movie.genres,
  description: movie.description,
  visible: movie.visible,
  featured: movie.featured,
})

const SOURCE_CLASS = { TMDB: 'chipViolet', Edited: 'chipYellow', Local: 'chipMint' } as const
const STATUS_CLASS = { Published: 'chipMint', Draft: 'chipCream', Hidden: 'chipCoral' } as const

type EditorProps = {
  movie: AdminMovie | null
  onSave: (fields: MovieFields, poster: string | undefined) => void
  onClose: () => void
  /** Focus the form and scroll to it (not when the page first loads with it open). */
  focusOnOpen: boolean
}

function MovieEditor({ movie, onSave, onClose, focusOnOpen }: EditorProps) {
  const [fields, setFields] = useState<MovieFields>(movie ? pickFields(movie) : EMPTY_FIELDS)
  const [poster, setPoster] = useState(movie?.poster)
  const [error, setError] = useState('')
  const [note, setNote] = useState('')
  const fileInput = useRef<HTMLInputElement>(null)
  const panel = useRef<HTMLFormElement>(null)

  useEffect(() => {
    if (!focusOnOpen) return
    // On narrow screens the panel sits above the table, so bring it into view.
    panel.current?.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
    panel.current?.querySelector('input')?.focus({ preventScroll: true })
  }, [focusOnOpen])

  const set = <K extends keyof MovieFields>(key: K, value: MovieFields[K]) => setFields((f) => ({ ...f, [key]: value }))
  const remaining = MOVIE_GENRES.filter((g) => !fields.genres.includes(g))

  const choosePoster = (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return
    if (!['image/jpeg', 'image/png'].includes(file.type)) return setError('The poster has to be a JPG or PNG.')
    if (file.size > MAX_POSTER_BYTES) return setError('The poster has to be 2 MB or smaller.')
    setError('')
    setPoster(URL.createObjectURL(file))
  }

  const resetToTmdb = () => {
    if (!movie?.tmdb) return
    setFields((f) => ({ ...f, ...Object.fromEntries(CONTENT_KEYS.map((key) => [key, movie.tmdb![key]])) }))
    setNote('Fields reset to the TMDB values. Save to apply.')
  }

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!fields.title.trim()) return setError('Give the movie a title.')
    if (fields.releaseDate && !/^\d{4}-\d{2}-\d{2}$/.test(fields.releaseDate)) return setError('Write the release date as YYYY-MM-DD.')
    setError('')
    onSave({ ...fields, title: fields.title.trim(), director: fields.director.trim() }, poster)
  }

  const chip = !movie
    ? 'New local movie'
    : movie.tmdbId
      ? movie.source === 'Edited'
        ? `Local override of TMDB #${movie.tmdbId}`
        : `Cached from TMDB #${movie.tmdbId}`
      : 'Local movie'

  return (
    <form ref={panel} className={styles.editor} onSubmit={submit} aria-labelledby="editor-title" noValidate>
      <div className={styles.editorHead}>
        <div className={styles.editorTitleBlock}>
          <h2 id="editor-title" className={styles.editorTitle}>
            {movie ? 'Edit movie' : 'Add movie'}
          </h2>
          <span className={movie?.source === 'TMDB' ? styles.chipViolet : styles.chipYellow}>{chip}</span>
        </div>
        <button type="button" className={styles.close} onClick={onClose} aria-label="Close editor">
          <img src={closeIcon} width={15} height={15} alt="" />
        </button>
      </div>

      <div className={styles.posterRow}>
        {poster ? (
          <img className={styles.poster} src={poster} alt={`${fields.title || 'Movie'} poster`} />
        ) : (
          <span className={styles.poster} style={{ background: gradientCss(movie?.gradient ?? NEW_MOVIE_GRADIENT) }} />
        )}
        <div className={styles.upload}>
          <button type="button" className={styles.replace} onClick={() => fileInput.current?.click()}>
            <img src={uploadIcon} width={14} height={14} alt="" />
            {poster ? 'Replace poster' : movie ? 'Replace poster' : 'Upload poster'}
          </button>
          <input ref={fileInput} type="file" accept="image/jpeg,image/png" hidden onChange={choosePoster} />
          <p className={styles.uploadHint}>JPG or PNG, 2:3, max 2 MB</p>
        </div>
      </div>

      <TextField label="Title" value={fields.title} onChange={(e) => set('title', e.target.value)} required />
      <div className={styles.pair}>
        <TextField
          label="Release date"
          value={fields.releaseDate}
          onChange={(e) => set('releaseDate', e.target.value)}
          placeholder="YYYY-MM-DD"
          inputMode="numeric"
        />
        <TextField
          label="Runtime (min)"
          type="number"
          min={1}
          inputMode="numeric"
          value={fields.runtime || ''}
          onChange={(e) => set('runtime', Math.max(0, Number(e.target.value)))}
        />
      </div>
      <TextField label="Director" value={fields.director} onChange={(e) => set('director', e.target.value)} />

      <fieldset className={styles.genres}>
        <legend className={styles.fieldLabel}>Genres</legend>
        <div className={styles.genreChips}>
          {fields.genres.map((genre) => (
            <button
              key={genre}
              type="button"
              className={styles.genreChip}
              onClick={() => set('genres', fields.genres.filter((g) => g !== genre))}
              aria-label={`Remove ${genre}`}
            >
              <img src={xCreamIcon} width={13} height={13} alt="" />
              {genre}
            </button>
          ))}
          {remaining.length > 0 && (
            <label className={styles.addGenre}>
              <span aria-hidden="true">+ Add</span>
              <select
                value=""
                aria-label="Add a genre"
                onChange={(e) => e.target.value && set('genres', [...fields.genres, e.target.value])}
              >
                <option value="" disabled>
                  Add a genre
                </option>
                {remaining.map((g) => (
                  <option key={g}>{g}</option>
                ))}
              </select>
            </label>
          )}
        </div>
      </fieldset>

      <label className={styles.description}>
        <span className={styles.fieldLabel}>Description</span>
        <textarea rows={3} value={fields.description} onChange={(e) => set('description', e.target.value)} />
      </label>

      <div className={styles.toggleRow}>
        <span id="featured-label" className={styles.fieldLabel}>
          Featured on Home
        </span>
        <Toggle className={styles.toggle} checked={fields.featured} onChange={(v) => set('featured', v)} labelledBy="featured-label" />
      </div>
      <div className={styles.toggleRow}>
        <span id="visible-label" className={styles.fieldLabel}>
          Visible to users
        </span>
        <Toggle className={styles.toggle} checked={fields.visible} onChange={(v) => set('visible', v)} labelledBy="visible-label" />
      </div>

      {(error || note) && (
        <p className={error ? styles.formError : styles.formNote} role={error ? 'alert' : 'status'}>
          {error || note}
        </p>
      )}

      <div className={styles.editorFooter}>
        {movie?.tmdb && (
          <button type="button" className={styles.outline} onClick={resetToTmdb}>
            Reset to TMDB
          </button>
        )}
        <button type="submit" className={styles.solid}>
          {movie ? 'Save changes' : 'Add movie'}
        </button>
      </div>
    </form>
  )
}

export default function AdminMovies() {
  // TODO: load the catalogue and send edits, deletes and syncs to the admin API.
  const [movies, setMovies] = useState(ADMIN_MOVIES)
  const [query, setQuery] = useState('')
  // The Figma frame opens on Neon Tide; `'new'` is the Add movie form, null hides the panel.
  const [editing, setEditing] = useState<string | 'new' | null>(ADMIN_MOVIES[0].id)
  const [syncing, setSyncing] = useState(false)
  const [lastSynced, setLastSynced] = useState(MOVIE_SYNC.lastSynced)
  const [note, setNote] = useState('')
  const [userOpened, setUserOpened] = useState(false)

  const open = (id: string | 'new') => {
    setUserOpened(true)
    setEditing(id)
  }

  const editingMovie = movies.find((m) => m.id === editing) ?? null
  const overrides =
    MOVIE_SYNC.overrides +
    movies.filter((m) => m.source === 'Edited').length -
    ADMIN_MOVIES.filter((m) => m.source === 'Edited').length

  const visible = useMemo(() => {
    const q = query.trim().toLowerCase()
    return q ? movies.filter((m) => [m.title, m.director].some((field) => field.toLowerCase().includes(q))) : movies
  }, [movies, query])

  const sync = () => {
    setSyncing(true)
    setNote('')
    window.setTimeout(() => {
      setSyncing(false)
      setLastSynced('just now')
      setNote('Synced with TMDB. Your local overrides were kept.')
    }, 1500)
  }

  const save = (fields: MovieFields, poster: string | undefined) => {
    if (editing === 'new') {
      const movie: AdminMovie = { ...fields, id: `local-${Date.now()}`, source: 'Local', rating: null, gradient: NEW_MOVIE_GRADIENT, poster }
      setMovies((list) => [movie, ...list])
      setEditing(movie.id)
      setNote(`${fields.title} was added as a local movie.`)
      return
    }
    setMovies((list) =>
      list.map((m) =>
        m.id === editing
          ? { ...m, ...fields, poster, source: m.tmdb ? (isOverridden(fields, m.tmdb) ? 'Edited' : 'TMDB') : 'Local' }
          : m,
      ),
    )
    setNote(`Changes to ${fields.title} saved.`)
  }

  const remove = (movie: AdminMovie) => {
    const message = movie.tmdbId
      ? `Remove ${movie.title} from the local catalogue? It comes back from TMDB on the next sync, without your overrides.`
      : `Delete ${movie.title}? This local movie can't be restored.`
    if (!window.confirm(message)) return
    setMovies((list) => list.filter((m) => m.id !== movie.id))
    if (editing === movie.id) setEditing(null)
    setNote(`${movie.title} was removed.`)
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
              placeholder="Search title, director…"
              aria-label="Search by title or director"
            />
          </label>
          <button type="button" className={styles.syncButton} onClick={sync} disabled={syncing}>
            <img className={syncing ? styles.spin : undefined} src={syncIcon} width={16} height={16} alt="" />
            {syncing ? 'Syncing…' : 'Sync from TMDB'}
          </button>
          <button type="button" className={styles.add} onClick={() => open('new')}>
            <img src={plusIcon} width={16} height={16} alt="" />
            Add movie
          </button>
        </div>
      </header>

      <div className={styles.syncStatus}>
        <img src={dbIcon} width={18} height={18} alt="" />
        <p className={styles.syncText}>
          Last synced {lastSynced} · {MOVIE_SYNC.cached.toLocaleString('en-US')} movies cached · {overrides} local overrides · next
          sync in {MOVIE_SYNC.nextSync}
        </p>
        <span className={styles.chipWhite}>
          <img src={checkIcon} width={13} height={13} alt="" />
          TMDB API connected
        </span>
      </div>

      {note && (
        <p className={styles.note} role="status">
          {note}
        </p>
      )}

      <div className={styles.body}>
        <section className={styles.tableCard} aria-label="Movies">
          <div className={styles.scroll}>
            <table className={styles.table}>
              <colgroup>
                <col />
                <col className={styles.colSource} />
                <col className={styles.colRating} />
                <col className={styles.colRuntime} />
                <col className={styles.colStatus} />
                <col className={styles.colActions} />
              </colgroup>
              <thead>
                <tr>
                  <th scope="col">MOVIE</th>
                  <th scope="col">SOURCE</th>
                  <th scope="col">RATING</th>
                  <th scope="col">RUNTIME</th>
                  <th scope="col">STATUS</th>
                  <th scope="col">ACTIONS</th>
                </tr>
              </thead>
              <tbody>
                {visible.length === 0 && (
                  <tr>
                    <td colSpan={6} className={styles.empty}>
                      No movies match “{query.trim()}”.
                    </td>
                  </tr>
                )}
                {visible.map((movie) => {
                  const status = statusOf(movie)
                  const year = movie.releaseDate.slice(0, 4)
                  return (
                    <tr key={movie.id} className={movie.id === editing ? styles.rowSelected : undefined}>
                      <td>
                        <span className={styles.movie}>
                          {movie.poster ? (
                            <img className={styles.thumb} src={movie.poster} alt="" />
                          ) : (
                            <span className={styles.thumb} style={{ background: gradientCss(movie.gradient) }} />
                          )}
                          <span className={styles.movieText}>
                            <span className={styles.movieTitle}>{movie.title}</span>
                            <span className={styles.movieMeta}>{[year, movie.genres.join(', ')].filter(Boolean).join(' · ')}</span>
                          </span>
                        </span>
                      </td>
                      <td>
                        <span className={styles[SOURCE_CLASS[movie.source]]}>{movie.source}</span>
                      </td>
                      <td>
                        {movie.rating === null ? (
                          <span className={styles.muted}>—</span>
                        ) : (
                          <span className={styles.score}>
                            <img src={starIcon} width={13} height={13} alt="" />
                            <span className="sr-only">Rated </span>
                            {movie.rating.toFixed(1)}
                          </span>
                        )}
                      </td>
                      <td className={styles.muted}>{formatRuntime(movie.runtime)}</td>
                      <td>
                        <span className={styles[STATUS_CLASS[status]]}>{status}</span>
                      </td>
                      <td>
                        <span className={styles.actions}>
                          <button
                            type="button"
                            className={styles.actionCream}
                            onClick={() => open(movie.id)}
                            aria-label={`Edit ${movie.title}`}
                            aria-pressed={movie.id === editing}
                          >
                            <img src={editIcon} width={14} height={14} alt="" />
                          </button>
                          <button
                            type="button"
                            className={styles.actionCoral}
                            onClick={() => remove(movie)}
                            aria-label={`Remove ${movie.title}`}
                          >
                            <img src={trashIcon} width={14} height={14} alt="" />
                          </button>
                        </span>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        </section>

        {editing && (
          <MovieEditor
            key={editing}
            movie={editing === 'new' ? null : editingMovie}
            onSave={save}
            onClose={() => setEditing(null)}
            focusOnOpen={userOpened}
          />
        )}
      </div>
    </div>
  )
}
