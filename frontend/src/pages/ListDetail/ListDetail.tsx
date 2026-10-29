import { useEffect, useRef, useState, type KeyboardEvent, type PointerEvent } from 'react'
import { Link, useParams } from 'react-router'
import { useLenis } from 'lenis/react'
import AppNav from '../../components/AppNav/AppNav.tsx'
import PillButton from '../../components/PillButton/PillButton.tsx'
import PosterCollage from '../../components/PosterCollage/PosterCollage.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import Placeholder from '../Placeholder/Placeholder.tsx'
import headerWave from '../../assets/movie/hero-wave.svg'
import globeIcon from '../../assets/lists/icon-globe.svg'
import lockIcon from '../../assets/lists/icon-lock.svg'
import shareIcon from '../../assets/listdetail/icon-share.svg'
import searchIcon from '../../assets/listdetail/icon-search.svg'
import chevDownIcon from '../../assets/watchlist/icon-chev-down.svg'
import dragIcon from '../../assets/listdetail/icon-drag.svg'
import starIcon from '../../assets/movie/star-full.svg'
import xIcon from '../../assets/listdetail/icon-x.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import { gradientCss } from '../Home/homeData.ts'
import { useAuth } from '../../contexts/useAuth.ts'
import { isStatus, useApi } from '../../lib/api.ts'
import { isLoading, posterAt, useMovies } from '../../lib/movieDetails.ts'
import { filmKeyOf, itemKey } from '../../lib/movies.ts'
import type { ApiList } from '../../lib/types.ts'
import { listIdFrom } from '../Lists/listsData.ts'
import { updateList } from '../Lists/myListsStore.ts'
import { runtimeFor, toListDetail, toSimilarList, type ListDetail as Detail, type ListEntry } from './listDetailData.ts'
import styles from './ListDetail.module.css'

const SORTS = {
  custom: { label: 'Custom order', compare: null },
  title: { label: 'Title A–Z', compare: (a: ListEntry, b: ListEntry) => a.movie.title.localeCompare(b.movie.title) },
  rating: { label: 'Highest rated', compare: (a: ListEntry, b: ListEntry) => Number(b.movie.score) - Number(a.movie.score) },
  year: { label: 'Newest first', compare: (a: ListEntry, b: ListEntry) => b.movie.meta.localeCompare(a.movie.meta) },
}
type SortKey = keyof typeof SORTS

/**
 * A row being dragged by its handle: where it started, the slot it would drop into, how far it has
 * moved, and the rows' page positions (top, middle, bottom) measured when the drag began.
 */
type Drag = { from: number; over: number; dy: number; startY: number; step: number; tops: number[]; mids: number[]; bottoms: number[] }

/** Dragging this close (px) to the top or bottom of the window scrolls the page. */
const SCROLL_EDGE = 90

/**
 * Moves the dragged row to follow the pointer (kept within the list) and picks its slot: it takes a
 * neighbour's place as soon as its leading edge passes that neighbour's middle.
 */
function dragTo(drag: Drag, clientY: number): Drag {
  const { from, tops, mids, bottoms } = drag
  const last = mids.length - 1
  const dy = Math.min(Math.max(clientY + window.scrollY - drag.startY, tops[0] - tops[from]), bottoms[last] - bottoms[from])
  let over = from
  for (let i = from + 1; i <= last; i++) if (bottoms[from] + dy > mids[i]) over = i
  for (let i = from - 1; i >= 0; i--) if (tops[from] + dy < mids[i]) over = i
  return { ...drag, dy, over }
}

export default function ListDetail() {
  const { slug } = useParams()
  const { user } = useAuth()
  const { data, error } = useApi<{ list: ApiList }>(`/lists/${listIdFrom(slug)}`)
  // Private lists of other members answer 403; treat them like missing ones.
  if (isStatus(error, 404) || isStatus(error, 403) || Number.isNaN(listIdFrom(slug))) return <Placeholder title="List not found" notFound />
  if (error) return <Placeholder title="Couldn't load this list" />
  if (!data) return null
  // Keyed by the list and its films, so local state (order, search) resets when moving between lists
  // and when a fresh copy arrives after an edit (the first render may use the copy from an earlier visit).
  const films = (data.list.items ?? []).map(itemKey).join(',')
  return <ListDetailPage key={`${data.list.id}:${films}`} detail={toListDetail(data.list, user?.id)} />
}

function ListDetailPage({ detail }: { detail: Detail }) {
  const pageRef = useRef<HTMLDivElement>(null)
  useEntranceAnimation(pageRef)

  // The owner's reorder and remove are saved through PATCH /api/lists/{id}; others only read.
  const [entries, setEntries] = useState(detail.entries)
  // The films arrive as ids only; load their real titles and posters.
  const [savedIds] = useState(() => detail.entries.map((entry) => filmKeyOf(entry.movie)))
  const movieOf = useMovies(savedIds)
  const resolved = entries.map((entry) => ({ ...entry, movie: movieOf(filmKeyOf(entry.movie), entry.movie) }))
  // The header collage shows the list's first three films, so it follows reorders and removals.
  const cover = resolved.slice(0, 3)
  const similar = (useApi<{ lists: ApiList[] }>('/lists?scope=public').data?.lists ?? [])
    .filter((list) => list.id !== detail.id)
    .slice(0, 3)
    .map(toSimilarList)
  const similarMovieOf = useMovies(similar.map((list) => list.posterId).filter(Boolean))
  const [query, setQuery] = useState('')
  const [sort, setSort] = useState<SortKey>('custom')
  const listRef = useRef<HTMLOListElement>(null)
  const [drag, setDrag] = useState<Drag | null>(null)
  // True for one frame after a drop, so rows jump straight to their new places instead of sliding back.
  const [settling, setSettling] = useState(false)

  useEffect(() => {
    if (!settling) return
    const frame = requestAnimationFrame(() => setSettling(false))
    return () => cancelAnimationFrame(frame)
  }, [settling])

  // While dragging near the top or bottom of the window, scroll the page so any slot can be reached.
  const lenis = useLenis()
  const pointerY = useRef(0)
  const dragging = drag !== null
  useEffect(() => {
    if (!dragging) return
    let frame = 0
    const tick = () => {
      const y = pointerY.current
      const speed =
        y < SCROLL_EDGE ? -(SCROLL_EDGE - y) / 4 : y > window.innerHeight - SCROLL_EDGE ? (y - window.innerHeight + SCROLL_EDGE) / 4 : 0
      if (speed) {
        const target = window.scrollY + Math.round(speed)
        if (lenis) lenis.scrollTo(target, { immediate: true })
        else window.scrollTo(0, target)
        setDrag((current) => current && dragTo(current, y))
      }
      frame = requestAnimationFrame(tick)
    }
    frame = requestAnimationFrame(tick)
    return () => cancelAnimationFrame(frame)
  }, [dragging, lenis])
  const [shareNote, setShareNote] = useState('')
  const [status, setStatus] = useState('')

  const filmsCount = entries.length

  const q = query.trim().toLowerCase()
  const compare = SORTS[sort].compare
  const shown = resolved
    .map((entry, index) => ({ entry, index }))
    .filter(({ entry }) => !q || entry.movie.title.toLowerCase().includes(q))
    .sort((a, b) => (compare ? compare(a.entry, b.entry) : 0))
  // Reordering only makes sense on the full list in its own order, and only for the owner.
  const canReorder = detail.isOwner && sort === 'custom' && !q

  /** Shows the new order at once and saves it; a failed save puts the old one back. */
  const save = (next: ListEntry[], message: string) => {
    const previous = entries
    setEntries(next)
    setStatus(message)
    updateList(detail.id, { tmdb_ids: next.map((entry) => filmKeyOf(entry.movie)) }).catch(() => {
      setEntries(previous)
      setStatus("Couldn't save that change. Please try again.")
    })
  }

  const move = (from: number, to: number) => {
    if (to < 0 || to >= entries.length || from === to) return
    const next = [...entries]
    const [item] = next.splice(from, 1)
    next.splice(to, 0, item)
    save(next, `${resolved[from].movie.title} moved to position ${to + 1}`)
  }

  const handleKeyMove = (event: KeyboardEvent<HTMLButtonElement>, index: number) => {
    if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
      event.preventDefault()
      move(index, index + (event.key === 'ArrowUp' ? -1 : 1))
    }
  }

  /** Lifts the row under the handle; it follows the pointer and the others slide out of its way. */
  const startDrag = (event: PointerEvent<HTMLButtonElement>, index: number) => {
    const rows = [...(listRef.current?.children ?? [])]
    if (!canReorder || event.button !== 0 || rows.length < 2) return
    event.preventDefault()
    event.currentTarget.setPointerCapture(event.pointerId)
    const rects = rows.map((row) => row.getBoundingClientRect())
    const gap = parseFloat(getComputedStyle(listRef.current as HTMLOListElement).rowGap) || 0
    // Page positions, so they stay right while the page scrolls during the drag.
    const scrollY = window.scrollY
    pointerY.current = event.clientY
    setDrag({
      from: index,
      over: index,
      dy: 0,
      startY: event.clientY + scrollY,
      step: rects[index].height + gap,
      tops: rects.map((rect) => rect.top + scrollY),
      mids: rects.map((rect) => rect.top + rect.height / 2 + scrollY),
      bottoms: rects.map((rect) => rect.bottom + scrollY),
    })
  }

  const moveDrag = (event: PointerEvent<HTMLButtonElement>) => {
    pointerY.current = event.clientY
    setDrag((current) => current && dragTo(current, event.clientY))
  }

  const endDrag = (drop: boolean) => {
    if (!drag) return
    if (drop && drag.over !== drag.from) move(drag.from, drag.over)
    setSettling(true)
    setDrag(null)
  }

  /** How far a row slides to make room for the dragged one. */
  const shiftFor = (index: number) => {
    if (!drag || index === drag.from) return 0
    if (drag.from < drag.over && index > drag.from && index <= drag.over) return -drag.step
    if (drag.over < drag.from && index >= drag.over && index < drag.from) return drag.step
    return 0
  }

  const remove = (index: number) => {
    save(
      entries.filter((_, i) => i !== index),
      `${resolved[index].movie.title} removed from the list`,
    )
  }

  const handleShare = async () => {
    const url = window.location.href
    try {
      if (navigator.share) {
        await navigator.share({ title: detail.title, url })
        return
      }
      await navigator.clipboard.writeText(url)
      setShareNote('Link copied')
    } catch {
      setShareNote('')
    }
  }

  return (
    <div ref={pageRef} className={styles.page}>
      <AppNav />

      <main>
        <section className={styles.header} aria-labelledby="list-title">
          <div className={styles.headerInner}>
            <div className={styles.cover} data-enter data-enter-delay="0.1" data-enter-rise="80">
              <PosterCollage
                gradients={cover.length ? cover.map((entry) => entry.movie.gradient) : detail.posters}
                images={cover.map((entry) => entry.movie.posterUrl)}
                pending={cover.map((entry) => isLoading(entry.movie))}
                size="xl"
              />
            </div>
            <div className={styles.info} data-enter data-enter-delay="0.25">
              <ul className={styles.tags}>
                <li className={detail.isPublic ? styles.tagPublic : styles.tagPrivate}>
                  <img src={detail.isPublic ? globeIcon : lockIcon} width={14} height={14} alt="" />
                  {detail.isPublic ? 'Public list' : 'Private list'}
                </li>
                <li className={styles.tag}>{filmsCount} films</li>
                <li className={styles.tag}>{detail.updated}</li>
              </ul>
              <SplitHeadline id="list-title" className={styles.title} lines={[{ text: detail.title }]} />
              <p className={styles.description}>{detail.description}</p>
              <Link to={detail.isOwner ? '/profile' : `/profile/${detail.author.handle}`} className={styles.author}>
                <span className={styles.avatar} style={{ background: detail.author.color }} aria-hidden="true">
                  {detail.author.initials}
                </span>
                <span className={styles.authorName}>{detail.isOwner ? 'You' : detail.author.name}</span>
                <span className={styles.authorMeta}>· Created {detail.created}</span>
              </Link>
              <div className={styles.actions}>
                <button type="button" className={styles.actionOutline} onClick={handleShare}>
                  <img src={shareIcon} width={16} height={16} alt="" />
                  Share
                </button>
                <span className={styles.shareNote} role="status">
                  {shareNote}
                </span>
              </div>
            </div>
          </div>
        </section>
        <img className={styles.headerWave} src={headerWave} width={1440} height={70} alt="" />

        <div className={styles.body}>
          <section className={styles.films} aria-label="Films in this list" data-enter data-enter-delay="0.35">
            <div className={styles.toolbar}>
              <label className={styles.search}>
                <img src={searchIcon} width={16} height={16} alt="" />
                <span className="sr-only">Search this list</span>
                <input
                  type="search"
                  value={query}
                  onChange={(event) => setQuery(event.target.value)}
                  placeholder="Search this list"
                />
              </label>
              <div className={styles.toolbarRight}>
                <label className={styles.sort}>
                  <span className="sr-only">Sort films</span>
                  <select value={sort} onChange={(event) => setSort(event.target.value as SortKey)}>
                    {Object.entries(SORTS).map(([key, option]) => (
                      <option key={key} value={key}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                  <img src={chevDownIcon} width={14} height={14} alt="" />
                </label>
                {detail.isOwner && <PillButton to={`/lists/${detail.slug}/edit`}>+ Add movies</PillButton>}
              </div>
            </div>

            <p className="sr-only" role="status">
              {status}
            </p>

            {shown.length === 0 && (
              <p className={styles.empty}>{q ? `No films in this list match “${query}”.` : 'This list has no films yet.'}</p>
            )}

            <ol
              ref={listRef}
              className={styles.entries}
              data-dragging={drag ? '' : undefined}
              data-settling={settling ? '' : undefined}
            >
              {shown.map(({ entry, index }) => (
                <li
                  key={entry.movie.slug}
                  className={drag?.from === index ? styles.entryLifted : styles.entry}
                  style={{
                    transform: drag?.from === index ? `translateY(${drag.dy}px)` : shiftFor(index) ? `translateY(${shiftFor(index)}px)` : undefined,
                  }}
                >
                  {detail.isOwner && (
                    <button
                      type="button"
                      className={styles.drag}
                      disabled={!canReorder}
                      aria-label={`Reorder ${entry.movie.title}: drag, or use the up and down arrow keys`}
                      onKeyDown={(event) => handleKeyMove(event, index)}
                      onPointerDown={(event) => startDrag(event, index)}
                      onPointerMove={moveDrag}
                      onPointerUp={() => endDrag(true)}
                      onPointerCancel={() => endDrag(false)}
                    >
                      <img src={dragIcon} width={18} height={18} alt="" />
                    </button>
                  )}
                  <span className={styles.rank}>{String(index + 1).padStart(2, '0')}</span>
                  <Link
                    to={`/movies/${entry.movie.slug}`}
                    className={isLoading(entry.movie) ? `${styles.thumb} ${styles.thumbLoading}` : styles.thumb}
                    style={{ background: gradientCss(entry.movie.gradient) }}
                    aria-label={entry.movie.title}
                    draggable={false}
                  >
                    {entry.movie.posterUrl && (
                      <img
                        className={styles.thumbImg}
                        src={posterAt(entry.movie.posterUrl, 'w185')}
                        alt=""
                        loading="lazy"
                        draggable={false}
                        onError={(event) => {
                          event.currentTarget.hidden = true
                        }}
                      />
                    )}
                  </Link>
                  <div className={styles.entryInfo}>
                    <Link to={`/movies/${entry.movie.slug}`} className={styles.entryTitle} draggable={false}>
                      {entry.movie.title}
                    </Link>
                    <span className={styles.entryMeta}>{entry.movie.meta}</span>
                    {entry.note && <p className={styles.note}>{entry.note}</p>}
                  </div>
                  <span className={styles.score}>
                    <img src={starIcon} width={16} height={16} alt="" />
                    <span className="sr-only">Rated </span>
                    {entry.movie.score}
                  </span>
                  {detail.isOwner && (
                    <button
                      type="button"
                      className={styles.remove}
                      aria-label={`Remove ${entry.movie.title} from this list`}
                      onClick={() => remove(index)}
                    >
                      <img src={xIcon} width={16} height={16} alt="" />
                    </button>
                  )}
                </li>
              ))}
            </ol>
          </section>

          <aside className={styles.side} data-enter data-enter-delay="0.45">
            <section className={styles.about} aria-labelledby="about-title">
              <h2 id="about-title" className={styles.cardTitle}>
                About this list
              </h2>
              <dl className={styles.facts}>
                <div>
                  <dt>Visibility</dt>
                  <dd>{detail.isPublic ? 'Public' : 'Private'}</dd>
                </div>
                <div>
                  <dt>Films</dt>
                  <dd>{filmsCount}</dd>
                </div>
                <div>
                  <dt>Total runtime</dt>
                  <dd>{runtimeFor(filmsCount)}</dd>
                </div>
                <div>
                  <dt>Created</dt>
                  <dd>{detail.created}</dd>
                </div>
              </dl>
            </section>

            {detail.isPublic && !detail.isOwner && (
              <section className={styles.followers} aria-labelledby="followers-title">
                <h2 id="followers-title" className={styles.cardTitle}>
                  More from {detail.author.name}
                </h2>
                <p className={styles.followersText}>Follow {detail.author.name} to see their new public lists under Lists › Following.</p>
                <PillButton variant="outline" to={`/profile/${detail.author.handle}`}>
                  View profile
                </PillButton>
              </section>
            )}

            <section className={styles.similar} aria-labelledby="similar-title">
              <h2 id="similar-title" className={styles.cardTitle}>
                Other public lists
              </h2>
              <ul className={styles.similarList}>
                {similar.map((list) => (
                  <li key={list.slug}>
                    <Link to={`/lists/${list.slug}`} className={styles.similarItem}>
                      <span className={styles.similarThumb} style={{ background: gradientCss(list.poster) }}>
                        {Boolean(list.posterId) && similarMovieOf(list.posterId).posterUrl && (
                          <img
                            className={styles.thumbImg}
                            src={posterAt(similarMovieOf(list.posterId).posterUrl, 'w92')}
                            alt=""
                            loading="lazy"
                            onError={(event) => {
                              event.currentTarget.hidden = true
                            }}
                          />
                        )}
                      </span>
                      <span className={styles.similarText}>
                        <span className={styles.similarTitle}>{list.title}</span>
                        <span className={styles.similarAuthor}>by {list.author}</span>
                      </span>
                    </Link>
                  </li>
                ))}
              </ul>
            </section>
          </aside>
        </div>
      </main>
    </div>
  )
}
