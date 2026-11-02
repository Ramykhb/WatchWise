import { useState } from 'react'
import PillButton from '../PillButton/PillButton.tsx'
import { setFollowing } from '../../lib/follow.ts'

type FollowButtonProps = {
  username: string
  following: boolean
  /** Your follow request is waiting for their answer. */
  requested?: boolean
  /** "Follow back" when they already follow you. */
  followsYou?: boolean
  /** Called with the new state and follower count once the API confirms it. */
  onChange?: (following: boolean, followers: number) => void
  className?: string
}

type State = 'none' | 'following' | 'requested'

/**
 * Follow / Requested / Following pill. Unfollowing (or withdrawing a request) changes at once and is put back if
 * the request fails; following waits for the answer, since a followers-only or private profile makes it a request.
 */
export default function FollowButton({ username, following, requested = false, followsYou, onChange, className }: FollowButtonProps) {
  const [state, setState] = useState<State>(following ? 'following' : requested ? 'requested' : 'none')
  const [busy, setBusy] = useState(false)

  const toggle = async () => {
    const before = state
    const follow = before === 'none'
    if (!follow) setState('none')
    setBusy(true)
    try {
      const result = await setFollowing(username, follow)
      setState(result.following ? 'following' : result.requested ? 'requested' : 'none')
      onChange?.(result.following, result.followers)
    } catch {
      setState(before)
    } finally {
      setBusy(false)
    }
  }

  return (
    <PillButton variant={state === 'none' ? 'solid' : 'outline'} pressed={state !== 'none'} disabled={busy} onClick={toggle} className={className}>
      {state === 'following' ? 'Following' : state === 'requested' ? 'Requested' : followsYou ? 'Follow back' : 'Follow'}
      <span className="sr-only"> @{username}</span>
    </PillButton>
  )
}
