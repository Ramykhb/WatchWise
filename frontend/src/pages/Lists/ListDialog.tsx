import { useEffect, useId, useRef, useState, type FormEvent, type KeyboardEvent } from 'react'
import { useNavigate, useParams } from 'react-router'
import ButtonPrimary from '../../components/ButtonPrimary/ButtonPrimary.tsx'
import PillButton from '../../components/PillButton/PillButton.tsx'
import TextField from '../../components/TextField/TextField.tsx'
import bgBlob from '../../assets/listmodal/bg-blob.svg'
import bgBlob2 from '../../assets/listmodal/bg-blob-2.svg'
import xIcon from '../../assets/listmodal/icon-x.svg'
import xSmIcon from '../../assets/listmodal/icon-x-sm.svg'
import globeIcon from '../../assets/listmodal/icon-globe.svg'
import lockIcon from '../../assets/listmodal/icon-lock.svg'
import searchIcon from '../../assets/home/icon-search.svg'
import type { Movie } from '../../components/MovieTile/MovieTile.tsx'
import { gradientCss } from '../Home/homeData.ts'
import { apiErrorMessage } from '../../lib/auth.ts'
import { api, useApi } from '../../lib/api.ts'
import { isLoading, posterAt, rememberMovie, useMovies } from '../../lib/movieDetails.ts'
import { movieFor, tmdbIdOf } from '../../lib/movies.ts'
import type { ApiList } from '../../lib/types.ts'
import { loadGenres, toTileMovie, type MoviePage } from '../Discover/discoverApi.ts'
import { listSlug } from '../Profile/profileData.ts'
import { listIdFrom } from './listsData.ts'
import { createList, updateList } from './myListsStore.ts'
import styles from './ListDialog.module.css'

const CLOSE_MS = 220
/** Wait this long after the last keystroke before searching, so typing doesn't use up the TMDB rate limit. */
const SEARCH_DELAY_MS = 250
/** One page of TMDB results; the dropdown shows three at a time and scrolls for the rest. */
const MAX_RESULTS = 20
/** Used when the genre names can't be loaded: the results then only show the year. */
const NO_GENRES = new Map<number, string>()

/** Answered searches (lower-cased query → films), so going back to an earlier query is instant. */
const searchCache = new Map<string, Movie[]>()

type SearchResult = { query: string; movies: Movie[]; failed: boolean }

type ListDialogProps = { mode: 'create' | 'edit' }

/** "Create a list" / "Edit list" dialog over the Lists page (Figma "Modal — Create / Edit List"). */
export default function ListDialog({ mode }: ListDialogProps) {
  return mode === 'edit' ? <EditDialog /> : <Dialog key="new" />
}

/** Loads the list (with its films) before opening the dialog. */
function EditDialog() {
  const { slug } = useParams()
  const navigate = useNavigate()
  const { data, error } = useApi<{ list: ApiList }>(`/lists/${listIdFrom(slug)}`)

  // An unknown list, or someone else's, can't be edited; go back to Lists.
  useEffect(() => {
    if (error) navigate('/lists', { replace: true })
  }, [error, navigate])

  if (!data) return null
  return <Dialog key={data.list.id} existing={data.list} />
}

function Dialog({ existing }: { existing?: ApiList }) {
  const navigate = useNavigate()
  const titleId = useId()
  const dialogRef = useRef<HTMLDivElement>(null)
  const isEdit = Boolean(existing)

  const [title, setTitle] = useState(existing?.title ?? '')
  const [description, setDescription] = useState(existing?.description ?? '')
  const [isPublic, setIsPublic] = useState(existing?.is_public ?? true)
  const [picked, setPicked] = useState<Movie[]>(() => existing?.items?.map((item) => movieFor(item.tmdb_id)) ?? [])
  // The list's saved films arrive as ids only; load their real titles and posters.
  const [savedIds] = useState(() => existing?.items?.map((item) => item.tmdb_id) ?? [])
  const movieOf = useMovies(savedIds)
  const [query, setQuery] = useState('')
  const [titleError, setTitleError] = useState('')
  const [formError, setFormError] = useState('')
  const [saving, setSaving] = useState(false)
  const [closing, setClosing] = useState(false)

  const [found, setFound] = useState<SearchResult>({ query: '', movies: [], failed: false })

  const q = query.trim()
  const key = q.toLowerCase()
  const answered = key ? searchCache.get(key) : undefined
  const failed = found.query === key && found.failed
  const searching = key !== '' && !answered && !failed
  // While a new search runs, the last answer stays on screen (dimmed) instead of the list going blank.
  const results = (answered ?? (key ? found.movies : []))
    .filter((movie) => !picked.some((p) => p.slug === movie.slug))
    .slice(0, MAX_RESULTS)

  // Searches TMDB movies (not TV: lists store movie ids) once typing pauses; a newer query cancels the older one.
  useEffect(() => {
    if (!key || searchCache.has(key)) return
    const controller = new AbortController()
    const timer = window.setTimeout(() => {
      Promise.all([
        api.get<MoviePage>('/movies/search', { params: { q: key, type: 'movie' }, signal: controller.signal }),
        loadGenres().catch(() => NO_GENRES),
      ])
        .then(([{ data }, genres]) => {
          const movies = data.movies.map((movie) => toTileMovie(movie, genres))
          searchCache.set(key, movies)
          setFound({ query: key, movies, failed: false })
        })
        .catch(() => {
          if (!controller.signal.aborted) setFound({ query: key, movies: [], failed: true })
        })
    }, SEARCH_DELAY_MS)
    return () => {
      window.clearTimeout(timer)
      controller.abort()
    }
  }, [key])

  const close = (to = '/lists') => {
    if (closing) return
    setClosing(true)
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    window.setTimeout(() => navigate(to), reduced ? 0 : CLOSE_MS)
  }

  // Focus the first field, lock page scroll and restore focus on close.
  useEffect(() => {
    const previous = document.activeElement as HTMLElement | null
    dialogRef.current?.querySelector<HTMLInputElement>('input')?.focus()
    const { overflow } = document.body.style
    document.body.style.overflow = 'hidden'
    return () => {
      document.body.style.overflow = overflow
      previous?.focus?.()
    }
  }, [])

  // Escape closes; Tab stays inside the dialog.
  const handleKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (event.key === 'Escape') {
      event.stopPropagation()
      close()
      return
    }
    if (event.key !== 'Tab' || !dialogRef.current) return
    const focusable = [
      ...dialogRef.current.querySelectorAll<HTMLElement>('a[href], button:not([disabled]), input, textarea, [tabindex="0"]'),
    ]
    const first = focusable[0]
    const last = focusable.at(-1)
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault()
      last?.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault()
      first?.focus()
    }
  }

  const pick = (movie: Movie) => {
    // The search already has the film's title and poster, so the saved list can show it without loading.
    rememberMovie(movie)
    setPicked((list) => [...list, movie])
    setQuery('')
  }

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const cleanTitle = title.trim()
    if (!cleanTitle) {
      setTitleError('Give your list a title.')
      dialogRef.current?.querySelector<HTMLInputElement>('input')?.focus()
      return
    }
    const input = { title: cleanTitle, description: description.trim(), is_public: isPublic, tmdb_ids: picked.map(tmdbIdOf) }
    setSaving(true)
    setFormError('')
    try {
      const saved = existing ? await updateList(existing.id, input) : await createList(input)
      close(isEdit ? '/lists' : `/lists/${listSlug(saved)}`)
    } catch (error) {
      setFormError(apiErrorMessage(error, "Couldn't save the list. Please try again."))
      setSaving(false)
    }
  }

  return (
    <div
      className={closing ? styles.backdropClosing : styles.backdrop}
      onMouseDown={(event) => event.target === event.currentTarget && close()}
      onKeyDown={handleKeyDown}
      data-lenis-prevent
    >
      <div className={styles.blobs} aria-hidden="true">
        <img className={styles.blob} src={bgBlob} width={700} height={602.524} alt="" />
        <img className={styles.blob2} src={bgBlob2} width={500} height={420.842} alt="" />
      </div>

      <div ref={dialogRef} className={styles.dialog} role="dialog" aria-modal="true" aria-labelledby={titleId}>
        <form className={styles.form} onSubmit={handleSubmit} noValidate>
          <div className={styles.head}>
            <h2 id={titleId} className={styles.title}>
              {isEdit ? 'Edit list' : 'Create a list'}
            </h2>
            <button type="button" className={styles.close} aria-label="Close" onClick={() => close()}>
              <img src={xIcon} width={18} height={18} alt="" />
            </button>
          </div>

          <TextField
            label="Title"
            value={title}
            onChange={(event) => {
              setTitle(event.target.value)
              if (titleError) setTitleError('')
            }}
            placeholder="Movies to Watch This Summer"
            maxLength={80}
            error={titleError}
          />

          <label className={styles.field}>
            <span className={styles.label}>Description</span>
            <textarea
              className={styles.textarea}
              value={description}
              onChange={(event) => setDescription(event.target.value)}
              placeholder="Big, bright adventures for long warm nights."
              maxLength={280}
            />
          </label>

          <div className={styles.field}>
            <span className={styles.label} id={`${titleId}-visibility`}>
              Who can see this list?
            </span>
            <div className={styles.segmented} role="radiogroup" aria-labelledby={`${titleId}-visibility`}>
              <button
                type="button"
                role="radio"
                aria-checked={isPublic}
                className={isPublic ? styles.segmentOn : styles.segment}
                onClick={() => setIsPublic(true)}
              >
                <img className={isPublic ? undefined : styles.darkIcon} src={globeIcon} width={16} height={16} alt="" />
                Public — anyone can view
              </button>
              <button
                type="button"
                role="radio"
                aria-checked={!isPublic}
                className={!isPublic ? styles.segmentOn : styles.segment}
                onClick={() => setIsPublic(false)}
              >
                <img className={isPublic ? undefined : styles.lightIcon} src={lockIcon} width={16} height={16} alt="" />
                Private — only you
              </button>
            </div>
          </div>

          <div className={styles.field}>
            <label className={styles.label} htmlFor={`${titleId}-search`}>
              Add movies
            </label>
            <div className={styles.searchWrap}>
              <div className={styles.search}>
                <img src={searchIcon} width={18} height={18} alt="" />
                <input
                  id={`${titleId}-search`}
                  type="search"
                  value={query}
                  onChange={(event) => setQuery(event.target.value)}
                  onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                      event.preventDefault()
                      // Results from an older query may still be on screen; only pick from the current one.
                      if (!searching && results[0]) pick(results[0])
                    }
                  }}
                  placeholder="Search TMDB to add films…"
                  autoComplete="off"
                  aria-controls={`${titleId}-results`}
                />
              </div>
              {q && (
                <ul id={`${titleId}-results`} className={styles.results} aria-label="Search results" aria-busy={searching}>
                  {searching && (
                    <li className={styles.searching} role="status">
                      <span className="sr-only">Searching…</span>
                    </li>
                  )}
                  {/* Placeholder rows while the first answer loads; later searches keep the last answer, dimmed. */}
                  {searching &&
                    results.length === 0 &&
                    [0, 1, 2].map((row) => (
                      <li key={`skeleton-${row}`} className={styles.skeleton} aria-hidden="true">
                        <span className={styles.skeletonThumb} />
                        <span className={styles.skeletonLine} />
                      </li>
                    ))}
                  {failed && <li className={styles.noResults}>Couldn't search right now. Try again in a moment.</li>}
                  {!searching && !failed && results.length === 0 && <li className={styles.noResults}>No films match “{q}”.</li>}
                  {results.map((movie) => (
                    <li key={movie.slug}>
                      <button type="button" className={styles.result} onClick={() => pick(movie)}>
                        <Thumb movie={movie} className={styles.resultThumb} />
                        <span className={styles.resultTitle}>{movie.title}</span>
                        <span className={styles.resultMeta}>{movie.meta}</span>
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
            {picked.length > 0 && (
              <ul className={styles.picked} aria-label="Picked films">
                {picked.map((pickedMovie) => movieOf(tmdbIdOf(pickedMovie), pickedMovie)).map((movie) => (
                  <li key={movie.slug} className={styles.pickedFilm}>
                    <Thumb movie={movie} className={styles.pickedThumb} />
                    {movie.title}
                    <button
                      type="button"
                      className={styles.unpick}
                      aria-label={`Remove ${movie.title}`}
                      onClick={() => setPicked((list) => list.filter((m) => m.slug !== movie.slug))}
                    >
                      <img src={xSmIcon} width={12} height={12} alt="" />
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </div>

          {formError && (
            <p className={styles.noResults} role="alert">
              {formError}
            </p>
          )}

          <div className={styles.footer}>
            <PillButton variant="outline" onClick={() => close()}>
              Cancel
            </PillButton>
            <ButtonPrimary label={saving ? 'Saving…' : 'Save list'} type="submit" disabled={saving} />
          </div>
        </form>
      </div>
    </div>
  )
}

/** A small poster; it shimmers while the film loads, and shows the gradient when TMDB has none. */
function Thumb({ movie, className }: { movie: Movie; className: string }) {
  return (
    <span
      className={isLoading(movie) ? `${className} ${styles.thumbLoading}` : className}
      style={{ background: gradientCss(movie.gradient) }}
    >
      {movie.posterUrl && (
        <img
          className={styles.thumbImg}
          src={posterAt(movie.posterUrl, 'w92')}
          alt=""
          loading="lazy"
          onError={(event) => {
            event.currentTarget.hidden = true
          }}
        />
      )}
    </span>
  )
}
