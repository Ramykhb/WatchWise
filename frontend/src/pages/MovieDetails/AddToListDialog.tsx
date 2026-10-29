import { useEffect, useId, useRef, useState, type FormEvent, type KeyboardEvent } from 'react'
import { Link } from 'react-router'
import xIcon from '../../assets/listmodal/icon-x.svg'
import globeIcon from '../../assets/lists/icon-globe.svg'
import lockIcon from '../../assets/lists/icon-lock.svg'
import plusIcon from '../../assets/lists/icon-plus.svg'
import checkIcon from '../../assets/movie/icon-check.svg'
import { api } from '../../lib/api.ts'
import { apiErrorMessage } from '../../lib/auth.ts'
import { posterAt, useMovies } from '../../lib/movieDetails.ts'
import { itemKey, type FilmKey, type MediaType } from '../../lib/movies.ts'
import type { ApiList } from '../../lib/types.ts'
import { gradientCss } from '../Home/homeData.ts'
import { addMovieToList, createList, removeMovieFromList } from '../Lists/myListsStore.ts'
import styles from './AddToListDialog.module.css'

const CLOSE_MS = 220

/** A list's first title, as a key (a show's cover isn't mistaken for the movie with the same id). */
const coverKeyOf = (list: ApiList): FilmKey | undefined => {
  const first = list.cover?.[0]
  return first ? itemKey(first) : list.posters[0]
}

type AddToListDialogProps = {
  /** Keep it rendered and flip this to false to close it with its exit animation. */
  open: boolean
  tmdbId: number
  /** Whether `tmdbId` is a movie or a TV show (TMDB numbers them separately). */
  mediaType: MediaType
  title: string
  onClose: () => void
  /** Called once the exit animation has finished after `open` became false. */
  onClosed: () => void
}

/**
 * "Add to list" over the Movie page (which stays visible, blurred, behind it): the user's lists
 * with a tick on the ones that already hold this film. Clicking a list adds or removes the film;
 * "New list" creates one that starts with it.
 */
export default function AddToListDialog({ open, tmdbId, mediaType, title, onClose, onClosed }: AddToListDialogProps) {
  const titleId = useId()
  const dialogRef = useRef<HTMLDivElement>(null)
  const [lists, setLists] = useState<ApiList[] | null>(null)
  const [loadFailed, setLoadFailed] = useState(false)
  const [retries, setRetries] = useState(0)
  // Lists whose request is running, so their row can't be clicked twice.
  const [saving, setSaving] = useState<number[]>([])
  const [error, setError] = useState('')
  const [status, setStatus] = useState('')
  const [creating, setCreating] = useState(false)
  const [newTitle, setNewTitle] = useState('')
  const [newPublic, setNewPublic] = useState(true)
  const [submitting, setSubmitting] = useState(false)

  // The user's lists, each saying whether it already has this film.
  useEffect(() => {
    let active = true
    api
      .get<{ lists: ApiList[] }>('/lists', { params: { scope: 'mine', contains: tmdbId, contains_type: mediaType } })
      .then((response) => {
        if (!active) return
        setLists(response.data.lists)
        setLoadFailed(false)
      })
      .catch(() => active && setLoadFailed(true))
    return () => {
      active = false
    }
  }, [tmdbId, mediaType, retries])

  // Cover thumbnails: the first film of each list.
  const coverIds = (lists ?? []).map(coverKeyOf).filter((key): key is FilmKey => key !== undefined)
  const movieOf = useMovies(coverIds)

  // Focus the dialog, lock the page scroll and give focus back to "Add to list" when it goes away.
  useEffect(() => {
    const previous = document.activeElement as HTMLElement | null
    dialogRef.current?.querySelector<HTMLElement>('button')?.focus()
    const { overflow } = document.body.style
    document.body.style.overflow = 'hidden'
    return () => {
      document.body.style.overflow = overflow
      previous?.focus?.()
    }
  }, [])

  // Kept in a ref so a parent re-render during the exit doesn't restart the timer below.
  const onClosedRef = useRef(onClosed)
  useEffect(() => {
    onClosedRef.current = onClosed
  })

  useEffect(() => {
    if (open) return
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    const timer = window.setTimeout(() => onClosedRef.current(), reduced ? 0 : CLOSE_MS)
    return () => window.clearTimeout(timer)
  }, [open])

  const close = () => {
    if (open) onClose()
  }

  const replace = (updated: ApiList, hasMovie: boolean) =>
    setLists((current) => current?.map((list) => (list.id === updated.id ? { ...updated, has_movie: hasMovie } : list)) ?? null)

  const toggle = async (list: ApiList) => {
    if (saving.includes(list.id)) return
    const adding = !list.has_movie
    setError('')
    setSaving((ids) => [...ids, list.id])
    // Shows at once; put back if the request fails.
    replace(list, adding)
    try {
      const updated = adding ? await addMovieToList(list.id, tmdbId, mediaType) : await removeMovieFromList(list.id, tmdbId, mediaType)
      replace(updated, adding)
      setStatus(adding ? `Added to “${list.title}”` : `Removed from “${list.title}”`)
    } catch (failure) {
      replace(list, !adding)
      setError(apiErrorMessage(failure, `Couldn't update “${list.title}”. Please try again.`))
    } finally {
      setSaving((ids) => ids.filter((id) => id !== list.id))
    }
  }

  const handleCreate = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const clean = newTitle.trim()
    if (!clean) return
    setSubmitting(true)
    setError('')
    try {
      const created = await createList({ title: clean, description: '', is_public: newPublic, tmdb_ids: [mediaType === 'tv' ? `tv-${tmdbId}` : tmdbId] })
      setLists((current) => [{ ...created, has_movie: true }, ...(current ?? [])])
      setStatus(`Created “${clean}” with ${title}`)
      setCreating(false)
      setNewTitle('')
    } catch (failure) {
      setError(apiErrorMessage(failure, "Couldn't create the list. Please try again."))
    } finally {
      setSubmitting(false)
    }
  }

  // Esc closes; Tab and Shift+Tab stay inside the dialog.
  const handleKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (event.key === 'Escape') {
      event.stopPropagation()
      close()
      return
    }
    if (event.key !== 'Tab' || !dialogRef.current) return
    const focusable = [...dialogRef.current.querySelectorAll<HTMLElement>('a[href], button:not([disabled]), input')]
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

  const count = lists?.filter((list) => list.has_movie).length ?? 0

  return (
    <div
      className={open ? styles.backdrop : styles.backdropClosing}
      onMouseDown={(event) => event.target === event.currentTarget && close()}
      onKeyDown={handleKeyDown}
      data-lenis-prevent
    >
      <div ref={dialogRef} className={styles.dialog} role="dialog" aria-modal="true" aria-labelledby={titleId}>
        <div className={styles.head}>
          <div>
            <h2 id={titleId} className={styles.title}>
              Add to list
            </h2>
            <p className={styles.subtitle}>
              {title}
              {count > 0 && ` · in ${count} ${count === 1 ? 'list' : 'lists'}`}
            </p>
          </div>
          <button type="button" className={styles.close} aria-label="Close" onClick={close}>
            <img src={xIcon} width={16} height={16} alt="" />
          </button>
        </div>

        <p className="sr-only" role="status">
          {status}
        </p>

        {loadFailed ? (
          <p className={styles.note} role="alert">
            Couldn't load your lists.{' '}
            <button type="button" className={styles.textButton} onClick={() => setRetries((n) => n + 1)}>
              Try again
            </button>
          </p>
        ) : !lists ? (
          <ul className={styles.lists} aria-label="Loading your lists">
            {[0, 1, 2].map((n) => (
              <li key={n} className={styles.skeleton} />
            ))}
          </ul>
        ) : (
          <ul className={styles.lists}>
            {lists.length === 0 && !creating && <li className={styles.note}>You don't have any lists yet. Start one below.</li>}
            {lists.map((list) => {
              const coverKey = coverKeyOf(list)
              const cover = coverKey !== undefined ? movieOf(coverKey) : undefined
              const image = posterAt(cover?.posterUrl, 'w92')
              const busy = saving.includes(list.id)
              return (
                <li key={list.id}>
                  <button
                    type="button"
                    className={list.has_movie ? styles.rowOn : styles.row}
                    aria-pressed={Boolean(list.has_movie)}
                    disabled={busy}
                    onClick={() => void toggle(list)}
                  >
                    <span
                      className={styles.thumb}
                      style={{
                        background: image
                          ? `center / cover no-repeat url("${image}"), ${gradientCss(cover!.gradient)}`
                          : cover
                            ? gradientCss(cover.gradient)
                            : 'var(--surface-cream-deep)',
                      }}
                      aria-hidden="true"
                    />
                    <span className={styles.rowText}>
                      <span className={styles.rowTitle}>{list.title}</span>
                      <span className={styles.rowMeta}>
                        <img src={list.is_public ? globeIcon : lockIcon} width={12} height={12} alt="" />
                        {list.is_public ? 'Public' : 'Private'} · {list.films} {list.films === 1 ? 'film' : 'films'}
                      </span>
                    </span>
                    <span className={list.has_movie ? styles.checkOn : styles.check} aria-hidden="true">
                      {list.has_movie && <img src={checkIcon} width={14} height={14} alt="" />}
                    </span>
                    <span className="sr-only">{list.has_movie ? ', added. Click to remove' : ', click to add'}</span>
                  </button>
                </li>
              )
            })}
          </ul>
        )}

        {error && (
          <p className={styles.error} role="alert">
            {error}
          </p>
        )}

        {creating ? (
          <form className={styles.create} onSubmit={handleCreate}>
            <input
              className={styles.input}
              value={newTitle}
              onChange={(event) => setNewTitle(event.target.value)}
              placeholder="List name, e.g. Rainy day comfort"
              aria-label="New list name"
              maxLength={80}
              autoFocus
            />
            <div className={styles.createRow}>
              <div className={styles.segmented} role="radiogroup" aria-label="Who can see the new list">
                <button type="button" role="radio" aria-checked={newPublic} className={newPublic ? styles.segmentOn : styles.segment} onClick={() => setNewPublic(true)}>
                  Public
                </button>
                <button type="button" role="radio" aria-checked={!newPublic} className={!newPublic ? styles.segmentOn : styles.segment} onClick={() => setNewPublic(false)}>
                  Private
                </button>
              </div>
              <button type="button" className={styles.textButton} onClick={() => setCreating(false)}>
                Cancel
              </button>
              <button type="submit" className={styles.primary} disabled={!newTitle.trim() || submitting}>
                {submitting ? 'Creating…' : 'Create & add'}
              </button>
            </div>
          </form>
        ) : (
          <button type="button" className={styles.newList} onClick={() => setCreating(true)}>
            <span className={styles.newIcon} aria-hidden="true">
              <img src={plusIcon} width={16} height={16} alt="" />
            </span>
            New list
          </button>
        )}

        <div className={styles.foot}>
          <Link to="/lists" className={styles.textButton}>
            Manage your lists →
          </Link>
          <button type="button" className={styles.primary} onClick={close}>
            Done
          </button>
        </div>
      </div>
    </div>
  )
}
