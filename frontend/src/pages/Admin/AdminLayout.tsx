import { useState } from 'react'
import { Link, NavLink, Outlet, useNavigate } from 'react-router'
import { useAuth } from '../../contexts/useAuth.ts'
import Toast from '../../components/Toast/Toast.tsx'
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

// Route access mirrors the backend: shared moderation tools plus administrator-only configuration.
const LINKS: { to: string; label: string; icon: string; end?: boolean; adminOnly?: boolean }[] = [
  { to: '/admin', label: 'Overview', icon: gridIcon, end: true },
  { to: '/admin/users', label: 'Users', icon: usersIcon },
  { to: '/admin/reviews', label: 'Reviews', icon: chatIcon },
  { to: '/admin/reports', label: 'Reports', icon: flagIcon },
  { to: '/admin/ratings', label: 'Ratings', icon: chartIcon },
  { to: '/admin/lists', label: 'Lists', icon: filmIcon },
  { to: '/admin/rooms', label: 'Rooms', icon: chatIcon },
  { to: '/admin/genres', label: 'Genres', icon: chartIcon },
  { to: '/admin/settings', label: 'Settings', icon: settingsIcon, adminOnly: true },
  { to: '/admin/audit-log', label: 'Audit history', icon: chartIcon, adminOnly: true },
  { to: '/admin/movies', label: 'Movies', icon: filmIcon, adminOnly: true },
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
  const [toast, setToast] = useState<{ id: number; message: string } | null>(null)
  const signOut = () => {
    logout().then(
      () => navigate('/signin', { replace: true }),
      () => setToast({ id: Date.now(), message: "Couldn't sign out. Please try again." }),
    )
  }
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
        </nav>

        <div className={styles.admin}>
          <span className={styles.avatar} aria-hidden="true">
            {initialsOf(user?.name ?? '')}
          </span>
          <span className={styles.who}>
            <span className={styles.name}>{user?.name}</span>
            <span className={styles.role}>{isAdmin ? 'Administrator' : 'Moderator'}</span>
          </span>
          <button type="button" onClick={signOut} className={styles.logout} aria-label="Log out">
            <MaskIcon src={logoutIcon} size={18} />
          </button>
        </div>
      </aside>

      <main className={styles.main}>
        <Outlet />
      </main>
      {toast && <Toast key={toast.id} message={toast.message} onDone={() => setToast(null)} />}
    </div>
  )
}
