import { useState, type FormEvent } from 'react'
import { Link, NavLink, useNavigate } from 'react-router'
import Logo from '../Logo/Logo.tsx'
import searchIcon from '../../assets/home/icon-search.svg'
import bellIcon from '../../assets/home/icon-bell.svg'
import { useUnreadCount } from '../../pages/Notifications/notificationsStore.ts'
import styles from './AppNav.module.css'

const LINKS = [
  { to: '/home-page', label: 'Home' },
  { to: '/discover', label: 'Discover' },
  { to: '/for-you', label: 'For You' },
  { to: '/lists', label: 'Lists' },
  { to: '/movie-nights', label: 'Movie Nights' },
  { to: '/rooms', label: 'Rooms' },
]

type AppNavProps = {
  /** Initials shown in the avatar (until auth provides the signed-in user). */
  initials?: string
}

/** Signed-in app header (Figma "Nav/App"): logo, section links, search, notifications, avatar. */
export default function AppNav({ initials = 'MR' }: AppNavProps) {
  const navigate = useNavigate()
  const [query, setQuery] = useState('')
  const unread = useUnreadCount()

  const handleSearch = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const q = query.trim()
    navigate(q ? `/discover?q=${encodeURIComponent(q)}` : '/discover')
  }

  return (
    <header className={styles.nav}>
      <div className={styles.left}>
        <Logo />
        <nav className={styles.links} aria-label="Main">
          {LINKS.map((link) => (
            <NavLink key={link.to} to={link.to} className={({ isActive }) => (isActive ? styles.linkActive : styles.link)}>
              {link.label}
            </NavLink>
          ))}
        </nav>
      </div>
      <div className={styles.right}>
        <form className={styles.search} role="search" onSubmit={handleSearch}>
          <img src={searchIcon} width={18} height={18} alt="" />
          <input
            className={styles.searchInput}
            type="search"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="Search movies, people…"
            aria-label="Search movies and people"
          />
        </form>
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
        <Link to="/profile" className={styles.avatar} aria-label="Your profile">
          {initials}
        </Link>
      </div>
    </header>
  )
}
