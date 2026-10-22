import { useRef, useState } from 'react'
import { Link, Outlet } from 'react-router'
import AppNav from '../../components/AppNav/AppNav.tsx'
import ButtonPrimary from '../../components/ButtonPrimary/ButtonPrimary.tsx'
import PosterCollage from '../../components/PosterCollage/PosterCollage.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import globeIcon from '../../assets/lists/icon-globe.svg'
import lockIcon from '../../assets/lists/icon-lock.svg'
import editIcon from '../../assets/lists/icon-edit.svg'
import trashIcon from '../../assets/lists/icon-trash.svg'
import plusIcon from '../../assets/lists/icon-plus.svg'
import heartIcon from '../../assets/lists/icon-heart.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import { FOLLOWING_COUNT, PUBLIC_LISTS, type PublicList } from './listsData.ts'
import { deleteMyList, useMyLists } from './myListsStore.ts'
import styles from './Lists.module.css'

type Tab = 'mine' | 'public' | 'following'

function PublicListCard({ list }: { list: PublicList }) {
  return (
    <Link to={`/lists/${list.slug}`} className={styles.publicCard}>
      <span className={styles.publicCover}>
        <PosterCollage gradients={list.posters} size="sm" />
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
          <span className={styles.likes}>
            <img src={heartIcon} width={14} height={14} alt="" />
            {list.likes}
            <span className="sr-only"> likes</span>
          </span>
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

  const myLists = useMyLists()
  const [tab, setTab] = useState<Tab>('mine')

  const remove = (slug: string, title: string) => {
    if (window.confirm(`Delete “${title}”? This can't be undone.`)) {
      deleteMyList(slug)
    }
  }

  const browseAll = () => {
    setTab('public')
    tabsRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }

  const tabs: { id: Tab; label: string; count?: number }[] = [
    { id: 'mine', label: 'My lists', count: myLists.length },
    { id: 'public', label: 'Public lists' },
    { id: 'following', label: 'Following', count: FOLLOWING_COUNT },
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
            {myLists.map((list) => (
              <li key={list.slug} className={styles.card}>
                <Link
                  to={`/lists/${list.slug}`}
                  className={styles.cover}
                  style={{ background: list.cover }}
                  aria-label={`Open ${list.title}`}
                >
                  <PosterCollage gradients={list.posters} />
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
                      onClick={() => remove(list.slug, list.title)}
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
            {PUBLIC_LISTS.filter((list) => tab === 'public' || list.following).map((list) => (
              <li key={list.slug}>
                <PublicListCard list={list} />
              </li>
            ))}
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
              {PUBLIC_LISTS.map((list) => (
                <li key={list.slug}>
                  <PublicListCard list={list} />
                </li>
              ))}
            </ul>
          </section>
        )}
      </main>

      {/* Create/Edit List dialog (routes lists/new and lists/:slug/edit). */}
      <Outlet />
    </div>
  )
}
