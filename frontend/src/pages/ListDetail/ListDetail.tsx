import { useRef, useState, type KeyboardEvent } from 'react'
import { Link, useParams } from 'react-router'
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
import { tmdbIdOf } from '../../lib/movies.ts'
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

export default function ListDetail() {
  const { slug } = useParams()
  const { user } = useAuth()
  const { data, error } = useApi<{ list: ApiList }>(`/lists/${listIdFrom(slug)}`)
  // Private lists of other members answer 403; treat them like missing ones.
  if (isStatus(error, 404) || isStatus(error, 403) || Number.isNaN(listIdFrom(slug))) return <Placeholder title="List not found" notFound />
  if (error) return <Placeholder title="Couldn't load this list" />
  if (!data) return null
  // Keyed by id so local state (order, search) resets when moving between lists.
  return <ListDetailPage key={data.list.id} detail={toListDetail(data.list, user?.id)} />
}

function ListDetailPage({ detail }: { detail: Detail }) {
  const pageRef = useRef<HTMLDivElement>(null)
  useEntranceAnimation(pageRef)

  // The owner's reorder and remove are saved through PATCH /api/lists/{id}; others only read.
  const [entries, setEntries] = useState(detail.entries)
  const similar = (useApi<{ lists: ApiList[] }>('/lists?scope=public').data?.lists ?? [])
    .filter((list) => list.id !== detail.id)
    .slice(0, 3)
    .map(toSimilarList)
  const [query, setQuery] = useState('')
  const [sort, setSort] = useState<SortKey>('custom')
  const [dragIndex, setDragIndex] = useState<number | null>(null)
  const [shareNote, setShareNote] = useState('')
  const [status, setStatus] = useState('')

  const filmsCount = entries.length

  const q = query.trim().toLowerCase()
  const compare = SORTS[sort].compare
  const shown = entries
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
    updateList(detail.id, { tmdb_ids: next.map((entry) => tmdbIdOf(entry.movie)) }).catch(() => {
      setEntries(previous)
      setStatus("Couldn't save that change. Please try again.")
    })
  }

  const move = (from: number, to: number) => {
    if (to < 0 || to >= entries.length || from === to) return
    const next = [...entries]
    const [item] = next.splice(from, 1)
    next.splice(to, 0, item)
    save(next, `${entries[from].movie.title} moved to position ${to + 1}`)
  }

  const handleKeyMove = (event: KeyboardEvent<HTMLButtonElement>, index: number) => {
    if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
      event.preventDefault()
      move(index, index + (event.key === 'ArrowUp' ? -1 : 1))
    }
  }

  const remove = (index: number) => {
    save(
      entries.filter((_, i) => i !== index),
      `${entries[index].movie.title} removed from the list`,
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
              <PosterCollage gradients={detail.posters} size="xl" />
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

            <ol className={styles.entries}>
              {shown.map(({ entry, index }) => (
                <li
                  key={entry.movie.slug}
                  className={dragIndex === index ? styles.entryDragging : styles.entry}
                  draggable={canReorder}
                  onDragStart={() => setDragIndex(index)}
                  onDragOver={(event) => canReorder && event.preventDefault()}
                  onDrop={() => {
                    if (dragIndex !== null) move(dragIndex, index)
                    setDragIndex(null)
                  }}
                  onDragEnd={() => setDragIndex(null)}
                >
                  {detail.isOwner && (
                    <button
                      type="button"
                      className={styles.drag}
                      disabled={!canReorder}
                      aria-label={`Reorder ${entry.movie.title}: use the up and down arrow keys`}
                      onKeyDown={(event) => handleKeyMove(event, index)}
                    >
                      <img src={dragIcon} width={18} height={18} alt="" />
                    </button>
                  )}
                  <span className={styles.rank}>{String(index + 1).padStart(2, '0')}</span>
                  <Link
                    to={`/movies/${entry.movie.slug}`}
                    className={styles.thumb}
                    style={{ background: gradientCss(entry.movie.gradient) }}
                    aria-label={entry.movie.title}
                    draggable={false}
                  />
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
                      <span className={styles.similarThumb} style={{ background: gradientCss(list.poster) }} />
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
