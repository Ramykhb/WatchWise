import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router'
import StatCard from '../../components/StatCard/StatCard.tsx'
import chevDownIcon from '../../assets/watchlist/icon-chev-down.svg'
import searchIcon from '../../assets/listdetail/icon-search.svg'
import downloadIcon from '../../assets/admin/icon-download.svg'
import dotViolet from '../../assets/cinemas/dot-you.svg'
import dotCoral from '../../assets/cinemas/dot-cinema.svg'
import starIcon from '../../assets/cinemas/icon-star.svg'
import { useAuth } from '../../contexts/useAuth.ts'
import { adminPosterUrl, useAdminStats, type AdminActivityPoint, type AdminRankedMovie, type AdminStats } from '../../lib/adminApi.ts'
import { apiErrorMessage } from '../../lib/auth.ts'
import { RANGES } from './adminData.ts'
import styles from './AdminOverview.module.css'

const GENRE_COLORS = [
  'var(--accent-coral)',
  'var(--ground-violet)',
  'var(--accent-yellow)',
  'var(--accent-mint)',
  'var(--surface-violet-tint)',
  'var(--border-subtle)',
]

const fmt = (n: number) => n.toLocaleString('en-US')
const shortDate = (iso: string) => new Date(`${iso}T00:00:00`).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })

function greeting(hour = new Date().getHours()) {
  if (hour < 12) return 'Good morning'
  if (hour < 18) return 'Good afternoon'
  return 'Good evening'
}

type StatItem = { label: string; value: string; delta: string; tone: 'up' | 'attention' }

/** "+12% vs previous 30 days", or the plain change when the earlier window was empty. */
function delta(current: number, previous: number, unit: string, since: string) {
  if (previous === 0) return `+${fmt(current)} ${unit}`
  const percent = Math.round(((current - previous) / previous) * 100)
  return `${percent >= 0 ? '+' : ''}${percent}% vs ${since === 'today' ? 'yesterday' : `previous ${since}`}`
}

function statItems(stats: AdminStats): StatItem[] {
  const { range } = stats
  const period = range.name === 'year' ? 'year' : `${range.days} days`
  return [
    { label: 'Registered users', value: fmt(stats.users_total), delta: delta(range.new_users, range.previous_new_users, 'new', period), tone: 'up' },
    { label: 'Reviews', value: fmt(stats.reviews_total), delta: delta(range.new_reviews, range.previous_new_reviews, 'new', period), tone: 'up' },
    { label: 'Active today', value: fmt(stats.active_today), delta: delta(stats.active_today, stats.active_yesterday, 'active', 'today'), tone: 'up' },
    { label: 'Flagged reviews', value: fmt(stats.flagged_reviews), delta: 'Needs moderation', tone: 'attention' },
  ]
}

/** Builds a CSV of what the dashboard shows and downloads it. */
function exportCsv(stats: AdminStats, range: string) {
  const rows = [
    ['WatchWise admin overview', range],
    [],
    ['Metric', 'Value', 'Change'],
    ...statItems(stats).map((s) => [s.label, s.value, s.delta]),
    [],
    ['Genre', 'Share of watches'],
    ...stats.popular_genres.map((g) => [g.name, `${g.share}%`]),
    [],
    ['Most watched', 'Watches'],
    ...stats.most_watched.map((r) => [r.title ?? `#${r.tmdb_id}`, r.count]),
    [],
    ['Highest rated', 'Rating', 'Ratings'],
    ...stats.top_rated.map((r) => [r.title ?? `#${r.tmdb_id}`, r.average ?? '', r.count]),
  ]
  const csv = rows.map((row) => row.map((cell) => `"${String(cell).replaceAll('"', '""')}"`).join(',')).join('\r\n')
  const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }))
  const link = document.createElement('a')
  link.href = url
  link.download = `watchwise-overview-${range.toLowerCase().replaceAll(' ', '-')}.csv`
  link.click()
  URL.revokeObjectURL(url)
}

function RankedTable({ title, subtitle, rows, rating }: { title: string; subtitle: string; rows: AdminRankedMovie[]; rating?: boolean }) {
  return (
    <section className={styles.table} aria-label={title}>
      <div className={styles.cardHead}>
        <h2 className={styles.cardTitle}>{title}</h2>
        <p className={styles.cardSub}>{subtitle}</p>
      </div>
      {rows.length === 0 ? (
        <p className={styles.cardSub}>Nothing to rank yet.</p>
      ) : (
        <ol className={styles.rows}>
          {rows.map((row, index) => {
            const poster = adminPosterUrl(row.poster_path)
            return (
              <li key={row.tmdb_id}>
                <Link to={`/movies/${row.tmdb_id}`} className={styles.row}>
                  <span className={styles.rank}>{String(index + 1).padStart(2, '0')}</span>
                  {poster ? (
                    <img className={styles.thumb} src={poster} alt="" />
                  ) : (
                    <span className={styles.thumb} style={{ background: 'var(--surface-violet-tint)' }} />
                  )}
                  <span className={styles.rowText}>
                    <span className={styles.rowTitle}>{row.title ?? `Movie #${row.tmdb_id}`}</span>
                    <span className={styles.rowMeta}>{rating ? `${fmt(row.count)} ratings` : `${fmt(row.count)} watches`}</span>
                  </span>
                  <span className={styles.rowValue}>
                    {rating && <img src={starIcon} width={14} height={14} alt="" />}
                    {rating && <span className="sr-only">Rated </span>}
                    {rating ? (row.average ?? 0).toFixed(1) : fmt(row.count)}
                  </span>
                </Link>
              </li>
            )
          })}
        </ol>
      )}
    </section>
  )
}

const CHART = { width: 640, height: 220, pad: 8 }

/** One polyline per series, each scaled to its own maximum so both stay readable. */
function ActivityChart({ points }: { points: AdminActivityPoint[] }) {
  const line = (pick: (point: AdminActivityPoint) => number) => {
    const max = Math.max(1, ...points.map(pick))
    const step = points.length > 1 ? (CHART.width - CHART.pad * 2) / (points.length - 1) : 0
    return points
      .map((point, index) => {
        const x = CHART.pad + index * step
        const y = CHART.height - CHART.pad - (pick(point) / max) * (CHART.height - CHART.pad * 2)
        return `${x.toFixed(1)},${y.toFixed(1)}`
      })
      .join(' ')
  }
  const signups = points.reduce((sum, p) => sum + p.signups, 0)
  const reviews = points.reduce((sum, p) => sum + p.reviews, 0)
  return (
    <svg
      className={styles.chart}
      viewBox={`0 0 ${CHART.width} ${CHART.height}`}
      width={CHART.width}
      height={CHART.height}
      role="img"
      aria-label={`Line chart of ${points.length} days: ${fmt(signups)} new members and ${fmt(reviews)} reviews.`}
    >
      <polyline points={line((p) => p.signups)} fill="none" stroke="var(--ground-violet)" strokeWidth="3" strokeLinejoin="round" strokeLinecap="round" />
      <polyline points={line((p) => p.reviews)} fill="none" stroke="var(--accent-coral)" strokeWidth="3" strokeLinejoin="round" strokeLinecap="round" />
    </svg>
  )
}

/** Five evenly spaced dates under the chart. */
function axisLabels(points: AdminActivityPoint[]) {
  if (points.length <= 5) return points.map((p) => shortDate(p.date))
  return [0, 1, 2, 3, 4].map((i) => shortDate(points[Math.round((i * (points.length - 1)) / 4)].date))
}

export default function AdminOverview() {
  const navigate = useNavigate()
  const { firstName } = useAuth()
  const [range, setRange] = useState(RANGES[1])
  const [query, setQuery] = useState('')
  const { data: stats, error, loading, reload } = useAdminStats(range.key)

  // The admin search looks up users; it opens the Users page with the query.
  const handleSearch = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const q = query.trim()
    navigate(q ? `/admin/users?q=${encodeURIComponent(q)}` : '/admin/users')
  }

  const maxGenre = Math.max(1, ...(stats?.popular_genres.map((g) => g.share) ?? []))

  return (
    <div className={styles.page}>
      <header className={styles.topBar}>
        <div className={styles.titleBlock}>
          <h1 className={styles.title}>Overview</h1>
          <p className={styles.lead}>
            {greeting()}, {firstName} — here's how WatchWise is doing.
          </p>
        </div>
        <div className={styles.controls}>
          <label className={styles.range}>
            <span className="sr-only">Date range</span>
            <select value={range.key} onChange={(event) => setRange(RANGES.find((r) => r.key === event.target.value) ?? RANGES[1])}>
              {RANGES.map((option) => (
                <option key={option.key} value={option.key}>
                  {option.label}
                </option>
              ))}
            </select>
            <img src={chevDownIcon} width={14} height={14} alt="" />
          </label>
          <form className={styles.search} role="search" onSubmit={handleSearch}>
            <img src={searchIcon} width={16} height={16} alt="" />
            <input
              type="search"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Search…"
              aria-label="Search users"
            />
          </form>
          <button type="button" className={styles.export} disabled={!stats} onClick={() => stats && exportCsv(stats, range.label)}>
            <img src={downloadIcon} width={16} height={16} alt="" />
            Export
          </button>
        </div>
      </header>

      {!stats ? (
        <p className={styles.lead} role={error ? 'alert' : 'status'}>
          {error ? (
            <>
              {apiErrorMessage(error, 'Unable to load the overview.')}{' '}
              <button type="button" className={styles.export} onClick={reload}>
                Try again
              </button>
            </>
          ) : (
            'Loading the overview…'
          )}
        </p>
      ) : (
        <div aria-busy={loading} className={styles.page}>
          <div className={styles.stats}>
            {statItems(stats).map((stat) => (
              <StatCard key={stat.label} {...stat} />
            ))}
          </div>

          <div className={styles.charts}>
            <section className={styles.activity} aria-labelledby="activity-title">
              <div className={styles.activityHead}>
                <div className={styles.cardHead}>
                  <h2 id="activity-title" className={styles.cardTitle}>
                    Member activity
                  </h2>
                  <p className={styles.cardSub}>New members · reviews posted</p>
                </div>
                <ul className={styles.legend}>
                  <li>
                    <img src={dotViolet} width={10} height={10} alt="" />
                    New members
                  </li>
                  <li>
                    <img src={dotCoral} width={10} height={10} alt="" />
                    Reviews
                  </li>
                </ul>
              </div>
              {stats.activity.length === 0 ? (
                <p className={styles.cardSub}>No activity recorded yet.</p>
              ) : (
                <>
                  <ActivityChart points={stats.activity} />
                  <div className={styles.xLabels} aria-hidden="true">
                    {axisLabels(stats.activity).map((label, index) => (
                      <span key={`${label}-${index}`}>{label}</span>
                    ))}
                  </div>
                </>
              )}
            </section>

            <section className={styles.genres} aria-labelledby="genres-title">
              <div className={styles.cardHead}>
                <h2 id="genres-title" className={styles.cardTitle}>
                  Most popular genres
                </h2>
                <p className={styles.cardSub}>Share of watched and rated movies</p>
              </div>
              {stats.popular_genres.length === 0 ? (
                <p className={styles.cardSub}>No genre data yet.</p>
              ) : (
                <ul className={styles.bars}>
                  {stats.popular_genres.map((g, index) => (
                    <li key={g.id} className={styles.bar}>
                      <span className={styles.barLabels}>
                        <span>{g.name}</span>
                        <span className={styles.barPercent}>{g.share}%</span>
                      </span>
                      {/* Bars are scaled to the top genre, as in the Figma frame. */}
                      <span className={styles.track}>
                        <span className={styles.fill} style={{ width: `${(g.share / maxGenre) * 100}%`, background: GENRE_COLORS[index % GENRE_COLORS.length] }} />
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </section>
          </div>

          <div className={styles.tables}>
            <RankedTable title="Most watched movies" subtitle="All time" rows={stats.most_watched} />
            <RankedTable title="Highest rated movies" subtitle="By member ratings" rows={stats.top_rated} rating />
          </div>
        </div>
      )}
    </div>
  )
}
