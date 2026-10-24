import { useState } from 'react'
import PillButton from '../PillButton/PillButton.tsx'
import { setFollowing } from '../../lib/follow.ts'

type FollowButtonProps = {
  username: string
  following: boolean
  /** "Follow back" when they already follow you. */
  followsYou?: boolean
  /** Called with the new state and follower count once the API confirms it. */
  onChange?: (following: boolean, followers: number) => void
  className?: string
}

/** Follow / Following pill that saves at once and puts the old state back if the request fails. */
export default function FollowButton({ username, following: initial, followsYou, onChange, className }: FollowButtonProps) {
  const [following, setState] = useState(initial)
  const [busy, setBusy] = useState(false)

  const toggle = async () => {
    const next = !following
    setState(next)
    setBusy(true)
    try {
      const followers = await setFollowing(username, next)
      onChange?.(next, followers)
    } catch {
      setState(!next)
    } finally {
      setBusy(false)
    }
  }

  return (
    <PillButton variant={following ? 'outline' : 'solid'} pressed={following} disabled={busy} onClick={toggle} className={className}>
      {following ? 'Following' : followsYou ? 'Follow back' : 'Follow'}
      <span className="sr-only"> @{username}</span>
    </PillButton>
  )
}
