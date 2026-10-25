import { useEffect, useRef, useState, type FormEvent, type KeyboardEvent } from 'react'
import { Link, NavLink, useLocation, useNavigate } from 'react-router'
import Logo from '../Logo/Logo.tsx'
import AvatarImage from '../AvatarImage/AvatarImage.tsx'
import FollowButton from '../FollowButton/FollowButton.tsx'
import searchIcon from '../../assets/home/icon-search.svg'
import bellIcon from '../../assets/home/icon-bell.svg'
import { useNotificationPolling, useUnreadCount } from '../../pages/Notifications/notificationsStore.ts'
import { useAuth } from '../../contexts/useAuth.ts'
import { api } from '../../lib/api.ts'
import { colorOf, initialsOf } from '../../lib/format.ts'
import type { ApiUserSearch } from '../../lib/types.ts'
import styles from './AppNav.module.css'

/** `alsoActiveOn`: other sections that belong to this link, e.g. actor pages opened from Discover. */
const LINKS: { to: string; label: string; alsoActiveOn?: string[] }[] = [
  { to: '/home-page', label: 'Home' },
  { to: '/discover', label: 'Discover', alsoActiveOn: ['/people/'] },
  { to: '/for-you', label: 'For You' },
  { to: '/lists', label: 'Lists' },
  { to: '/movie-nights', label: 'Movie Nights' },
  { to: '/rooms', label: 'Rooms' },
]

/** Wait this long after the last keystroke before searching people. */
const SEARCH_DELAY_MS = 250
const PEOPLE_SHOWN = 5

/** Signed-in app header (Figma "Nav/App"): logo, section links, search, notifications, avatar. */
export default function AppNav() {
  const navigate = useNavigate()
  const { pathname } = useLocation()
  const { user } = useAuth()
  const [query, setQuery] = useState('')
  const [open, setOpen] = useState(false)
  // People matching the query, tagged with the query they answer so stale results are ignored.
  const [people, setPeople] = useState<{ q: string; data: ApiUserSearch } | null>(null)
  const searchRef = useRef<HTMLDivElement>(null)
  useNotificationPolling()
  const unread = useUnreadCount()

  const q = query.trim()

  useEffect(() => {
    if (q.length < 2) return
    const controller = new AbortController()
    const timer = window.setTimeout(() => {
      api
        .get<ApiUserSearch>('/users', { params: { q, limit: PEOPLE_SHOWN }, signal: controller.signal })
        .then((response) => setPeople({ q, data: response.data }))
        .catch(() => undefined)
    }, SEARCH_DELAY_MS)
    return () => {
      window.clearTimeout(timer)
      controller.abort()
    }
  }, [q])

  // Close the dropdown on a click outside the search.
  useEffect(() => {
    if (!open) return
    const onPointerDown = (event: PointerEvent) => {
      if (!searchRef.current?.contains(event.target as Node)) setOpen(false)
    }
    document.addEventListener('pointerdown', onPointerDown)
    return () => document.removeEventListener('pointerdown', onPointerDown)
  }, [open])

  const go = (to: string) => {
    setOpen(false)
    navigate(to)
  }

  // Enter searches movies on Discover; people are one click away in the dropdown.
  const handleSearch = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    go(q ? `/discover?q=${encodeURIComponent(q)}` : '/discover')
  }

  const handleKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (event.key === 'Escape') setOpen(false)
  }

  const results = people && people.q === q ? people.data : null
  const showPanel = open && q.length >= 2

  return (
    <header className={styles.nav}>
      <div className={styles.left}>
        <Logo />
        <nav className={styles.links} aria-label="Main">
          {LINKS.map((link) => (
            <NavLink
              key={link.to}
              to={link.to}
              className={({ isActive }) =>
                isActive || link.alsoActiveOn?.some((path) => pathname.startsWith(path)) ? styles.linkActive : styles.link
              }
            >
              {link.label}
            </NavLink>
          ))}
        </nav>
      </div>
      <div className={styles.right}>
        <div ref={searchRef} className={styles.searchWrap} onKeyDown={handleKeyDown}>
          <form className={styles.search} role="search" onSubmit={handleSearch}>
            <img src={searchIcon} width={18} height={18} alt="" />
            <input
              className={styles.searchInput}
              type="search"
              value={query}
              onChange={(event) => {
                setQuery(event.target.value)
                setOpen(true)
              }}
              onFocus={() => setOpen(true)}
              placeholder="Search movies, people…"
              aria-label="Search movies and people"
              aria-expanded={showPanel}
              aria-controls="nav-search-results"
              autoComplete="off"
            />
          </form>

          {showPanel && (
            <div id="nav-search-results" className={styles.results} data-lenis-prevent>
              <button type="button" className={styles.resultAction} onClick={() => go(`/discover?q=${encodeURIComponent(q)}`)}>
                <img src={searchIcon} width={16} height={16} alt="" />
                Search movies for “{q}”
              </button>

              <p className={styles.resultsLabel}>People</p>
              {!results && <p className={styles.resultsNote}>Searching…</p>}
              {results && results.users.length === 0 && <p className={styles.resultsNote}>No one matches “{q}”.</p>}
              <ul className={styles.people}>
                {results?.users.map((person) => (
                  <li key={person.id} className={styles.person}>
                    <Link to={`/profile/${person.username}`} className={styles.personLink} onClick={() => setOpen(false)}>
                      <span className={styles.personAvatar} style={{ background: colorOf(person.id) }} aria-hidden="true">
                        <AvatarImage className={styles.avatarImg} src={person.avatar} fallback={initialsOf(person.name)} />
                      </span>
                      <span className={styles.personText}>
                        <span className={styles.personName}>{person.name}</span>
                        <span className={styles.personHandle}>
                          @{person.username} · {person.followers} follower{person.followers === 1 ? '' : 's'}
                        </span>
                      </span>
                    </Link>
                    <FollowButton
                      key={`${person.id}-${person.is_following}`}
                      username={person.username}
                      following={person.is_following}
                      followsYou={person.follows_you}
                    />
                  </li>
                ))}
              </ul>
              {results && results.total > results.users.length && (
                <button
                  type="button"
                  className={styles.resultAction}
                  onClick={() => go(`/discover?q=${encodeURIComponent(q)}&type=people`)}
                >
                  See all {results.total} people →
                </button>
              )}
            </div>
          )}
        </div>
        <NavLink
          to="/notifications"
          className={({ isActive }) => (isActive ? `${styles.iconButton} ${styles.iconButtonActive}` : styles.iconButton)}
          aria-label={unread > 0 ? `Notifications, ${unread} unread` : 'Notifications'}
        >
          <img src={bellIcon} width={20} height={20} alt="" />
          {unread > 0 && (
            <span className={styles.badge} aria-hidden="true">
              {unread > 9 ? '9+' : unread}
            </span>
          )}
        </NavLink>
        <Link
          to="/profile"
          className={styles.avatar}
          style={user ? { background: colorOf(user.id) } : undefined}
          aria-label="Your profile"
        >
          <AvatarImage className={styles.avatarImg} src={user?.avatar} fallback={initialsOf(user?.name ?? '')} />
        </Link>
      </div>
    </header>
  )
}
