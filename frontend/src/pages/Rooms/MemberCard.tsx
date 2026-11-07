import { useEffect, useId, useRef, useState, type KeyboardEvent } from 'react'
import xIcon from '../../assets/listmodal/icon-x.svg'
import AvatarImage from '../../components/AvatarImage/AvatarImage.tsx'
import FollowButton from '../../components/FollowButton/FollowButton.tsx'
import PillButton from '../../components/PillButton/PillButton.tsx'
import { isStatus, useApi } from '../../lib/api.ts'
import type { ApiProfile } from '../../lib/types.ts'
import type { Member } from './roomsData.ts'
import styles from './MemberCard.module.css'

const CLOSE_MS = 220

type MemberCardProps = {
  member: Member
  /** Keep it rendered and flip this to false to close it with its exit animation. */
  open: boolean
  onClose: () => void
  /** Called once the exit animation has finished after `open` became false. */
  onClosed: () => void
}

/**
 * A room member's card over the room (which stays visible, blurred, behind it): photo, name, Follow and
 * "Visit profile". It reads the same GET /api/users/{username} as the Profile page, so the profile then
 * opens at once from the cache. A follow made here clears that cache (lib/follow.ts), so the profile
 * loads fresh and shows it.
 */
export default function MemberCard({ member, open, onClose, onClosed }: MemberCardProps) {
  const titleId = useId()
  const dialogRef = useRef<HTMLDivElement>(null)
  const { data, error, reload } = useApi<ApiProfile>(`/users/${encodeURIComponent(member.handle)}`)
  // The follower count after following here; kept on the card only (the cache was cleared for the profile).
  const [followersNow, setFollowersNow] = useState<number | null>(null)
  const notFound = isStatus(error, 404)

  // Focus the card, lock the page scroll and give focus back to the name when it goes away.
  useEffect(() => {
    const previous = document.activeElement as HTMLElement | null
    dialogRef.current?.querySelector<HTMLElement>('button')?.focus()
    const { overflow } = document.body.style
    document.body.style.overflow = 'hidden'
    return () => {
      document.body.style.overflow = overflow
      previous?.focus?.()
    }
  }, [])

  // Kept in a ref so a parent re-render during the exit doesn't restart the timer below.
  const onClosedRef = useRef(onClosed)
  useEffect(() => {
    onClosedRef.current = onClosed
  })

  useEffect(() => {
    if (open) return
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    const timer = window.setTimeout(() => onClosedRef.current(), reduced ? 0 : CLOSE_MS)
    return () => window.clearTimeout(timer)
  }, [open])

  const close = () => {
    if (open) onClose()
  }

  // Esc closes; Tab and Shift+Tab stay inside the card.
  const handleKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (event.key === 'Escape') {
      event.stopPropagation()
      close()
      return
    }
    if (event.key !== 'Tab' || !dialogRef.current) return
    const focusable = [...dialogRef.current.querySelectorAll<HTMLElement>('a[href], button:not([disabled])')]
    const first = focusable[0]
    const last = focusable.at(-1)
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault()
      last?.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault()
      first?.focus()
    }
  }

  // The room already knows the name and colour, so the card shows them while the profile loads.
  const name = data?.user.name ?? member.name
  const followers = followersNow ?? data?.counts?.followers

  return (
    <div
      className={open ? styles.backdrop : styles.backdropClosing}
      onMouseDown={(event) => event.target === event.currentTarget && close()}
      onKeyDown={handleKeyDown}
      data-lenis-prevent
    >
      <div ref={dialogRef} className={styles.dialog} role="dialog" aria-modal="true" aria-labelledby={titleId} aria-busy={!data && !error}>
        <button type="button" className={styles.close} aria-label="Close" onClick={close}>
          <img src={xIcon} width={16} height={16} alt="" />
        </button>

        <span className={styles.avatar} style={{ background: member.color }}>
          <AvatarImage
            className={styles.photo}
            src={data?.user.avatar}
            alt={`${name}'s profile picture`}
            fallback={<span aria-hidden="true">{member.initials}</span>}
          />
        </span>
        <div className={styles.names}>
          <h2 id={titleId} className={styles.name}>
            {name}
          </h2>
          <p className={styles.handle}>@{member.handle}</p>
          {followers !== undefined && (
            <p className={styles.meta}>
              {followers} {followers === 1 ? 'follower' : 'followers'}
              {data?.follows_you && ' · Follows you'}
            </p>
          )}
        </div>

        {notFound ? (
          <p className={styles.note} role="alert">
            This account is no longer available.
          </p>
        ) : error ? (
          <p className={styles.note} role="alert">
            Couldn't load this profile.{' '}
            <button type="button" className={styles.textButton} onClick={reload}>
              Try again
            </button>
          </p>
        ) : null}

        <div className={styles.actions}>
          {data && !data.is_me ? (
            <FollowButton
              className={styles.action}
              username={member.handle}
              following={data.is_following}
              requested={data.is_requested}
              followsYou={data.follows_you}
              onChange={(_following, count) => setFollowersNow(count)}
            />
          ) : (
            !notFound && !data?.is_me && (
              <span className={styles.followPlaceholder} aria-hidden="true">
                Follow
              </span>
            )
          )}
          {!notFound && (
            <PillButton variant="outline" className={styles.action} to={data?.is_me ? '/profile' : `/profile/${member.handle}`}>
              Visit profile
            </PillButton>
          )}
        </div>
      </div>
    </div>
  )
}
