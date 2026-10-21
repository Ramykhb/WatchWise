import { useLocation } from 'react-router'

/** Router state set by the Sign in ⇄ Sign up links so the page swap morphs instead of replaying the entrance. */
export type AuthSwapState = { authSwap: true }

export const AUTH_SWAP_STATE: AuthSwapState = { authSwap: true }

/** True when this page was reached through the Sign in ⇄ Sign up view transition. */
export function useAuthSwap() {
  const location = useLocation()
  return (location.state as AuthSwapState | null)?.authSwap === true
}
