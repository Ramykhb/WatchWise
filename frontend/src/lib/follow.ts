import { api, clearApiCache } from './api.ts'
import type { ApiFollowState } from './types.ts'

/**
 * Follows or unfollows someone (POST / DELETE /api/users/{username}/follow) and returns the new state with
 * their follower count. Following a followers-only or private profile sends a request instead
 * (`requested: true`), and unfollowing withdraws one. Cached pages (profiles, Home feed, lists) are dropped
 * so they show the change.
 */
export async function setFollowing(username: string, follow: boolean): Promise<ApiFollowState> {
  const path = `/users/${encodeURIComponent(username)}/follow`
  const response = follow ? await api.post<ApiFollowState>(path) : await api.delete<ApiFollowState>(path)
  clearApiCache()
  return response.data
}
