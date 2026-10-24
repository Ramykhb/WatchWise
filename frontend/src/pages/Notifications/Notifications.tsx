import { useRef, useState, type CSSProperties } from 'react'
import { Link } from 'react-router'
import AppNav from '../../components/AppNav/AppNav.tsx'
import PillButton from '../../components/PillButton/PillButton.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import Toggle from '../../components/Toggle/Toggle.tsx'
import calendarIcon from '../../assets/home/icon-calendar-sm.svg'
import crownIcon from '../../assets/nights/icon-crown.svg'
import chatIcon from '../../assets/profile/icon-chat.svg'
import userIcon from '../../assets/settings/icon-user.svg'
import heartIcon from '../../assets/movie/icon-heart.svg'
import listIcon from '../../assets/profile/icon-list.svg'
import sparkleIcon from '../../assets/foryou/icon-sparkle-sm.svg'
import eyeIcon from '../../assets/profile/icon-eye.svg'
import xIcon from '../../assets/listmodal/icon-x-sm.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import { dismiss, markAllRead, markRead, useNotifications, type AppNotification, type NotificationKind } from './notificationsStore.ts'
import { api, useApi } from '../../lib/api.ts'
import { setFollowing } from '../../lib/follow.ts'
import type { ApiMe } from '../../lib/types.ts'
import styles from './Notifications.module.css'

type Tab = 'all' | 'unread' | 'nights' | 'social'

const TAB_KINDS: Record<Exclude<Tab, 'all' | 'unread'>, NotificationKind[]> = {
  nights: ['invite', 'vote'],
  social: ['mention', 'follow', 'like', 'list'],
}

/** Icon and circle colour per kind; icons are masks so they all take the same ink colour. */
const KIND_STYLE: Record<NotificationKind, { icon: string; bg: string; label: string }> = {
  invite: { icon: calendarIcon, bg: 'var(--accent-yellow)', label: 'Movie night invite' },
  vote: { icon: crownIcon, bg: 'var(--surface-yellow-tint)', label: 'Voting' },
  mention: { icon: chatIcon, bg: 'var(--accent-mint)', label: 'Mention' },
  follow: { icon: userIcon, bg: 'var(--surface-violet-tint)', label: 'New follower' },
  like: { icon: heartIcon, bg: 'var(--surface-coral-tint)', label: 'Review' },
  list: { icon: listIcon, bg: 'var(--surface-violet-tint)', label: 'List' },
  release: { icon: sparkleIcon, bg: 'var(--surface-mint-tint)', label: 'New release' },
  watchlist: { icon: eyeIcon, bg: 'var(--surface-cream-deep)', label: 'Watchlist' },
}

type PreferenceKey = keyof ApiMe['preferences']['notifications']

const PREFERENCES: { id: PreferenceKey; title: string }[] = [
  { id: 'nights', title: 'Movie night invites & votes' },
  { id: 'mentions', title: 'Mentions in rooms' },
  { id: 'social', title: 'Followers, likes & lists' },
  { id: 'releases', title: 'New releases near you' },
]

const maskIcon = (icon: string) => ({ '--icon': `url("${icon}")` }) as CSSProperties

export default function Notifications() {
  const pageRef = useRef<HTMLDivElement>(null)
  useEntranceAnimation(pageRef)

  const { items, loaded } = useNotifications()
  const [tab, setTab] = useState<Tab>('all')
  // The switches are saved with the account preferences (GET/PATCH /api/me).
  const me = useApi<ApiMe>('/me')
  const prefs = me.data?.preferences.notifications
  const setPref = (id: PreferenceKey, on: boolean) => {
    me.setData((current) => ({ ...current, preferences: { ...current.preferences, notifications: { ...current.preferences.notifications, [id]: on } } }))
    api.patch('/me/preferences', { notifications: { [id]: on } }).catch(me.reload)
  }
  const [followedBack, setFollowedBack] = useState<number[]>([])
  const followBack = (item: AppNotification) => {
    if (!item.actor) return
    const following = followedBack.includes(item.id)
    setFollowedBack((ids) => (following ? ids.filter((id) => id !== item.id) : [...ids, item.id]))
    const request = setFollowing(item.actor.handle, !following)
    request.catch(() => setFollowedBack((ids) => (following ? [...ids, item.id] : ids.filter((id) => id !== item.id))))
  }

  const unread = items.filter((n) => !n.read).length
  const visible = items.filter((n) =>
    tab === 'all' ? true : tab === 'unread' ? !n.read : TAB_KINDS[tab].includes(n.kind),
  )
  const groups = (['Today', 'Yesterday', 'This week', 'Earlier'] as const)
    .map((group) => ({ group, list: visible.filter((n) => n.group === group) }))
    .filter((g) => g.list.length > 0)

  const tabs: { id: Tab; label: string; count?: number }[] = [
    { id: 'all', label: 'All', count: items.length },
    { id: 'unread', label: 'Unread', count: unread },
    { id: 'nights', label: 'Movie nights' },
    { id: 'social', label: 'Social' },
  ]

  return (
    <div ref={pageRef} className={styles.page}>
      <AppNav />

      <main className={styles.main}>
        <header className={styles.header} data-enter data-enter-delay="0.05">
          <div className={styles.titleBlock}>
            <SplitHeadline className={styles.title} lines={[{ text: 'Notifications' }]} />
            <p className={styles.lead} aria-live="polite">
              {unread > 0 ? `You have ${unread} unread notification${unread > 1 ? 's' : ''}.` : "You're all caught up."}
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
          <section className={styles.feed} aria-label="Notifications" data-enter data-enter-delay="0.2">
            {groups.map(({ group, list }) => (
              <div key={group} className={styles.group}>
                <h2 className={styles.groupTitle}>{group}</h2>
                <ul className={styles.list}>
                  {list.map((n) => (
                    <NotificationItem
                      key={n.id}
                      item={n}
                      followedBack={followedBack.includes(n.id)}
                      onFollowBack={() => followBack(n)}
                    />
                  ))}
                </ul>
              </div>
            ))}
            {groups.length === 0 && loaded && (
              <p className={styles.empty}>{tab === 'unread' ? 'No unread notifications. Nice.' : 'Nothing here yet.'}</p>
            )}
          </section>

          <aside className={styles.side} aria-labelledby="prefs-title" data-enter data-enter-delay="0.3">
            <h2 id="prefs-title" className={styles.sideTitle}>
              Notify me about
            </h2>
            <ul className={styles.prefs}>
              {PREFERENCES.map((p) => (
                <li key={p.id}>
                  <span id={`pref-${p.id}`}>{p.title}</span>
                  <Toggle checked={prefs?.[p.id] ?? false} labelledBy={`pref-${p.id}`} onChange={(on) => setPref(p.id, on)} />
                </li>
              ))}
            </ul>
            <Link to="/settings" className={styles.settingsLink}>
              All notification settings →
            </Link>
          </aside>
        </div>
      </main>
    </div>
  )
}

function NotificationItem({ item, followedBack, onFollowBack }: { item: AppNotification; followedBack: boolean; onFollowBack: () => void }) {
  const kind = KIND_STYLE[item.kind]
  return (
    <li className={item.read ? styles.item : styles.itemUnread}>
      {item.actor ? (
        <span className={styles.avatar} style={{ background: item.actor.color }} aria-hidden="true">
          {item.actor.initials}
          <span className={styles.badge} style={{ background: kind.bg }}>
            <span className={styles.badgeIcon} style={maskIcon(kind.icon)} />
          </span>
        </span>
      ) : (
        <span className={styles.kindIcon} style={{ background: kind.bg }} aria-hidden="true">
          <span className={styles.kindMask} style={maskIcon(kind.icon)} />
        </span>
      )}

      <Link to={item.to} className={styles.content} onClick={() => markRead(item.id)}>
        <span className="sr-only">{kind.label}{item.read ? '' : ', unread'}: </span>
        <span className={styles.sentence}>
          {item.actor && <strong>{item.actor.name} </strong>}
          {item.text}
          {item.target && <strong> {item.target}</strong>}
          {item.detail && item.kind === 'like' && ` ${item.detail}`}
        </span>
        {item.detail && item.kind !== 'like' && <span className={styles.detail}>{item.detail}</span>}
        <span className={styles.time}>{item.time}</span>
      </Link>

      <div className={styles.actions}>
        {item.kind === 'follow' && (
          <PillButton variant={followedBack ? 'outline' : 'solid'} pressed={followedBack} onClick={onFollowBack}>
            {followedBack ? 'Following' : 'Follow back'}
          </PillButton>
        )}
        <Link to={item.to} className={styles.action} onClick={() => markRead(item.id)}>
          {item.action}
        </Link>
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
