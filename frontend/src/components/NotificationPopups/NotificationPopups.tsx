import { useEffect, useState } from 'react'
import { createPortal } from 'react-dom'
import { Link } from 'react-router'
import xIcon from '../../assets/listmodal/icon-x-sm.svg'
import { KIND_STYLE, maskIcon } from '../../pages/Notifications/notificationKinds.ts'
import { closePopup, markRead, usePopups, type AppNotification } from '../../pages/Notifications/notificationsStore.ts'
import styles from './NotificationPopups.module.css'

/** How long a pop-up stays; hovering or focusing it holds it. */
const SHOW_MS = 6000
const EXIT_MS = 220

/**
 * Notifications as they arrive over the socket: a small card under the header for each, newest on top.
 * Clicking one opens what it is about and marks it read. Rendered on the body, above every page.
 */
export default function NotificationPopups() {
  const popups = usePopups()

  return createPortal(
    <div className={styles.stack} role="region" aria-label="New notifications" aria-live="polite">
      {popups.map((item) => (
        // A notification brought up to date (more messages in a room) starts over with its new text.
        <Popup key={`${item.id}-${item.createdAt}`} item={item} />
      ))}
    </div>,
    document.body,
  )
}

function Popup({ item }: { item: AppNotification }) {
  const kind = KIND_STYLE[item.kind]
  const [held, setHeld] = useState(false)
  const [leaving, setLeaving] = useState(false)

  useEffect(() => {
    if (held || leaving) return
    const timer = window.setTimeout(() => setLeaving(true), SHOW_MS)
    return () => window.clearTimeout(timer)
  }, [held, leaving])

  useEffect(() => {
    if (!leaving) return
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    const timer = window.setTimeout(() => closePopup(item.id), reduced ? 0 : EXIT_MS)
    return () => window.clearTimeout(timer)
  }, [leaving, item.id])

  const open = () => {
    markRead(item.id)
    closePopup(item.id)
  }

  return (
    <div
      className={leaving ? styles.popupLeaving : styles.popup}
      onPointerEnter={() => setHeld(true)}
      onPointerLeave={() => setHeld(false)}
      onFocus={() => setHeld(true)}
      onBlur={() => setHeld(false)}
    >
      <span className={styles.avatar} style={{ background: item.actor.color }} aria-hidden="true">
        {item.actor.initials}
        <span className={styles.badge} style={{ background: kind.bg }}>
          <span className={styles.badgeIcon} style={maskIcon(kind.icon)} />
        </span>
      </span>
      <Link to={item.to} className={styles.content} onClick={open}>
        <span className="sr-only">{kind.label}: </span>
        <span className={styles.sentence}>
          <strong>{item.actor.name}</strong> {item.text}
          {item.target && <strong> {item.target}</strong>}
        </span>
        {item.detail && <span className={styles.detail}>{item.detail}</span>}
        <span className={styles.action}>{item.action} →</span>
      </Link>
      <button type="button" className={styles.close} aria-label="Close notification" onClick={() => setLeaving(true)}>
        <img src={xIcon} width={12} height={12} alt="" />
      </button>
    </div>
  )
}
