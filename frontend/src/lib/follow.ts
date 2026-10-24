import { api, clearApiCache } from './api.ts'

/**
 * Follows or unfollows someone (POST / DELETE /api/users/{username}/follow) and returns their new
 * follower count. Cached pages (profiles, Home feed, lists) are dropped so they show the change.
 */
export async function setFollowing(username: string, follow: boolean): Promise<number> {
  const path = `/users/${encodeURIComponent(username)}/follow`
  const response = follow ? await api.post<{ followers: number }>(path) : await api.delete<{ followers: number }>(path)
  clearApiCache()
  return response.data.followers
}
