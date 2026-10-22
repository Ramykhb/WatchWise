import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router'
import searchIcon from '../../assets/listdetail/icon-search.svg'
import chevDownIcon from '../../assets/watchlist/icon-chev-down.svg'
import mailIcon from '../../assets/admin/icon-mail.svg'
import mailSmIcon from '../../assets/admin/icon-mail-sm.svg'
import banWhiteIcon from '../../assets/admin/icon-ban-white.svg'
import checkIcon from '../../assets/admin/icon-check-sm.svg'
import editIcon from '../../assets/movie/icon-edit.svg'
import banIcon from '../../assets/admin/icon-ban.svg'
import dotsIcon from '../../assets/admin/icon-dots.svg'
import chevLeftIcon from '../../assets/admin/icon-chev-left.svg'
import chevRightIcon from '../../assets/admin/icon-chev-right.svg'
import {
  USERS,
  USER_ROLES,
  USER_STATUSES,
  USER_TOTALS,
  type AdminUserRow,
  type UserRole,
  type UserStatus,
} from './adminData.ts'
import styles from './AdminUsers.module.css'

const ROLE_CLASS: Record<UserRole, string> = {
  Member: styles.chipCream,
  Moderator: styles.chipYellow,
  Admin: styles.chipViolet,
}
const STATUS_CLASS: Record<UserStatus, string> = {
  Active: styles.chipMint,
  Unverified: styles.chipYellow,
  Suspended: styles.chipCoral,
}

const fmt = (n: number) => n.toLocaleString('en-US')

/** Page buttons around the current page, e.g. 1 2 3 … 6,888. */
function pageItems(page: number, total: number): (number | '…')[] {
  if (page <= 3) return [1, 2, 3, '…', total]
  if (page >= total - 2) return [1, '…', total - 2, total - 1, total]
  return [1, '…', page, '…', total]
}

type Menu = { id: string; kind: 'role' | 'more' } | null

export default function AdminUsers() {
  const [searchParams] = useSearchParams()
  // TODO: load, filter and page users through the admin API; changes below are local only.
  const [users, setUsers] = useState(USERS)
  const [query, setQuery] = useState(searchParams.get('q') ?? '')
  const [role, setRole] = useState<UserRole | 'All'>('All')
  const [status, setStatus] = useState<UserStatus | 'All'>('All')
  const [selected, setSelected] = useState<string[]>([])
  const [menu, setMenu] = useState<Menu>(null)
  const [page, setPage] = useState(1)
  const [note, setNote] = useState('')

  // Close an open row menu on outside click or Escape.
  useEffect(() => {
    if (!menu) return
    const close = (event: Event) => {
      if (event instanceof KeyboardEvent && event.key !== 'Escape') return
      if (event.type === 'mousedown' && (event.target as HTMLElement).closest('[data-menu]')) return
      setMenu(null)
    }
    document.addEventListener('mousedown', close)
    document.addEventListener('keydown', close)
    return () => {
      document.removeEventListener('mousedown', close)
      document.removeEventListener('keydown', close)
    }
  }, [menu])

  const q = query.trim().toLowerCase()
  const shown = users.filter(
    (u) =>
      (!q || u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q)) &&
      (role === 'All' || u.role === role) &&
      (status === 'All' || u.status === status),
  )
  const allShownSelected = shown.length > 0 && shown.every((u) => selected.includes(u.id))
  const selectedUsers = users.filter((u) => selected.includes(u.id))

  const toggle = (id: string) => setSelected((list) => (list.includes(id) ? list.filter((x) => x !== id) : [...list, id]))
  const toggleAll = () =>
    setSelected((list) =>
      allShownSelected ? list.filter((id) => !shown.some((u) => u.id === id)) : [...new Set([...list, ...shown.map((u) => u.id)])],
    )

  const update = (ids: string[], patch: Partial<AdminUserRow>) =>
    setUsers((list) => list.map((u) => (ids.includes(u.id) ? { ...u, ...patch } : u)))

  const toggleSuspend = (user: AdminUserRow) => {
    const suspending = user.status !== 'Suspended'
    if (suspending && !window.confirm(`Suspend ${user.name}? They won't be able to sign in or post.`)) return
    update([user.id], { status: suspending ? 'Suspended' : 'Active' })
    setNote(`${user.name} ${suspending ? 'suspended' : 'reinstated'}`)
  }

  const suspendSelected = () => {
    if (!window.confirm(`Suspend ${selectedUsers.length} user${selectedUsers.length > 1 ? 's' : ''}?`)) return
    update(selected, { status: 'Suspended' })
    setNote(`${selectedUsers.length} user${selectedUsers.length > 1 ? 's' : ''} suspended`)
    setSelected([])
  }

  const emailSelected = () => {
    window.location.href = `mailto:?bcc=${selectedUsers.map((u) => u.email).join(',')}`
  }

  const inviteAdmin = () => {
    const email = window.prompt('Invite an admin — email address:')?.trim()
    if (!email) return
    if (!/^\S+@\S+\.\S+$/.test(email)) {
      setNote('That email address looks wrong')
      return
    }
    // TODO: POST /admin/invitations once the API exists.
    const name = email.split('@')[0]
    setUsers((list) => [
      ...list,
      {
        id: `invite-${Date.now()}`,
        initials: name.slice(0, 2).toUpperCase(),
        color: 'var(--surface-violet-tint)',
        name,
        email,
        role: 'Admin',
        status: 'Unverified',
        joined: 'Invited',
        reviews: 0,
        lastActive: '—',
      },
    ])
    setNote(`Invitation sent to ${email}`)
  }

  const copyEmail = async (user: AdminUserRow) => {
    try {
      await navigator.clipboard.writeText(user.email)
      setNote(`Copied ${user.email}`)
    } catch {
      setNote('')
    }
    setMenu(null)
  }

  const summary: { label: string; value: number; className: string; onClick: () => void; pressed: boolean }[] = [
    { label: 'Total', value: USER_TOTALS.total, className: styles.chipWhite, onClick: () => (setRole('All'), setStatus('All')), pressed: false },
    { label: 'Active', value: USER_TOTALS.active, className: styles.chipMint, onClick: () => setStatus('Active'), pressed: status === 'Active' },
    { label: 'Unverified', value: USER_TOTALS.unverified, className: styles.chipYellow, onClick: () => setStatus('Unverified'), pressed: status === 'Unverified' },
    { label: 'Suspended', value: USER_TOTALS.suspended, className: styles.chipCoral, onClick: () => setStatus('Suspended'), pressed: status === 'Suspended' },
    { label: 'Admins', value: USER_TOTALS.admins, className: styles.chipViolet, onClick: () => setRole('Admin'), pressed: role === 'Admin' },
  ]

  return (
    <div className={styles.page}>
      <header className={styles.topBar}>
        <div className={styles.titleBlock}>
          <h1 className={styles.title}>Users</h1>
          <p className={styles.lead}>Manage accounts, roles and access.</p>
        </div>
        <div className={styles.controls}>
          <label className={styles.search}>
            <img src={searchIcon} width={16} height={16} alt="" />
            <span className="sr-only">Search users</span>
            <input
              type="search"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Search name or email"
            />
          </label>
          <label className={styles.select}>
            <span className="sr-only">Filter by role</span>
            <select value={role} onChange={(event) => setRole(event.target.value as UserRole | 'All')}>
              <option value="All">Role: All</option>
              {USER_ROLES.map((r) => (
                <option key={r} value={r}>
                  Role: {r}
                </option>
              ))}
            </select>
            <img src={chevDownIcon} width={14} height={14} alt="" />
          </label>
          <label className={styles.select}>
            <span className="sr-only">Filter by status</span>
            <select value={status} onChange={(event) => setStatus(event.target.value as UserStatus | 'All')}>
              <option value="All">Status: All</option>
              {USER_STATUSES.map((s) => (
                <option key={s} value={s}>
                  Status: {s}
                </option>
              ))}
            </select>
            <img src={chevDownIcon} width={14} height={14} alt="" />
          </label>
          <button type="button" className={styles.invite} onClick={inviteAdmin}>
            <img src={mailIcon} width={16} height={16} alt="" />
            Invite admin
          </button>
        </div>
      </header>

      <div className={styles.summary} role="group" aria-label="Quick filters">
        {summary.map((chip) => (
          <button key={chip.label} type="button" className={chip.className} aria-pressed={chip.pressed} onClick={chip.onClick}>
            {chip.label} {fmt(chip.value)}
          </button>
        ))}
        <span className={styles.note} role="status">
          {note}
        </span>
      </div>

      {selected.length > 0 && (
        <div className={styles.bulk}>
          <p className={styles.bulkCount}>
            {selected.length} user{selected.length > 1 ? 's' : ''} selected
          </p>
          <button type="button" className={styles.bulkEmail} onClick={emailSelected}>
            <img src={mailSmIcon} width={14} height={14} alt="" />
            Email
          </button>
          <button type="button" className={styles.bulkSuspend} onClick={suspendSelected}>
            <img src={banWhiteIcon} width={14} height={14} alt="" />
            Suspend
          </button>
        </div>
      )}

      <section className={styles.tableCard} aria-label="Users">
        <div className={styles.scroll}>
          <table className={styles.table}>
            <thead>
              <tr>
                <th className={styles.colCheck}>
                  <label className={styles.checkbox}>
                    <input type="checkbox" checked={allShownSelected} onChange={toggleAll} aria-label="Select all users on this page" />
                    <span aria-hidden="true">
                      <img src={checkIcon} width={12} height={12} alt="" />
                    </span>
                  </label>
                </th>
                <th className={styles.colUser}>USER</th>
                <th className={styles.colRole}>ROLE</th>
                <th className={styles.colStatus}>STATUS</th>
                <th className={styles.colJoined}>JOINED</th>
                <th className={styles.colReviews}>REVIEWS</th>
                <th className={styles.colActive}>LAST ACTIVE</th>
                <th className={styles.colActions}>ACTIONS</th>
              </tr>
            </thead>
            <tbody>
              {shown.length === 0 && (
                <tr>
                  <td colSpan={8} className={styles.empty}>
                    No users match these filters.
                  </td>
                </tr>
              )}
              {shown.map((user) => {
                const isSelected = selected.includes(user.id)
                return (
                  <tr key={user.id} className={isSelected ? styles.rowSelected : undefined}>
                    <td>
                      <label className={styles.checkbox}>
                        <input
                          type="checkbox"
                          checked={isSelected}
                          onChange={() => toggle(user.id)}
                          aria-label={`Select ${user.name}`}
                        />
                        <span aria-hidden="true">
                          <img src={checkIcon} width={12} height={12} alt="" />
                        </span>
                      </label>
                    </td>
                    <td>
                      <span className={styles.user}>
                        <span className={styles.avatar} style={{ background: user.color }} aria-hidden="true">
                          {user.initials}
                        </span>
                        <span className={styles.userText}>
                          <span className={styles.userName}>{user.name}</span>
                          <span className={styles.userEmail}>{user.email}</span>
                        </span>
                      </span>
                    </td>
                    <td>
                      <span className={ROLE_CLASS[user.role]}>{user.role}</span>
                    </td>
                    <td>
                      <span className={STATUS_CLASS[user.status]}>{user.status}</span>
                    </td>
                    <td className={styles.muted}>{user.joined}</td>
                    <td className={styles.strong}>{fmt(user.reviews)}</td>
                    <td className={styles.muted}>{user.lastActive}</td>
                    <td>
                      <div className={styles.actions}>
                        <div className={styles.menuWrap} data-menu>
                          <button
                            type="button"
                            className={styles.actionCream}
                            aria-label={`Change ${user.name}'s role`}
                            aria-expanded={menu?.id === user.id && menu.kind === 'role'}
                            onClick={() => setMenu(menu?.id === user.id && menu.kind === 'role' ? null : { id: user.id, kind: 'role' })}
                          >
                            <img src={editIcon} width={14} height={14} alt="" />
                          </button>
                          {menu?.id === user.id && menu.kind === 'role' && (
                            <ul className={styles.menu} role="menu" aria-label="Role">
                              {USER_ROLES.map((r) => (
                                <li key={r}>
                                  <button
                                    type="button"
                                    role="menuitemradio"
                                    aria-checked={user.role === r}
                                    className={styles.menuItem}
                                    onClick={() => {
                                      update([user.id], { role: r })
                                      setNote(`${user.name} is now ${r === 'Admin' ? 'an' : 'a'} ${r}`)
                                      setMenu(null)
                                    }}
                                  >
                                    {r}
                                  </button>
                                </li>
                              ))}
                            </ul>
                          )}
                        </div>
                        <button
                          type="button"
                          className={user.status === 'Suspended' ? styles.actionCoralOn : styles.actionCoral}
                          aria-pressed={user.status === 'Suspended'}
                          aria-label={user.status === 'Suspended' ? `Reinstate ${user.name}` : `Suspend ${user.name}`}
                          onClick={() => toggleSuspend(user)}
                        >
                          <img src={banIcon} width={14} height={14} alt="" />
                        </button>
                        <div className={styles.menuWrap} data-menu>
                          <button
                            type="button"
                            className={styles.actionCream}
                            aria-label={`More actions for ${user.name}`}
                            aria-expanded={menu?.id === user.id && menu.kind === 'more'}
                            onClick={() => setMenu(menu?.id === user.id && menu.kind === 'more' ? null : { id: user.id, kind: 'more' })}
                          >
                            <img src={dotsIcon} width={14} height={14} alt="" />
                          </button>
                          {menu?.id === user.id && menu.kind === 'more' && (
                            <ul className={styles.menu} role="menu" aria-label="More actions">
                              <li>
                                <a className={styles.menuItem} role="menuitem" href={`mailto:${user.email}`}>
                                  Email user
                                </a>
                              </li>
                              <li>
                                <button type="button" role="menuitem" className={styles.menuItem} onClick={() => copyEmail(user)}>
                                  Copy email
                                </button>
                              </li>
                              {user.status === 'Unverified' && (
                                <li>
                                  <button
                                    type="button"
                                    role="menuitem"
                                    className={styles.menuItem}
                                    onClick={() => {
                                      setNote(`Verification email re-sent to ${user.email}`)
                                      setMenu(null)
                                    }}
                                  >
                                    Resend verification
                                  </button>
                                </li>
                              )}
                            </ul>
                          )}
                        </div>
                      </div>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>

        <div className={styles.pagination}>
          <p className={styles.showing}>
            Showing 1–{shown.length} of {fmt(USER_TOTALS.total)}
          </p>
          <nav className={styles.pages} aria-label="Pages">
            <button
              type="button"
              className={styles.pageButton}
              aria-label="Previous page"
              disabled={page === 1}
              onClick={() => setPage((p) => p - 1)}
            >
              <img src={chevLeftIcon} width={15} height={15} alt="" />
            </button>
            {pageItems(page, USER_TOTALS.pages).map((item, index) =>
              item === '…' ? (
                <span key={`gap-${index}`} className={styles.pageGap}>
                  …
                </span>
              ) : (
                <button
                  key={item}
                  type="button"
                  className={item === page ? styles.pageOn : styles.pageButton}
                  aria-current={item === page ? 'page' : undefined}
                  onClick={() => setPage(item)}
                >
                  {fmt(item)}
                </button>
              ),
            )}
            <button
              type="button"
              className={styles.pageButton}
              aria-label="Next page"
              disabled={page === USER_TOTALS.pages}
              onClick={() => setPage((p) => p + 1)}
            >
              <img src={chevRightIcon} width={15} height={15} alt="" />
            </button>
          </nav>
        </div>
      </section>
    </div>
  )
}
