import { NotificationsSkeleton, NotificationPreferencesSkeleton } from '../../components/LoadingState/ScreenSkeletons.tsx'
import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router'
import AppNav from '../../components/AppNav/AppNav.tsx'
import PillButton from '../../components/PillButton/PillButton.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import Toggle from '../../components/Toggle/Toggle.tsx'
import xIcon from '../../assets/listmodal/icon-x-sm.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import {
  acceptFollowRequest,
  declineFollowRequest,
  dismiss,
  loadNotifications,
  markAllRead,
  markRead,
  setActorFollow,
  useNotifications,
  type AppNotification,
} from './notificationsStore.ts'
import { ALWAYS_ON_NOTE, KIND_STYLE, NOTIFICATION_GROUPS, TAB_KINDS, maskIcon, type NotifyKey } from './notificationKinds.ts'
import { api, useApi } from '../../lib/api.ts'
import { setFollowing } from '../../lib/follow.ts'
import { dayGroup, timeAgo } from '../../lib/format.ts'
import { isLoading, useMovies } from '../../lib/movieDetails.ts'
import type { FilmKey } from '../../lib/movies.ts'
import type { ApiMe } from '../../lib/types.ts'
import styles from './Notifications.module.css'

type Tab = 'all' | 'unread' | keyof typeof TAB_KINDS

/** "5m ago" and the day groups move on while the page stays open. */
const CLOCK_MS = 60_000

export default function Notifications() {
  const pageRef = useRef<HTMLDivElement>(null)
  useEntranceAnimation(pageRef)

  const { items, loaded, failed } = useNotifications()
  const [tab, setTab] = useState<Tab>('all')
  const [now, setNow] = useState(() => Date.now())
  useEffect(() => {
    const timer = window.setInterval(() => setNow(Date.now()), CLOCK_MS)
    return () => window.clearInterval(timer)
  }, [])

  // The switches are saved with the account preferences (GET/PATCH /api/me), the same ones as Settings.
  const me = useApi<ApiMe>('/me')
  const prefs = me.data?.preferences.notifications
  const setPref = (id: NotifyKey, on: boolean) => {
    me.setData((current) => ({ ...current, preferences: { ...current.preferences, notifications: { ...current.preferences.notifications, [id]: on } } }))
    api.patch('/me/preferences', { notifications: { [id]: on } }).catch(me.reload)
  }

  // Titles of the films some notifications are about (votes, the night's pick, reviews).
  const films = items.flatMap((n) => (n.film === undefined ? [] : [n.film]))
  const movieOf = useMovies(films)
  const filmTitle = (key: FilmKey) => {
    const movie = movieOf(key)
    return isLoading(movie) ? undefined : movie.title
  }

  // The follow state lives in the notifications store (from the API), so it survives leaving the page.
  const followBack = (item: AppNotification) => {
    const { handle, follow } = item.actor
    const undo = () => setActorFollow(handle, follow)
    if (follow !== 'none') {
      setActorFollow(handle, 'none')
      setFollowing(handle, false).catch(undo)
      return
    }
    // A followers-only or private profile turns it into a request.
    setActorFollow(handle, 'following')
    setFollowing(handle, true)
      .then((result) => setActorFollow(handle, result.following ? 'following' : result.requested ? 'requested' : 'none'))
      .catch(undo)
  }

  const unread = items.filter((n) => !n.read).length
  const visible = items.filter((n) => (tab === 'all' ? true : tab === 'unread' ? !n.read : TAB_KINDS[tab].includes(n.kind)))
  const groups = (['Today', 'Yesterday', 'This week', 'Earlier'] as const)
    .map((group) => ({ group, list: visible.filter((n) => dayGroup(n.createdAt, now) === group) }))
    .filter((g) => g.list.length > 0)

  const tabs: { id: Tab; label: string; count?: number }[] = [
    { id: 'all', label: 'All', count: items.length },
    { id: 'unread', label: 'Unread', count: unread },
    { id: 'social', label: 'Social' },
    { id: 'rooms', label: 'Rooms' },
    { id: 'nights', label: 'Movie nights' },
  ]

  return (
    <div ref={pageRef} className={styles.page}>
      <AppNav />

      <main className={styles.main}>
        <header className={styles.header} data-enter data-enter-delay="0.05">
          <div className={styles.titleBlock}>
            <SplitHeadline className={styles.title} lines={[{ text: 'Notifications' }]} />
            <p className={styles.lead} aria-live="polite">
              {!loaded ? 'Checking your notifications…' : failed ? 'Notifications are temporarily unavailable.' : unread > 0 ? `You have ${unread} unread notification${unread > 1 ? 's' : ''}.` : "You're all caught up."}
            </p>
          </div>
          <PillButton variant="outline" disabled={unread === 0} onClick={markAllRead}>
            Mark all as read
          </PillButton>
        </header>

        <div className={styles.tabs} role="tablist" aria-label="Filter notifications">
          {tabs.map((t) => (
            <button key={t.id} type="button" role="tab" aria-selected={tab === t.id} className={styles.tab} onClick={() => setTab(t.id)}>
              <span>
                {t.label}
                {t.count !== undefined && <span className={styles.tabCount}>{t.count}</span>}
              </span>
            </button>
          ))}
        </div>

        <div className={styles.body}>
          <section className={styles.feed} aria-busy={!loaded} aria-label="Notifications" data-enter data-enter-delay="0.2">
            {!loaded && <NotificationsSkeleton />}
            {groups.map(({ group, list }) => (
              <div key={group} className={styles.group}>
                <h2 className={styles.groupTitle}>{group}</h2>
                <ul className={styles.list}>
                  {list.map((n) => (
                    <NotificationItem
                      key={n.id}
                      item={n}
                      time={timeAgo(n.createdAt, now)}
                      film={n.film === undefined ? undefined : filmTitle(n.film)}
                      onFollowBack={() => followBack(n)}
                    />
                  ))}
                </ul>
              </div>
            ))}
            {failed && (
              <div className={styles.empty} role="alert">
                <p>Couldn’t load notifications.</p>
                <PillButton variant="outline" onClick={() => void loadNotifications()}>Try again</PillButton>
              </div>
            )}
            {groups.length === 0 && loaded && !failed && (
              <p className={styles.empty}>{tab === 'unread' ? 'No unread notifications. Nice.' : 'Nothing here yet.'}</p>
            )}
          </section>

          <aside className={styles.side} aria-labelledby="prefs-title" data-enter data-enter-delay="0.3">
            <h2 id="prefs-title" className={styles.sideTitle}>
              Notify me about
            </h2>
            {!prefs && (me.loading || !me.error) ? (
              <NotificationPreferencesSkeleton />
            ) : me.error ? (
              <p role="alert">Couldn’t load preferences. <button type="button" onClick={me.reload}>Try again</button></p>
            ) : (
              NOTIFICATION_GROUPS.map((group) => (
                <div key={group.title} className={styles.prefGroup}>
                  <h3 className={styles.prefGroupTitle}>{group.title}</h3>
                  <ul className={styles.prefs}>
                    {group.items.map((p) => (
                      <li key={p.id}>
                        <span id={`pref-${p.id}`}>{p.title}</span>
                        <Toggle checked={prefs?.[p.id] ?? true} labelledBy={`pref-${p.id}`} onChange={(on) => setPref(p.id, on)} />
                      </li>
                    ))}
                  </ul>
                </div>
              ))
            )}
            <p className={styles.prefNote}>{ALWAYS_ON_NOTE}</p>
            <Link to="/settings#notifications" className={styles.settingsLink}>
              All notification settings →
            </Link>
          </aside>
        </div>
      </main>
    </div>
  )
}

function NotificationItem({ item, time, film, onFollowBack }: { item: AppNotification; time: string; film?: string; onFollowBack: () => void }) {
  const kind = KIND_STYLE[item.kind]
  const follow = item.actor.follow
  return (
    <li className={item.read ? styles.item : styles.itemUnread}>
      <span className={styles.avatar} style={{ background: item.actor.color }} aria-hidden="true">
        {item.actor.initials}
        <span className={styles.badge} style={{ background: kind.bg }}>
          <span className={styles.badgeIcon} style={maskIcon(kind.icon)} />
        </span>
      </span>

      <Link to={item.to} className={styles.content} onClick={() => markRead(item.id)}>
        <span className="sr-only">{kind.label}{item.read ? '' : ', unread'}: </span>
        <span className={styles.sentence}>
          <strong>{item.actor.name} </strong>
          {item.text}
          {item.target && <strong> {item.target}</strong>}
        </span>
        {item.detail && <span className={styles.detail}>{item.detail}</span>}
        {film && <span className={styles.detail}>{film}</span>}
        <span className={styles.time}>{time}</span>
      </Link>

      <div className={styles.actions}>
        {item.kind === 'follow_request' ? (
          <>
            <PillButton onClick={() => acceptFollowRequest(item)}>
              Accept<span className="sr-only"> @{item.actor.handle}</span>
            </PillButton>
            <PillButton variant="outline" onClick={() => declineFollowRequest(item)}>
              Decline<span className="sr-only"> @{item.actor.handle}</span>
            </PillButton>
          </>
        ) : (
          <>
            {item.kind === 'follow' && (
              <PillButton variant={follow === 'none' ? 'solid' : 'outline'} pressed={follow !== 'none'} onClick={onFollowBack}>
                {follow === 'following' ? 'Following' : follow === 'requested' ? 'Requested' : 'Follow back'}
              </PillButton>
            )}
            <Link to={item.to} className={styles.action} onClick={() => markRead(item.id)}>
              {item.action}
            </Link>
          </>
        )}
        <button
          type="button"
          className={styles.iconBtn}
          aria-label={item.read ? 'Mark as unread' : 'Mark as read'}
          title={item.read ? 'Mark as unread' : 'Mark as read'}
          onClick={() => markRead(item.id, !item.read)}
        >
          <span className={item.read ? styles.dotEmpty : styles.dot} />
        </button>
        <button type="button" className={styles.iconBtn} aria-label="Dismiss notification" onClick={() => dismiss(item.id)}>
          <img src={xIcon} width={12} height={12} alt="" />
        </button>
      </div>
    </li>
  )
}
