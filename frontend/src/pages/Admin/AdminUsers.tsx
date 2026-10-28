import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router'
import AdminPagination from '../../components/AdminPagination/AdminPagination.tsx'
import searchIcon from '../../assets/listdetail/icon-search.svg'
import chevDownIcon from '../../assets/watchlist/icon-chev-down.svg'
import mailSmIcon from '../../assets/admin/icon-mail-sm.svg'
import banWhiteIcon from '../../assets/admin/icon-ban-white.svg'
import checkIcon from '../../assets/admin/icon-check-sm.svg'
import editIcon from '../../assets/movie/icon-edit.svg'
import banIcon from '../../assets/admin/icon-ban.svg'
import dotsIcon from '../../assets/admin/icon-dots.svg'
import { useAuth } from '../../contexts/useAuth.ts'
import { adminApi, canSuspendUser, isAdminRole, useAdminUsers, type AdminUser, type AdminUserRole } from '../../lib/adminApi.ts'
import { apiErrorMessage } from '../../lib/auth.ts'
import { colorOf, initialsOf, longDate, timeAgo } from '../../lib/format.ts'
import { USER_ROLES, USER_ROLE_LABEL, type UserStatus } from './adminData.ts'
import styles from './AdminUsers.module.css'

const ROLE_CLASS: Record<AdminUserRole, string> = {
  user: styles.chipCream,
  moderator: styles.chipYellow,
  admin: styles.chipViolet,
}
const STATUS_CLASS: Record<UserStatus, string> = {
  Active: styles.chipMint,
  Unverified: styles.chipYellow,
  Suspended: styles.chipCoral,
}
const STATUS_FILTERS: UserStatus[] = ['Active', 'Unverified', 'Suspended']
const STATUS_PARAM = { Active: 'active', Unverified: 'unverified', Suspended: 'inactive' } as const
const PER_PAGE = 20

const fmt = (n: number) => n.toLocaleString('en-US')

const STATUS_LABEL: Record<AdminUser['status'], UserStatus> = { active: 'Active', unverified: 'Unverified', inactive: 'Suspended' }

type Menu = { id: number; kind: 'role' | 'more' } | null

export default function AdminUsers() {
  const [searchParams] = useSearchParams()
  const { user: me } = useAuth()
  const isAdmin = isAdminRole(me?.role)
  const [query, setQuery] = useState(searchParams.get('q') ?? '')
  const [search, setSearch] = useState(query.trim())
  const [role, setRole] = useState<AdminUserRole | 'All'>('All')
  const [status, setStatus] = useState<UserStatus | 'All'>('All')
  const [page, setPage] = useState(1)
  const [selected, setSelected] = useState<number[]>([])
  const [menu, setMenu] = useState<Menu>(null)
  const [note, setNote] = useState('')

  // Search the API once typing pauses.
  useEffect(() => {
    const timer = window.setTimeout(() => {
      setSearch(query.trim())
      setPage(1)
      setSelected([])
    }, 300)
    return () => window.clearTimeout(timer)
  }, [query])

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

  const { data, error, loading, setData, reload } = useAdminUsers({
    q: search,
    role: role === 'All' ? undefined : role,
    status: status === 'All' ? undefined : STATUS_PARAM[status],
    page,
    per_page: PER_PAGE,
  })
  const users = data?.data ?? []
  const hasFilters = search !== '' || role !== 'All' || status !== 'All'

  const allShownSelected = users.length > 0 && users.every((u) => selected.includes(u.id))
  const selectedUsers = users.filter((u) => selected.includes(u.id))

  const filterBy = (apply: () => void) => {
    apply()
    setPage(1)
    setSelected([])
  }
  const toggle = (id: number) => setSelected((list) => (list.includes(id) ? list.filter((x) => x !== id) : [...list, id]))
  const toggleAll = () =>
    setSelected((list) =>
      allShownSelected ? list.filter((id) => !users.some((u) => u.id === id)) : [...new Set([...list, ...users.map((u) => u.id)])],
    )

  const replaceUser = (updated: AdminUser) =>
    setData((current) => ({ ...current, data: current.data.map((u) => (u.id === updated.id ? { ...u, ...updated } : u)) }))

  const toggleSuspend = async (user: AdminUser) => {
    const suspending = user.is_active
    if (suspending && !window.confirm(`Suspend ${user.name}? They won't be able to sign in or post.`)) return
    try {
      replaceUser(await adminApi.updateUser(user.id, { is_active: !suspending }))
      setNote(`${user.name} ${suspending ? 'suspended' : 'reinstated'}`)
    } catch (updateError) {
      setNote(apiErrorMessage(updateError, 'Unable to update this user. Please try again.'))
    }
  }

  const changeRole = async (user: AdminUser, next: AdminUserRole) => {
    setMenu(null)
    if (next === user.role) return
    try {
      replaceUser(await adminApi.updateUser(user.id, { role: next }))
      setNote(`${user.name} is now ${next === 'admin' ? 'an' : 'a'} ${USER_ROLE_LABEL[next]}`)
    } catch (updateError) {
      setNote(apiErrorMessage(updateError, 'Unable to change the role. Please try again.'))
    }
  }

  const deleteUser = async (user: AdminUser) => {
    setMenu(null)
    if (!window.confirm(`Delete ${user.name}? The account is removed for good.`)) return
    try {
      await adminApi.deleteUser(user.id)
      setSelected((list) => list.filter((id) => id !== user.id))
      setNote(`${user.name} was deleted`)
      reload()
    } catch (deleteError) {
      setNote(apiErrorMessage(deleteError, 'Unable to delete this user. Please try again.'))
    }
  }

  const suspendSelected = async () => {
    const targets = selectedUsers.filter((u) => u.is_active && canSuspendUser(me, u))
    if (targets.length === 0) {
      setNote('Nothing to suspend in this selection.')
      return
    }
    if (!window.confirm(`Suspend ${targets.length} user${targets.length > 1 ? 's' : ''}?`)) return
    const results = await Promise.allSettled(targets.map((u) => adminApi.updateUser(u.id, { is_active: false })))
    const failed = results.filter((r): r is PromiseRejectedResult => r.status === 'rejected')
    setNote(
      failed.length === 0
        ? `${targets.length} user${targets.length > 1 ? 's' : ''} suspended`
        : apiErrorMessage(failed[0].reason, `${failed.length} of ${targets.length} users could not be suspended.`),
    )
    setSelected([])
    reload()
  }

  const emailSelected = () => {
    window.location.href = `mailto:?bcc=${selectedUsers.map((u) => u.email).join(',')}`
  }

  const copyEmail = async (user: AdminUser) => {
    try {
      await navigator.clipboard.writeText(user.email)
      setNote(`Copied ${user.email}`)
    } catch {
      setNote('')
    }
    setMenu(null)
  }

  // Site-wide totals from the API, whatever filter is applied.
  const totals = data?.summary
  const summary: { label: string; className: string; onClick: () => void; pressed: boolean; count?: number }[] = [
    { label: 'Total', className: styles.chipWhite, onClick: () => filterBy(() => (setRole('All'), setStatus('All'))), pressed: role === 'All' && status === 'All', count: totals?.total },
    { label: 'Active', className: styles.chipMint, onClick: () => filterBy(() => setStatus('Active')), pressed: status === 'Active', count: totals?.active },
    { label: 'Unverified', className: styles.chipYellow, onClick: () => filterBy(() => setStatus('Unverified')), pressed: status === 'Unverified', count: totals?.unverified },
    { label: 'Suspended', className: styles.chipCoral, onClick: () => filterBy(() => setStatus('Suspended')), pressed: status === 'Suspended', count: totals?.inactive },
    { label: 'Moderators', className: styles.chipYellow, onClick: () => filterBy(() => setRole('moderator')), pressed: role === 'moderator', count: totals?.moderators },
    { label: 'Admins', className: styles.chipViolet, onClick: () => filterBy(() => setRole('admin')), pressed: role === 'admin', count: totals?.admins },
  ]

  return (
    <div className={styles.page}>
      <header className={styles.topBar}>
        <div className={styles.titleBlock}>
          <h1 className={styles.title}>Users</h1>
          <p className={styles.lead}>{isAdmin ? 'Manage accounts, roles and access.' : 'Review accounts and suspend those that break the rules.'}</p>
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
            <select value={role} onChange={(event) => filterBy(() => setRole(event.target.value as AdminUserRole | 'All'))}>
              <option value="All">Role: All</option>
              {USER_ROLES.map((r) => (
                <option key={r} value={r}>
                  Role: {USER_ROLE_LABEL[r]}
                </option>
              ))}
            </select>
            <img src={chevDownIcon} width={14} height={14} alt="" />
          </label>
          <label className={styles.select}>
            <span className="sr-only">Filter by status</span>
            <select value={status} onChange={(event) => filterBy(() => setStatus(event.target.value as UserStatus | 'All'))}>
              <option value="All">Status: All</option>
              {STATUS_FILTERS.map((s) => (
                <option key={s} value={s}>
                  Status: {s}
                </option>
              ))}
            </select>
            <img src={chevDownIcon} width={14} height={14} alt="" />
          </label>
        </div>
      </header>

      <div className={styles.summary} role="group" aria-label="Quick filters">
        {summary.map((chip) => (
          <button key={chip.label} type="button" className={chip.className} aria-pressed={chip.pressed} onClick={chip.onClick}>
            {chip.label}
            {chip.count !== undefined && ` ${fmt(chip.count)}`}
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
          <button type="button" className={styles.bulkSuspend} onClick={() => void suspendSelected()}>
            <img src={banWhiteIcon} width={14} height={14} alt="" />
            Suspend
          </button>
        </div>
      )}

      <section className={styles.tableCard} aria-label="Users" aria-busy={loading}>
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
              {!data && (
                <tr>
                  <td colSpan={8} className={styles.empty} role={error ? 'alert' : 'status'}>
                    {error ? (
                      <>
                        {apiErrorMessage(error, 'Unable to load users.')}{' '}
                        <button type="button" className={styles.menuItem} onClick={reload}>
                          Try again
                        </button>
                      </>
                    ) : (
                      'Loading users…'
                    )}
                  </td>
                </tr>
              )}
              {data && users.length === 0 && (
                <tr>
                  <td colSpan={8} className={styles.empty}>
                    {hasFilters ? 'No users match these filters.' : 'No users yet.'}
                  </td>
                </tr>
              )}
              {users.map((user) => {
                const isSelected = selected.includes(user.id)
                const userStatus = STATUS_LABEL[user.status]
                const isMe = user.id === me?.id
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
                        <span className={styles.avatar} style={{ background: colorOf(user.id) }} aria-hidden="true">
                          {initialsOf(user.name)}
                        </span>
                        <span className={styles.userText}>
                          <span className={styles.userName}>{user.name}</span>
                          <span className={styles.userEmail}>{user.email}</span>
                        </span>
                      </span>
                    </td>
                    <td>
                      <span className={ROLE_CLASS[user.role] ?? styles.chipCream}>{USER_ROLE_LABEL[user.role] ?? user.role}</span>
                    </td>
                    <td>
                      <span className={STATUS_CLASS[userStatus]}>{userStatus}</span>
                    </td>
                    <td className={styles.muted}>{user.created_at ? longDate(user.created_at) : '—'}</td>
                    <td className={styles.strong}>{fmt(user.reviews_count)}</td>
                    <td className={styles.muted}>{user.last_active_at ? timeAgo(user.last_active_at) : '—'}</td>
                    <td>
                      <div className={styles.actions}>
                        {isAdmin && (
                        <div className={styles.menuWrap} data-menu>
                          <button
                            type="button"
                            className={styles.actionCream}
                            aria-label={`Change ${user.name}'s role`}
                            aria-expanded={menu?.id === user.id && menu.kind === 'role'}
                            disabled={isMe}
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
                                    onClick={() => void changeRole(user, r)}
                                  >
                                    {USER_ROLE_LABEL[r]}
                                  </button>
                                </li>
                              ))}
                            </ul>
                          )}
                        </div>
                        )}
                        <button
                          type="button"
                          className={!user.is_active ? styles.actionCoralOn : styles.actionCoral}
                          aria-pressed={!user.is_active}
                          aria-label={!user.is_active ? `Reinstate ${user.name}` : `Suspend ${user.name}`}
                          disabled={!canSuspendUser(me, user)}
                          onClick={() => void toggleSuspend(user)}
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
                                <button type="button" role="menuitem" className={styles.menuItem} onClick={() => void copyEmail(user)}>
                                  Copy email
                                </button>
                              </li>
                              {isAdmin && !isMe && (
                                <li>
                                  <button type="button" role="menuitem" className={styles.menuItem} onClick={() => void deleteUser(user)}>
                                    Delete user
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

        {data && (
          <AdminPagination
            meta={data.meta}
            shown={users.length}
            onPage={(next) => {
              setPage(next)
              setSelected([])
            }}
          />
        )}
      </section>
    </div>
  )
}
