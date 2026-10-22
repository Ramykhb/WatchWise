import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router'
import StatCard from '../../components/StatCard/StatCard.tsx'
import chevDownIcon from '../../assets/watchlist/icon-chev-down.svg'
import searchIcon from '../../assets/listdetail/icon-search.svg'
import downloadIcon from '../../assets/admin/icon-download.svg'
import dotViolet from '../../assets/cinemas/dot-you.svg'
import dotCoral from '../../assets/cinemas/dot-cinema.svg'
import lineChart from '../../assets/admin/line-chart.svg'
import starIcon from '../../assets/cinemas/icon-star.svg'
import { gradientCss } from '../Home/homeData.ts'
import {
  ACTIVITY_LABELS,
  ADMIN_USER,
  HIGHEST_RATED,
  MOST_WATCHED,
  POPULAR_GENRES,
  RANGES,
  STATS,
  type RankedMovie,
} from './adminData.ts'
import styles from './AdminOverview.module.css'

const MAX_GENRE = Math.max(...POPULAR_GENRES.map((g) => g.percent))

function greeting(hour = new Date().getHours()) {
  if (hour < 12) return 'Good morning'
  if (hour < 18) return 'Good afternoon'
  return 'Good evening'
}

/** Builds a CSV of what the dashboard shows and downloads it. */
function exportCsv(range: string) {
  const rows = [
    ['WatchWise admin overview', range],
    [],
    ['Metric', 'Value', 'Change'],
    ...STATS.map((s) => [s.label, s.value, s.delta]),
    [],
    ['Genre', 'Share of watches'],
    ...POPULAR_GENRES.map((g) => [g.genre, `${g.percent}%`]),
    [],
    ['Most watched', 'Watches'],
    ...MOST_WATCHED.map((r) => [r.movie.title, r.value]),
    [],
    ['Highest rated', 'Rating'],
    ...HIGHEST_RATED.map((r) => [r.movie.title, r.value]),
  ]
  const csv = rows.map((row) => row.map((cell) => `"${String(cell).replaceAll('"', '""')}"`).join(',')).join('\r\n')
  const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }))
  const link = document.createElement('a')
  link.href = url
  link.download = `watchwise-overview-${range.toLowerCase().replaceAll(' ', '-')}.csv`
  link.click()
  URL.revokeObjectURL(url)
}

function RankedTable({ title, subtitle, rows, rating }: { title: string; subtitle: string; rows: RankedMovie[]; rating?: boolean }) {
  return (
    <section className={styles.table} aria-label={title}>
      <div className={styles.cardHead}>
        <h2 className={styles.cardTitle}>{title}</h2>
        <p className={styles.cardSub}>{subtitle}</p>
      </div>
      <ol className={styles.rows}>
        {rows.map((row, index) => (
          <li key={row.movie.slug}>
            <Link to={`/movies/${row.movie.slug}`} className={styles.row}>
              <span className={styles.rank}>{String(index + 1).padStart(2, '0')}</span>
              <span className={styles.thumb} style={{ background: gradientCss(row.movie.gradient) }} />
              <span className={styles.rowText}>
                <span className={styles.rowTitle}>{row.movie.title}</span>
                <span className={styles.rowMeta}>{row.meta}</span>
              </span>
              <span className={styles.rowValue}>
                {rating && <img src={starIcon} width={14} height={14} alt="" />}
                {rating && <span className="sr-only">Rated </span>}
                {row.value}
              </span>
            </Link>
          </li>
        ))}
      </ol>
    </section>
  )
}

export default function AdminOverview() {
  const navigate = useNavigate()
  // TODO: load the stats for the chosen range from the admin API.
  const [range, setRange] = useState<string>('Last 30 days')
  const [query, setQuery] = useState('')

  // The admin search looks up users; it opens the Users page with the query.
  const handleSearch = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const q = query.trim()
    navigate(q ? `/admin/users?q=${encodeURIComponent(q)}` : '/admin/users')
  }

  return (
    <div className={styles.page}>
      <header className={styles.topBar}>
        <div className={styles.titleBlock}>
          <h1 className={styles.title}>Overview</h1>
          <p className={styles.lead}>
            {greeting()}, {ADMIN_USER.name.split(' ')[0]} — here's how WatchWise is doing.
          </p>
        </div>
        <div className={styles.controls}>
          <label className={styles.range}>
            <span className="sr-only">Date range</span>
            <select value={range} onChange={(event) => setRange(event.target.value)}>
              {RANGES.map((option) => (
                <option key={option}>{option}</option>
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
          <button type="button" className={styles.export} onClick={() => exportCsv(range)}>
            <img src={downloadIcon} width={16} height={16} alt="" />
            Export
          </button>
        </div>
      </header>

      <div className={styles.stats}>
        {STATS.map((stat) => (
          <StatCard key={stat.label} {...stat} />
        ))}
      </div>

      <div className={styles.charts}>
        <section className={styles.activity} aria-labelledby="activity-title">
          <div className={styles.activityHead}>
            <div className={styles.cardHead}>
              <h2 id="activity-title" className={styles.cardTitle}>
                User activity
              </h2>
              <p className={styles.cardSub}>Daily active users · reviews posted</p>
            </div>
            <ul className={styles.legend}>
              <li>
                <img src={dotViolet} width={10} height={10} alt="" />
                Active users
              </li>
              <li>
                <img src={dotCoral} width={10} height={10} alt="" />
                Reviews
              </li>
            </ul>
          </div>
          {/* TODO: draw the chart from real daily numbers once the admin API exists. */}
          <img
            className={styles.chart}
            src={lineChart}
            width={640}
            height={220}
            alt="Line chart: daily active users and reviews posted both rise steadily from Feb 10 to Mar 10."
          />
          <div className={styles.xLabels} aria-hidden="true">
            {ACTIVITY_LABELS.map((label) => (
              <span key={label}>{label}</span>
            ))}
          </div>
        </section>

        <section className={styles.genres} aria-labelledby="genres-title">
          <div className={styles.cardHead}>
            <h2 id="genres-title" className={styles.cardTitle}>
              Most popular genres
            </h2>
            <p className={styles.cardSub}>Share of watches this month</p>
          </div>
          <ul className={styles.bars}>
            {POPULAR_GENRES.map((g) => (
              <li key={g.genre} className={styles.bar}>
                <span className={styles.barLabels}>
                  <span>{g.genre}</span>
                  <span className={styles.barPercent}>{g.percent}%</span>
                </span>
                {/* Bars are scaled to the top genre, as in the Figma frame. */}
                <span className={styles.track}>
                  <span className={styles.fill} style={{ width: `${(g.percent / MAX_GENRE) * 100}%`, background: g.color }} />
                </span>
              </li>
            ))}
          </ul>
        </section>
      </div>

      <div className={styles.tables}>
        <RankedTable title="Most watched movies" subtitle={range} rows={MOST_WATCHED} />
        <RankedTable title="Highest rated movies" subtitle="Min. 500 ratings" rows={HIGHEST_RATED} rating />
      </div>
    </div>
  )
}
