import { useEffect } from 'react'
import { Link } from 'react-router'
import { ListVideo } from 'lucide-react'
import AvatarImage from '../../components/AvatarImage/AvatarImage.tsx'
import LoadingState from '../../components/LoadingState/LoadingState.tsx'
import PillButton from '../../components/PillButton/PillButton.tsx'
import { look, shortName, timeAgo } from '../../lib/format.ts'
import { itemKey } from '../../lib/movies.ts'
import { posterAt, useMovies } from '../../lib/movieDetails.ts'
import type { ApiHomeActivity } from '../../lib/types.ts'
import type { Movie } from '../../components/MovieTile/MovieTile.tsx'
import { gradientCss } from './homeData.ts'
import styles from './Home.module.css'

function describe(item: ApiHomeActivity, movie: Movie | null) {
  const title = movie?.title ?? 'a title'
  const to = `/movies/${movie?.slug ?? itemKey({ tmdb_id: item.tmdb_id ?? 0, media_type: item.media_type })}`
  switch (item.kind) {
    case 'rated':
      return { action: 'rated', target: title, suffix: item.rating === null ? '' : `${item.rating}/5 ★`, to }
    case 'reviewed':
      return { action: 'reviewed', target: title, suffix: item.has_spoilers ? '· Contains spoilers' : item.excerpt ? `— “${item.excerpt}”` : '', to: `${to}#reviews` }
    case 'list':
      return { action: 'updated the public list', target: `“${item.list.title}”`, suffix: '', to: `/lists/${item.list.id}` }
    case 'watched':
      return { action: 'watched', target: title, suffix: '', to }
    case 'watchlist':
      return { action: 'added', target: title, suffix: 'to their watchlist', to }
  }
}

type Props = {
  activity: ApiHomeActivity[]
  loading: boolean
  error: unknown
  reload: () => void
}

export default function FollowingActivity({ activity, loading, error, reload }: Props) {
  const movieOf = useMovies(activity.flatMap((item) => item.tmdb_id === null ? [] : [itemKey({ ...item, tmdb_id: item.tmdb_id })]))

  useEffect(() => {
    const refresh = () => {
      if (document.visibilityState === 'visible') reload()
    }
    window.addEventListener('focus', refresh)
    const timer = window.setInterval(refresh, 60_000)
    return () => {
      window.removeEventListener('focus', refresh)
      window.clearInterval(timer)
    }
  }, [reload])

  return (
    <section className={styles.activity} aria-labelledby="home-activity" aria-busy={loading}>
      <div className={styles.activityHead}>
        <h2 id="home-activity" className={styles.cardTitle}>From people you follow</h2>
        <button type="button" className={styles.activityRefresh} onClick={reload} disabled={loading}>
          {loading && activity.length > 0 ? 'Refreshing…' : 'Refresh'}
        </button>
      </div>
      {loading && activity.length === 0 ? <LoadingState label="Loading activity…" compact />
        : error ? (
          <div className={styles.activityState} role="status">
            <p>Activity is temporarily unavailable.</p>
            <PillButton variant="outline" onClick={reload}>Try again</PillButton>
          </div>
        ) : activity.length === 0 ? (
          <div className={styles.activityState}>
            <p>No shared activity yet. Follow people from Discover’s People tab to see their latest watches, ratings and lists.</p>
            <PillButton to="/discover?type=people" variant="soft">Find people to follow</PillButton>
          </div>
        ) : activity.map((item) => {
          const movie = item.tmdb_id === null ? null : movieOf(itemKey({ ...item, tmdb_id: item.tmdb_id }))
          const line = describe(item, movie)
          const face = look(item.user)
          const poster = posterAt(movie?.posterUrl, 'w92')
          return (
            <div key={item.id} className={styles.activityItem}>
              <Link to={`/profile/${item.user.username}`} className={styles.avatar} style={{ background: face.color }} aria-label={`${item.user.name}’s profile`}>
                <AvatarImage src={item.user.avatar} fallback={face.initials} className={styles.activityAvatarImage} />
              </Link>
              <Link to={line.to} className={styles.activityText}>
                <span className={styles.activityLine}>
                  <strong>{shortName(item.user.name)}</strong> {line.action} <strong>{line.target}</strong>
                  {line.suffix && ` ${line.suffix}`}
                </span>
                <time className={styles.activityTime} dateTime={item.created_at}>{timeAgo(item.created_at)}</time>
              </Link>
              <Link to={line.to} className={styles.thumb} aria-label={item.kind === 'list' ? item.list.title : movie?.title}
                style={{ background: movie ? `${poster ? `center / cover no-repeat url("${poster}"), ` : ''}${gradientCss(movie.gradient)}` : 'var(--surface-violet-tint)' }}>
                {!movie && <ListVideo size={20} aria-hidden="true" />}
              </Link>
            </div>
          )
        })}
    </section>
  )
}
