import { useRef, useState } from 'react'
import { Link, Outlet } from 'react-router'
import AppNav from '../../components/AppNav/AppNav.tsx'
import ButtonPrimary from '../../components/ButtonPrimary/ButtonPrimary.tsx'
import ConfirmDialog from '../../components/ConfirmDialog/ConfirmDialog.tsx'
import PosterCollage from '../../components/PosterCollage/PosterCollage.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import Toast from '../../components/Toast/Toast.tsx'
import globeIcon from '../../assets/lists/icon-globe.svg'
import lockIcon from '../../assets/lists/icon-lock.svg'
import editIcon from '../../assets/lists/icon-edit.svg'
import trashIcon from '../../assets/lists/icon-trash.svg'
import plusIcon from '../../assets/lists/icon-plus.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import { useApi } from '../../lib/api.ts'
import { useMovies } from '../../lib/movieDetails.ts'
import type { ApiList } from '../../lib/types.ts'
import { toPublicList, type MyList, type PublicList } from './listsData.ts'
import { deleteList, useMyLists } from './myListsStore.ts'
import styles from './Lists.module.css'

type Tab = 'mine' | 'public' | 'following'

/** Matches the card's fade-out in Lists.module.css. */
const CARD_EXIT_MS = 300

/** Waits for an animation to finish; no wait when the user turned animations off. */
const afterMotion = (ms: number) =>
  new Promise<void>((resolve) =>
    window.setTimeout(resolve, window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : ms),
  )

function PublicListCard({ list, images }: { list: PublicList; images: (string | undefined)[] }) {
  return (
    <Link to={`/lists/${list.slug}`} className={styles.publicCard}>
      <span className={styles.publicCover}>
        <PosterCollage gradients={list.posters} images={images} size="sm" />
      </span>
      <span className={styles.publicTitle}>{list.title}</span>
      <span className={styles.publicMeta}>
        <span className={styles.author}>
          <span className={styles.avatar} style={{ background: list.color }} aria-hidden="true">
            {list.initials}
          </span>
          by {list.author}
        </span>
        <span className={styles.stats}>
          <span className={styles.films}>{list.films} films</span>
        </span>
      </span>
    </Link>
  )
}

export default function Lists() {
  const pageRef = useRef<HTMLDivElement>(null)
  const tabsRef = useRef<HTMLDivElement>(null)
  useEntranceAnimation(pageRef)

  const { lists: myLists, loaded } = useMyLists()
  const [tab, setTab] = useState<Tab>('mine')
  // Other members' public lists, and those by people you follow.
  const publicLists = (useApi<{ lists: ApiList[] }>('/lists?scope=public').data?.lists ?? []).map(toPublicList)
  const followingLists = (useApi<{ lists: ApiList[] }>('/lists?scope=following').data?.lists ?? []).map(toPublicList)
  // The list waiting for "Delete list?"; `open` turns false first so the dialog can animate out.
  const [confirming, setConfirming] = useState<{ list: MyList; open: boolean } | null>(null)
  // The card fading out after a successful delete.
  const [leavingId, setLeavingId] = useState<number | null>(null)
  const [toast, setToast] = useState<{ id: number; message: string } | null>(null)

  // The cards' cover posters arrive as TMDB ids; load their images (each film once).
  const movieOf = useMovies([...myLists, ...publicLists, ...followingLists].flatMap((list) => list.posterIds))
  const coverImages = (list: { posterIds: number[] }) => list.posterIds.map((id) => movieOf(id).posterUrl)

  const confirmDelete = async () => {
    if (!confirming) return
    const { list } = confirming
    // A failure throws here, so the dialog stays open and shows its error.
    await deleteList(list.id, async () => {
      // Deleted on the server: close the dialog and fade the card out before it leaves the grid.
      setConfirming({ list, open: false })
      setLeavingId(list.id)
      await afterMotion(CARD_EXIT_MS)
    })
    setLeavingId(null)
    setToast({ id: Date.now(), message: `“${list.title}” deleted` })
  }

  const browseAll = () => {
    setTab('public')
    tabsRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }

  const tabs: { id: Tab; label: string; count?: number }[] = [
    { id: 'mine', label: 'My lists', count: myLists.length },
    { id: 'public', label: 'Public lists' },
    { id: 'following', label: 'Following', count: followingLists.length },
  ]

  return (
    <div ref={pageRef} className={styles.page}>
      <AppNav />

      <main className={styles.main}>
        <header className={styles.header} data-enter data-enter-delay="0.1">
          <div className={styles.titleBlock}>
            <SplitHeadline id="lists-title" className={styles.title} lines={[{ text: 'Lists' }]} />
            <p className={styles.lead}>
              Curate collections like “Favorite Comedies” or “Movies to Watch This Summer” — keep them private or share
              them.
            </p>
          </div>
          <ButtonPrimary label="Create a list" to="/lists/new" />
        </header>

        <div ref={tabsRef} className={styles.tabs} role="tablist" aria-label="Lists">
          {tabs.map((t) => (
            <button
              key={t.id}
              type="button"
              role="tab"
              aria-selected={tab === t.id}
              className={styles.tab}
              onClick={() => setTab(t.id)}
            >
              <span>
                {t.label}
                {t.count !== undefined && <span className={styles.tabCount}>{t.count}</span>}
              </span>
            </button>
          ))}
        </div>

        {tab === 'mine' && (
          <ul className={styles.grid} data-enter data-enter-delay="0.25">
            {!loaded && <li className={styles.description}>Loading your lists…</li>}
            {myLists.map((list) => (
              <li key={list.id} className={list.id === leavingId ? `${styles.card} ${styles.cardLeaving}` : styles.card}>
                <Link
                  to={`/lists/${list.slug}`}
                  className={styles.cover}
                  style={{ background: list.cover }}
                  aria-label={`Open ${list.title}`}
                >
                  <PosterCollage gradients={list.posters} images={coverImages(list)} />
                </Link>
                <div className={styles.titleRow}>
                  <Link to={`/lists/${list.slug}`} className={styles.cardTitle}>
                    {list.title}
                  </Link>
                  <span className={list.isPublic ? styles.visPublic : styles.visPrivate}>
                    <img src={list.isPublic ? globeIcon : lockIcon} width={14} height={14} alt="" />
                    {list.isPublic ? 'Public' : 'Private'}
                  </span>
                </div>
                <p className={styles.description}>{list.description}</p>
                <div className={styles.footer}>
                  <span className={styles.count}>
                    {list.films} films · {list.updated}
                  </span>
                  <div className={styles.manage}>
                    <Link to={`/lists/${list.slug}/edit`} className={styles.edit} aria-label={`Edit ${list.title}`}>
                      <img src={editIcon} width={16} height={16} alt="" />
                    </Link>
                    <button
                      type="button"
                      className={styles.delete}
                      aria-label={`Delete ${list.title}`}
                      onClick={() => setConfirming({ list, open: true })}
                    >
                      <img src={trashIcon} width={16} height={16} alt="" />
                    </button>
                  </div>
                </div>
              </li>
            ))}
            <li className={styles.newCell}>
              <Link to="/lists/new" className={styles.newList}>
                <span className={styles.newIcon} aria-hidden="true">
                  <img src={plusIcon} width={29} height={29} alt="" />
                </span>
                <span className={styles.newTitle}>Start a new list</span>
                <span className={styles.newText}>Give it a title, a description and choose who can see it.</span>
              </Link>
            </li>
          </ul>
        )}

        {tab !== 'mine' && (
          <ul className={styles.publicGrid}>
            {(tab === 'public' ? publicLists : followingLists).map((list) => (
              <li key={list.id}>
                <PublicListCard list={list} images={coverImages(list)} />
              </li>
            ))}
            {(tab === 'public' ? publicLists : followingLists).length === 0 && (
              <li className={styles.description}>
                {tab === 'public' ? 'No public lists yet.' : 'People you follow have no public lists yet.'}
              </li>
            )}
          </ul>
        )}

        {tab === 'mine' && (
          <section className={styles.popular} aria-labelledby="popular-title">
            <div className={styles.sectionHeader}>
              <h2 id="popular-title" className={styles.sectionTitle}>
                Popular public lists
              </h2>
              <button type="button" className={styles.sectionAction} onClick={browseAll}>
                Browse all →
              </button>
            </div>
            <ul className={styles.publicGrid}>
              {publicLists.slice(0, 3).map((list) => (
                <li key={list.id}>
                  <PublicListCard list={list} images={coverImages(list)} />
                </li>
              ))}
            </ul>
          </section>
        )}
      </main>

      {/* Create/Edit List dialog (routes lists/new and lists/:slug/edit). */}
      <Outlet />

      {confirming && (
        <ConfirmDialog
          key={confirming.list.id}
          open={confirming.open}
          title={`Delete “${confirming.list.title}”?`}
          media={
            <span className={styles.dialogCover} style={{ background: confirming.list.cover }}>
              <PosterCollage gradients={confirming.list.posters} images={coverImages(confirming.list)} size="sm" />
            </span>
          }
          message={
            <>
              <p>
                This removes the list and its {confirming.list.films} {confirming.list.films === 1 ? 'film' : 'films'}.
                {confirming.list.isPublic && ' It’s public, so anyone who opens its link will no longer find it.'}
              </p>
              <p>This can’t be undone.</p>
            </>
          }
          confirmLabel="Delete list"
          busyLabel="Deleting…"
          errorMessage={`Couldn’t delete “${confirming.list.title}”. Check your connection and try again.`}
          onConfirm={confirmDelete}
          onCancel={() => setConfirming({ ...confirming, open: false })}
          onClosed={() => setConfirming(null)}
        />
      )}

      {toast && <Toast key={toast.id} message={toast.message} onDone={() => setToast(null)} />}
    </div>
  )
}
