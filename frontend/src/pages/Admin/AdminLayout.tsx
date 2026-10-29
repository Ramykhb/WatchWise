import { Link, NavLink, Outlet, useNavigate } from 'react-router'
import { useAuth } from '../../contexts/useAuth.ts'
import Logo from '../../components/Logo/Logo.tsx'
import gridIcon from '../../assets/admin/icon-grid.svg'
import usersIcon from '../../assets/admin/icon-users.svg'
import chatIcon from '../../assets/admin/icon-chat.svg'
import filmIcon from '../../assets/admin/icon-film.svg'
import chartIcon from '../../assets/admin/icon-chart.svg'
import flagIcon from '../../assets/admin/icon-flag.svg'
import settingsIcon from '../../assets/admin/icon-settings.svg'
import logoutIcon from '../../assets/admin/icon-logout.svg'
import { isAdminRole } from '../../lib/adminApi.ts'
import { initialsOf } from '../../lib/format.ts'
import styles from './AdminLayout.module.css'

// Screens with a Figma design. Genres, Reports and Settings appear in the sidebar but have no screen yet.
const LINKS: { to: string; label: string; icon: string; end?: boolean; adminOnly?: boolean }[] = [
  { to: '/admin', label: 'Overview', icon: gridIcon, end: true },
  { to: '/admin/users', label: 'Users', icon: usersIcon },
  { to: '/admin/reviews', label: 'Reviews', icon: chatIcon },
  { to: '/admin/movies', label: 'Movies', icon: filmIcon, adminOnly: true },
]
const UPCOMING = [
  { label: 'Genres', icon: chartIcon },
  { label: 'Reports', icon: flagIcon },
  { label: 'Settings', icon: settingsIcon },
]

/** The icon is a CSS mask filled with the text colour, so it follows the active/inactive colour exactly. */
function MaskIcon({ src, size = 20 }: { src: string; size?: number }) {
  return (
    <span
      className={styles.icon}
      style={{ width: size, height: size, maskImage: `url("${src}")`, WebkitMaskImage: `url("${src}")` }}
      aria-hidden="true"
    />
  )
}

/**
 * Admin dashboard shell: violet sidebar (Figma "Admin/Sidebar") and the page on the right.
 * Only administrators and moderators reach it (see RequireAdmin in the router).
 */
export default function AdminLayout() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const isAdmin = isAdminRole(user?.role)
  const links = LINKS.filter((link) => isAdmin || !link.adminOnly)
  return (
    <div className={styles.shell}>
      <aside className={styles.sidebar}>
        <Logo theme="dark" />
        <nav className={styles.nav} aria-label="Admin">
          <Link to="/home-page" className={styles.item}>
            <span aria-hidden="true">←</span>
            Back to WatchWise
          </Link>
          {links.map((link) => (
            <NavLink
              key={link.to}
              to={link.to}
              end={link.end}
              className={({ isActive }) => (isActive ? styles.itemActive : styles.item)}
            >
              <MaskIcon src={link.icon} />
              {link.label}
            </NavLink>
          ))}
          {isAdmin && UPCOMING.map((item) => (
            <span key={item.label} className={styles.itemDisabled} aria-disabled="true" title="Coming soon">
              <MaskIcon src={item.icon} />
              {item.label}
            </span>
          ))}
        </nav>

        <div className={styles.admin}>
          <span className={styles.avatar} aria-hidden="true">
            {initialsOf(user?.name ?? '')}
          </span>
          <span className={styles.who}>
            <span className={styles.name}>{user?.name}</span>
            <span className={styles.role}>{isAdmin ? 'Administrator' : 'Moderator'}</span>
          </span>
          <button type="button" onClick={() => void logout().then(() => navigate('/signin', { replace: true }))} className={styles.logout} aria-label="Log out">
            <MaskIcon src={logoutIcon} size={18} />
          </button>
        </div>
      </aside>

      <main className={styles.main}>
        <Outlet />
      </main>
    </div>
  )
}
