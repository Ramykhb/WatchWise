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
import { MOVIES, gradientCss, type Gradient } from '../Home/homeData.ts'
import { getListDetail } from '../ListDetail/listDetailData.ts'
import { getMyLists, saveMyList, uniqueSlug } from './myListsStore.ts'
import styles from './ListDialog.module.css'

const ALL_MOVIES: Movie[] = Object.values(MOVIES)
const CLOSE_MS = 220

type ListDialogProps = { mode: 'create' | 'edit' }

/** "Create a list" / "Edit list" dialog over the Lists page (Figma "Modal — Create / Edit List"). */
export default function ListDialog({ mode }: ListDialogProps) {
  const { slug } = useParams()
  const navigate = useNavigate()
  const existing = mode === 'edit' ? getMyLists().find((list) => list.slug === slug) : undefined

  // An unknown list can't be edited; go back to Lists.
  useEffect(() => {
    if (mode === 'edit' && !existing) navigate('/lists', { replace: true })
  }, [mode, existing, navigate])

  if (mode === 'edit' && !existing) return null
  return <Dialog key={slug ?? 'new'} existing={existing} />
}

function initialPicks(slug: string | undefined): Movie[] {
  if (!slug) return []
  return getListDetail(slug)?.entries.map((entry) => entry.movie) ?? []
}

function Dialog({ existing }: { existing?: ReturnType<typeof getMyLists>[number] }) {
  const navigate = useNavigate()
  const titleId = useId()
  const dialogRef = useRef<HTMLDivElement>(null)
  const isEdit = Boolean(existing)

  const [title, setTitle] = useState(existing?.title ?? '')
  const [description, setDescription] = useState(existing?.description ?? '')
  const [isPublic, setIsPublic] = useState(existing?.isPublic ?? true)
  const [initial] = useState<Movie[]>(() => initialPicks(existing?.slug))
  const [picked, setPicked] = useState<Movie[]>(initial)
  const [query, setQuery] = useState('')
  const [titleError, setTitleError] = useState('')
  const [closing, setClosing] = useState(false)

  const q = query.trim().toLowerCase()
  // TODO: search TMDB through the backend instead of the local mock movies.
  const results = q
    ? ALL_MOVIES.filter((movie) => movie.title.toLowerCase().includes(q) && !picked.includes(movie)).slice(0, 5)
    : []

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
    setPicked((list) => [...list, movie])
    setQuery('')
  }

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const cleanTitle = title.trim()
    if (!cleanTitle) {
      setTitleError('Give your list a title.')
      dialogRef.current?.querySelector<HTMLInputElement>('input')?.focus()
      return
    }
    // Cover: the first three picked posters, padded with the existing cover.
    const posters: Gradient[] = [...picked.map((movie) => movie.gradient), ...(existing?.posters ?? []), MOVIES.neonTide.gradient]
      .filter((gradient, index, all) => all.indexOf(gradient) === index)
      .slice(0, 3)
    const slug = existing?.slug ?? uniqueSlug(cleanTitle)
    // TODO: POST /lists or PATCH /lists/:slug once the API exists.
    saveMyList({
      slug,
      title: cleanTitle,
      description: description.trim(),
      isPublic,
      // Mock lists only show a few of their films, so adjust the total by what changed here.
      films: existing ? Math.max(0, existing.films + picked.length - initial.length) : picked.length,
      updated: 'updated just now',
      cover: isPublic ? 'var(--surface-violet-tint)' : 'var(--surface-cream-deep)',
      posters,
      movies: picked.map((movie) => movie.slug),
    })
    close(isEdit ? '/lists' : `/lists/${slug}`)
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
                      if (results[0]) pick(results[0])
                    }
                  }}
                  placeholder="Search TMDB to add films…"
                  autoComplete="off"
                  aria-controls={`${titleId}-results`}
                />
              </div>
              {q && (
                <ul id={`${titleId}-results`} className={styles.results} aria-label="Search results">
                  {results.length === 0 && <li className={styles.noResults}>No films match “{query}”.</li>}
                  {results.map((movie) => (
                    <li key={movie.slug}>
                      <button type="button" className={styles.result} onClick={() => pick(movie)}>
                        <span className={styles.resultThumb} style={{ background: gradientCss(movie.gradient) }} />
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
                {picked.map((movie) => (
                  <li key={movie.slug} className={styles.pickedFilm}>
                    <span className={styles.pickedThumb} style={{ background: gradientCss(movie.gradient) }} />
                    {movie.title}
                    <button
                      type="button"
                      className={styles.unpick}
                      aria-label={`Remove ${movie.title}`}
                      onClick={() => setPicked((list) => list.filter((m) => m !== movie))}
                    >
                      <img src={xSmIcon} width={12} height={12} alt="" />
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </div>

          <div className={styles.footer}>
            <PillButton variant="outline" onClick={() => close()}>
              Cancel
            </PillButton>
            <ButtonPrimary label="Save list" type="submit" />
          </div>
        </form>
      </div>
    </div>
  )
}
