/** Filter before pagination using the same local-day boundary as upcomingReleases. */
export function upcomingReleaseParams(today = new Date()) {
  const tomorrow = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 1)
  return {
    list: 'upcoming',
    sort: 'release_date',
    release_from: `${tomorrow.getFullYear()}-${String(tomorrow.getMonth() + 1).padStart(2, '0')}-${String(tomorrow.getDate()).padStart(2, '0')}`,
  }
}
