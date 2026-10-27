import { useRef, useState } from 'react'
import { Link, Outlet } from 'react-router'
import AppNav from '../../components/AppNav/AppNav.tsx'
import ButtonPrimary from '../../components/ButtonPrimary/ButtonPrimary.tsx'
import ConfirmDialog from '../../components/ConfirmDialog/ConfirmDialog.tsx'
import ListCover from '../../components/PosterCollage/ListCover.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import Toast from '../../components/Toast/Toast.tsx'
import globeIcon from '../../assets/lists/icon-globe.svg'
import lockIcon from '../../assets/lists/icon-lock.svg'
import editIcon from '../../assets/lists/icon-edit.svg'
import trashIcon from '../../assets/lists/icon-trash.svg'
import plusIcon from '../../assets/lists/icon-plus.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import { useApi } from '../../lib/api.ts'
import type { ApiList } from '../../lib/types.ts'
import { useWatchlist } from '../../lib/watchlist.ts'
import { posterGradients } from '../Profile/profileData.ts'
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

const SKELETONS = [0, 1, 2]

/**
 * Every member's default Watchlist, always first in My lists. It can't be edited or deleted, only
 * filled from movie pages ("Add to watchlist") and Home's Tonight's pick.
 */
function WatchlistCard() {
  const { loaded, queued } = useWatchlist()
  const coverIds = queued.slice(0, 3).map((item) => item.tmdb_id)
  const films = queued.length

  return (
    <li className={styles.card}>
      <Link to="/lists/watchlist" className={styles.cover} style={{ background: 'var(--surface-yellow-tint)' }} aria-label="Open your Watchlist">
        <ListCover tmdbIds={coverIds} gradients={posterGradients(coverIds.length ? coverIds : [0])} />
      </Link>
      <div className={styles.titleRow}>
        <Link to="/lists/watchlist" className={styles.cardTitle}>
          Watchlist
        </Link>
      </div>
      <p className={styles.description}>Films you want to watch, added from movie pages and Tonight’s pick on Home.</p>
      <div className={styles.footer}>
        <span className={styles.count}>{loaded ? `${films} ${films === 1 ? 'film' : 'films'} to watch` : 'Loading…'}</span>
      </div>
    </li>
  )
}

/** A shimmering placeholder shaped like a My lists card, shown while the lists load. */
function ListCardSkeleton() {
  return (
    <li className={`${styles.card} ${styles.skeletonCard}`} aria-hidden="true">
      <span className={`${styles.cover} ${styles.skeleton}`} />
      <span className={`${styles.skeleton} ${styles.skeletonTitle}`} />
      <span className={`${styles.skeleton} ${styles.skeletonText}`} />
      <span className={`${styles.skeleton} ${styles.skeletonMeta}`} />
    </li>
  )
}

/** A shimmering placeholder shaped like a public list card. */
function PublicCardSkeleton() {
  return (
    <li aria-hidden="true">
      <span className={`${styles.publicCard} ${styles.skeletonCard}`}>
        <span className={`${styles.publicCover} ${styles.skeleton}`} />
        <span className={`${styles.skeleton} ${styles.skeletonTitle}`} />
        <span className={`${styles.skeleton} ${styles.skeletonMeta}`} />
      </span>
    </li>
  )
}

function PublicListCard({ list }: { list: PublicList }) {
  return (
    <Link to={`/lists/${list.slug}`} className={styles.publicCard}>
      <span className={styles.publicCover}>
        <ListCover tmdbIds={list.posterIds} gradients={list.posters} size="sm" />
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
  const publicQuery = useApi<{ lists: ApiList[] }>('/lists?scope=public')
  const followingQuery = useApi<{ lists: ApiList[] }>('/lists?scope=following')
  const publicLists = (publicQuery.data?.lists ?? []).map(toPublicList)
  const followingLists = (followingQuery.data?.lists ?? []).map(toPublicList)
  // Still waiting for the first answer (a failed load shows the empty message instead of shimmering forever).
  const publicLoading = !publicQuery.data && !publicQuery.error
  const followingLoading = !followingQuery.data && !followingQuery.error
  const tabLoading = tab === 'public' ? publicLoading : followingLoading
  // The list waiting for "Delete list?"; `open` turns false first so the dialog can animate out.
  const [confirming, setConfirming] = useState<{ list: MyList; open: boolean } | null>(null)
  // The card fading out after a successful delete.
  const [leavingId, setLeavingId] = useState<number | null>(null)
  const [toast, setToast] = useState<{ id: number; message: string } | null>(null)

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
    // +1 for the default Watchlist.
    { id: 'mine', label: 'My lists', count: myLists.length + 1 },
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
          <ul className={styles.grid} data-enter data-enter-delay="0.25" aria-busy={!loaded}>
            <WatchlistCard />
            {!loaded && SKELETONS.map((n) => <ListCardSkeleton key={n} />)}
            {myLists.map((list) => (
              <li key={list.id} className={list.id === leavingId ? `${styles.card} ${styles.cardLeaving}` : styles.card}>
                <Link
                  to={`/lists/${list.slug}`}
                  className={styles.cover}
                  style={{ background: list.cover }}
                  aria-label={`Open ${list.title}`}
                >
                  <ListCover tmdbIds={list.posterIds} gradients={list.posters} />
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
            <li className={styles.newCell} hidden={!loaded}>
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
          <ul className={styles.publicGrid} aria-busy={tabLoading}>
            {tabLoading && SKELETONS.map((n) => <PublicCardSkeleton key={n} />)}
            {(tab === 'public' ? publicLists : followingLists).map((list) => (
              <li key={list.id}>
                <PublicListCard list={list} />
              </li>
            ))}
            {!tabLoading && (tab === 'public' ? publicLists : followingLists).length === 0 && (
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
            <ul className={styles.publicGrid} aria-busy={publicLoading}>
              {publicLoading && SKELETONS.map((n) => <PublicCardSkeleton key={n} />)}
              {publicLists.slice(0, 3).map((list) => (
                <li key={list.id}>
                  <PublicListCard list={list} />
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
              <ListCover tmdbIds={confirming.list.posterIds} gradients={confirming.list.posters} size="sm" />
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
