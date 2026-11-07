import type { TmdbMovie } from '../Discover/discoverApi.ts'

export type Gradient = [string, string, string]

/** "Coming soon" date tiles take these in turn. */
export const RELEASE_GRADIENTS: Gradient[] = [
  ['#fa8cbf', '#9940b2', '#2b1a63'],
  ['#ffbf66', '#d94033', '#400d26'],
  ['#bfeb8c', '#4d9973', '#143338'],
]

export const gradientCss = ([top, middle, bottom]: Gradient) => `linear-gradient(180deg, ${top} 0%, ${middle} 50%, ${bottom} 100%)`

const DAY = 86_400_000
const RELATIVE_TIME = new Intl.RelativeTimeFormat('en-US', { numeric: 'auto' })

/** "tomorrow", "in 9 days", "in 3 weeks" or "in 2 months". */
export function releasesIn(days: number) {
  if (days < 14) return RELATIVE_TIME.format(days, 'day')
  if (days < 60) return RELATIVE_TIME.format(Math.round(days / 7), 'week')
  return RELATIVE_TIME.format(Math.round(days / 30), 'month')
}

/**
 * The `limit` most anticipated films from the upcoming list (it comes most popular first), soonest first:
 * three on Home, all of them on the Coming soon page. Days count in the viewer's time zone, so a film
 * that is already out here is skipped.
 */
export function upcomingReleases(movies: TmdbMovie[], genres: Map<number, string>, limit = 3) {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  return movies
    .flatMap((movie) => {
      if (!movie.release_date) return []
      const date = new Date(`${movie.release_date}T00:00:00`)
      const days = Math.round((date.getTime() - today.getTime()) / DAY)
      if (days < 1) return []
      const genre = movie.genre_ids.map((id) => genres.get(id)).find(Boolean)
      return [{
        id: movie.id,
        title: movie.title,
        posterUrl: movie.poster_url,
        date,
        days,
        month: date.toLocaleDateString('en-US', { month: 'short' }).toUpperCase(),
        day: date.toLocaleDateString('en-US', { day: '2-digit' }),
        meta: [genre, releasesIn(days)].filter(Boolean).join(' · '),
      }]
    })
    .slice(0, limit)
    .sort((a, b) => a.days - b.days)
}
