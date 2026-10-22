import { Link, NavLink, Outlet } from 'react-router'
import Logo from '../../components/Logo/Logo.tsx'
import gridIcon from '../../assets/admin/icon-grid.svg'
import usersIcon from '../../assets/admin/icon-users.svg'
import chatIcon from '../../assets/admin/icon-chat.svg'
import filmIcon from '../../assets/admin/icon-film.svg'
import chartIcon from '../../assets/admin/icon-chart.svg'
import flagIcon from '../../assets/admin/icon-flag.svg'
import settingsIcon from '../../assets/admin/icon-settings.svg'
import logoutIcon from '../../assets/admin/icon-logout.svg'
import { ADMIN_USER } from './adminData.ts'
import styles from './AdminLayout.module.css'

// Screens with a Figma design. Genres, Reports and Settings appear in the sidebar but have no screen yet.
const LINKS = [
  { to: '/admin', label: 'Overview', icon: gridIcon, end: true },
  { to: '/admin/users', label: 'Users', icon: usersIcon },
  { to: '/admin/reviews', label: 'Reviews', icon: chatIcon },
  { to: '/admin/movies', label: 'Movies', icon: filmIcon },
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
 * TODO: guard these routes so only signed-in administrators can open them once auth is connected.
 */
export default function AdminLayout() {
  return (
    <div className={styles.shell}>
      <aside className={styles.sidebar}>
        <Logo theme="dark" />
        <nav className={styles.nav} aria-label="Admin">
          {LINKS.map((link) => (
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
          {UPCOMING.map((item) => (
            <span key={item.label} className={styles.itemDisabled} aria-disabled="true" title="Coming soon">
              <MaskIcon src={item.icon} />
              {item.label}
            </span>
          ))}
        </nav>

        <div className={styles.admin}>
          <span className={styles.avatar} aria-hidden="true">
            {ADMIN_USER.initials}
          </span>
          <span className={styles.who}>
            <span className={styles.name}>{ADMIN_USER.name}</span>
            <span className={styles.role}>{ADMIN_USER.role}</span>
          </span>
          {/* TODO: call the logout endpoint once auth is connected. */}
          <Link to="/signin" className={styles.logout} aria-label="Log out">
            <MaskIcon src={logoutIcon} size={18} />
          </Link>
        </div>
      </aside>

      <main className={styles.main}>
        <Outlet />
      </main>
    </div>
  )
}
