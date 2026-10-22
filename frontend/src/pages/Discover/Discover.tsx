import { useRef, useState, type CSSProperties, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router'
import AppNav from '../../components/AppNav/AppNav.tsx'
import ButtonPrimary from '../../components/ButtonPrimary/ButtonPrimary.tsx'
import Checkbox from '../../components/Checkbox/Checkbox.tsx'
import MovieTile from '../../components/MovieTile/MovieTile.tsx'
import SplitHeadline from '../../components/SplitHeadline/SplitHeadline.tsx'
import searchIcon from '../../assets/discover/icon-search.svg'
import chevDownSmIcon from '../../assets/discover/icon-chev-down-sm.svg'
import chevDownIcon from '../../assets/discover/icon-chev-down.svg'
import chevLeftIcon from '../../assets/discover/icon-chev-left.svg'
import chevRightIcon from '../../assets/discover/icon-chev-right.svg'
import gridIcon from '../../assets/discover/icon-grid.svg'
import listIcon from '../../assets/discover/icon-list.svg'
import xIcon from '../../assets/discover/icon-x.svg'
import starFull from '../../assets/discover/star-full.svg'
import starEmpty from '../../assets/discover/star-empty.svg'
import checkIcon from '../../assets/auth/icon-check.svg'
import starSmIcon from '../../assets/home/icon-star.svg'
import { useEntranceAnimation } from '../../hooks/useEntranceAnimation.ts'
import { gradientCss } from '../Home/homeData.ts'
import {
  DEFAULT_FILTERS,
  GENRES,
  INITIAL_FILTERS,
  LANGUAGES,
  RESULTS,
  RUNTIMES,
  SEARCH_TYPES,
  SORTS,
  TOTAL_PAGES,
  YEAR_BOUNDS,
  type Filters,
  type SearchType,
} from './discoverData.ts'
import styles from './Discover.module.css'

// TODO: search TMDB through the backend (query, type, filters, sort, page) instead of showing mock results.
const DEFAULT_QUERY = 'space'

/** Page buttons around the current page, e.g. 1 2 3 … 21 or 1 … 7 8 9 … 21. */
function pageItems(page: number, total: number): (number | '…')[] {
  if (page <= 3) return [1, 2, 3, '…', total]
  if (page >= total - 2) return [1, '…', total - 2, total - 1, total]
  return [1, '…', page - 1, page, page + 1, '…', total]
}

const fraction = (year: number) => (year - YEAR_BOUNDS.min) / (YEAR_BOUNDS.max - YEAR_BOUNDS.min)

export default function Discover() {
  const pageRef = useRef<HTMLDivElement>(null)
  useEntranceAnimation(pageRef)

  const [searchParams, setSearchParams] = useSearchParams()
  const submittedQuery = searchParams.get('q') ?? DEFAULT_QUERY
  const [query, setQuery] = useState(submittedQuery)
  const [type, setType] = useState<SearchType>('movies')

  // Draft = what the panel shows; applied = what the results use (committed by "Apply filters").
  const [draft, setDraft] = useState<Filters>(INITIAL_FILTERS)
  const [applied, setApplied] = useState<Filters>(INITIAL_FILTERS)

  const [sort, setSort] = useState(SORTS[0])
  const [view, setView] = useState<'grid' | 'list'>('grid')
  const [page, setPage] = useState(1)

  // Keep the field in sync when the header search changes ?q= while this page is open.
  const [lastQuery, setLastQuery] = useState(submittedQuery)
  if (submittedQuery !== lastQuery) {
    setLastQuery(submittedQuery)
    setQuery(submittedQuery)
  }

  const handleSearch = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const q = query.trim()
    setSearchParams(q ? { q } : {})
    setPage(1)
  }

  const applyFilters = (next: Filters) => {
    setDraft(next)
    setApplied(next)
    setPage(1)
  }

  const handleApply = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    applyFilters(draft)
  }

  const toggleGenre = (genre: string) =>
    setDraft((f) => ({
      ...f,
      genres: f.genres.includes(genre) ? f.genres.filter((g) => g !== genre) : [...f.genres, genre],
    }))

  const setYear = (index: 0 | 1, value: number) =>
    setDraft((f) => {
      const years: [number, number] = [...f.years]
      years[index] = index === 0 ? Math.min(value, f.years[1]) : Math.max(value, f.years[0])
      return { ...f, years }
    })

  const activeChips = [
    ...applied.genres.map((genre) => ({
      label: genre,
      remove: () => applyFilters({ ...applied, genres: applied.genres.filter((g) => g !== genre) }),
    })),
    ...(applied.runtime ? [{ label: applied.runtime, remove: () => applyFilters({ ...applied, runtime: null }) }] : []),
  ]

  const activeType = SEARCH_TYPES.find((t) => t.id === type)!
  const sliderStyle = { '--from': fraction(draft.years[0]), '--to': fraction(draft.years[1]) } as CSSProperties

  return (
    <div ref={pageRef} className={styles.page}>
      <AppNav />

      <main className={styles.main}>
        <header className={styles.header} data-enter data-enter-delay="0.1">
          <SplitHeadline id="discover-title" className={styles.title} lines={[{ text: 'Discover' }]} />
          <p className={styles.lead}>Search 900k+ movies, people and lists — live from TMDB.</p>

          <form className={styles.searchBar} role="search" onSubmit={handleSearch}>
            <img src={searchIcon} width={22} height={22} alt="" />
            <input
              className={styles.searchInput}
              type="search"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Search movies, people, lists…"
              aria-label="Search"
            />
            <label className={styles.typeChip}>
              <span className="sr-only">Search in</span>
              <select value={type} onChange={(event) => setType(event.target.value as SearchType)}>
                {SEARCH_TYPES.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.label}
                  </option>
                ))}
              </select>
              <img src={chevDownSmIcon} width={12} height={12} alt="" />
            </label>
            <ButtonPrimary label="Search" type="submit" className={styles.searchButton} />
          </form>

          <div className={styles.tabs} role="tablist" aria-label="Result type">
            {SEARCH_TYPES.map((t) => (
              <button
                key={t.id}
                type="button"
                role="tab"
                aria-selected={type === t.id}
                className={styles.tab}
                onClick={() => setType(t.id)}
              >
                <span>
                  {t.label}
                  <span className={styles.tabCount}>{t.count}</span>
                </span>
              </button>
            ))}
          </div>
        </header>

        <div className={styles.body}>
          <form
            className={styles.filters}
            onSubmit={handleApply}
            aria-labelledby="filters-title"
            data-enter
            data-enter-delay="0.25"
          >
            <div className={styles.filtersHead}>
              <h2 id="filters-title" className={styles.filtersTitle}>
                Filters
              </h2>
              <button type="button" className={styles.textButton} onClick={() => setDraft(DEFAULT_FILTERS)}>
                Reset
              </button>
            </div>

            <fieldset className={styles.group}>
              <legend className={styles.groupLabel}>Genres</legend>
              <div className={styles.chips}>
                {GENRES.map((genre) => {
                  const on = draft.genres.includes(genre)
                  return (
                    <button
                      key={genre}
                      type="button"
                      className={on ? styles.chipOn : styles.chip}
                      aria-pressed={on}
                      onClick={() => toggleGenre(genre)}
                    >
                      {on && <img src={checkIcon} width={14} height={14} alt="" />}
                      {genre}
                    </button>
                  )
                })}
              </div>
            </fieldset>

            <fieldset className={styles.group}>
              <legend className={styles.groupLabel}>Release year</legend>
              <div className={styles.range} style={sliderStyle}>
                <input
                  type="range"
                  min={YEAR_BOUNDS.min}
                  max={YEAR_BOUNDS.max}
                  value={draft.years[0]}
                  onChange={(event) => setYear(0, Number(event.target.value))}
                  aria-label="Released from"
                />
                <input
                  type="range"
                  min={YEAR_BOUNDS.min}
                  max={YEAR_BOUNDS.max}
                  value={draft.years[1]}
                  onChange={(event) => setYear(1, Number(event.target.value))}
                  aria-label="Released until"
                />
              </div>
              <div className={styles.rangeLabels} aria-hidden="true">
                <span>{draft.years[0]}</span>
                <span>{draft.years[1]}</span>
              </div>
            </fieldset>

            <fieldset className={styles.group}>
              <legend className={styles.groupLabel}>Minimum rating</legend>
              <div className={styles.ratingRow}>
                <div className={styles.stars}>
                  {[1, 2, 3, 4, 5].map((value) => (
                    <button
                      key={value}
                      type="button"
                      className={styles.star}
                      aria-label={`${value} star${value > 1 ? 's' : ''} and up`}
                      aria-pressed={draft.minRating === value}
                      onClick={() => setDraft((f) => ({ ...f, minRating: f.minRating === value ? 0 : value }))}
                    >
                      <img src={value <= draft.minRating ? starFull : starEmpty} width={16} height={16} alt="" />
                    </button>
                  ))}
                </div>
                <span className={styles.ratingValue}>
                  {draft.minRating ? `${draft.minRating.toFixed(1)} & up` : 'Any rating'}
                </span>
              </div>
            </fieldset>

            <fieldset className={styles.group}>
              <legend className={styles.groupLabel}>Runtime</legend>
              <div className={styles.chips}>
                {RUNTIMES.map((runtime) => {
                  const on = draft.runtime === runtime
                  return (
                    <button
                      key={runtime}
                      type="button"
                      className={on ? styles.chipOn : styles.chip}
                      aria-pressed={on}
                      onClick={() => setDraft((f) => ({ ...f, runtime: on ? null : runtime }))}
                    >
                      {runtime}
                    </button>
                  )
                })}
              </div>
            </fieldset>

            <div className={styles.group}>
              <label className={styles.groupLabel} htmlFor="discover-language">
                Language
              </label>
              <div className={styles.dropdown}>
                <select
                  id="discover-language"
                  value={draft.language}
                  onChange={(event) => setDraft((f) => ({ ...f, language: event.target.value }))}
                >
                  {LANGUAGES.map((language) => (
                    <option key={language}>{language}</option>
                  ))}
                </select>
                <img src={chevDownIcon} width={16} height={16} alt="" />
              </div>
            </div>

            <fieldset className={`${styles.group} ${styles.availability}`}>
              <legend className={styles.groupLabel}>Availability</legend>
              {(
                [
                  ['cinemas', 'In cinemas near me'],
                  ['streaming', 'Streaming'],
                  ['comingSoon', 'Coming soon'],
                ] as const
              ).map(([key, label]) => (
                <Checkbox
                  key={key}
                  checked={draft.availability[key]}
                  onChange={(event) =>
                    setDraft((f) => ({ ...f, availability: { ...f.availability, [key]: event.target.checked } }))
                  }
                >
                  {label}
                </Checkbox>
              ))}
            </fieldset>

            <ButtonPrimary label="Apply filters" type="submit" fullWidth />
          </form>

          <section className={styles.results} aria-labelledby="results-title" data-enter data-enter-delay="0.35">
            <div className={styles.toolbar}>
              <h2 id="results-title" className={styles.resultsTitle} aria-live="polite">
                {activeType.count} results
                {submittedQuery && (
                  <>
                    {' for '}
                    <span className={styles.accent}>“{submittedQuery}”</span>
                  </>
                )}
              </h2>
              <div className={styles.controls}>
                <label className={styles.sort}>
                  <span className="sr-only">Sort by</span>
                  <select value={sort} onChange={(event) => setSort(event.target.value)}>
                    {SORTS.map((option) => (
                      <option key={option} value={option}>
                        Sort: {option}
                      </option>
                    ))}
                  </select>
                  <img src={chevDownIcon} width={16} height={16} alt="" />
                </label>
                <button
                  type="button"
                  className={view === 'grid' ? styles.iconButtonOn : styles.iconButton}
                  aria-pressed={view === 'grid'}
                  aria-label="Grid view"
                  onClick={() => setView('grid')}
                >
                  <img className={styles.creamIcon} src={gridIcon} width={18} height={18} alt="" />
                </button>
                <button
                  type="button"
                  className={view === 'list' ? styles.iconButtonOn : styles.iconButton}
                  aria-pressed={view === 'list'}
                  aria-label="List view"
                  onClick={() => setView('list')}
                >
                  <img className={styles.inkIcon} src={listIcon} width={18} height={18} alt="" />
                </button>
              </div>
            </div>

            {activeChips.length > 0 && (
              <div className={styles.activeFilters}>
                {activeChips.map((chip) => (
                  <button
                    key={chip.label}
                    type="button"
                    className={styles.activeChip}
                    onClick={chip.remove}
                    aria-label={`Remove filter ${chip.label}`}
                  >
                    {chip.label}
                    <img src={xIcon} width={12} height={12} alt="" />
                  </button>
                ))}
                <button
                  type="button"
                  className={styles.textButtonSm}
                  onClick={() => applyFilters({ ...applied, genres: [], runtime: null })}
                >
                  Clear all
                </button>
              </div>
            )}

            {type !== 'movies' ? (
              <p className={styles.empty}>{activeType.label} results are coming soon.</p>
            ) : view === 'grid' ? (
              <div className={styles.grid}>
                {RESULTS.map((movie, index) => (
                  <MovieTile key={`${movie.slug}-${index}`} movie={movie} />
                ))}
              </div>
            ) : (
              <ul className={styles.list}>
                {RESULTS.map((movie, index) => (
                  <li key={`${movie.slug}-${index}`}>
                    <Link to={`/movies/${movie.slug}`} className={styles.listItem}>
                      <span className={styles.thumb} style={{ background: gradientCss(movie.gradient) }} />
                      <span className={styles.listText}>
                        <span className={styles.listTitle}>{movie.title}</span>
                        <span className={styles.listMeta}>{movie.meta}</span>
                      </span>
                      <span className={styles.listScore}>
                        <img src={starSmIcon} width={12} height={12} alt="" />
                        <span className="sr-only">Rated </span>
                        {movie.score}
                      </span>
                    </Link>
                  </li>
                ))}
              </ul>
            )}

            {type === 'movies' && (
              <nav className={styles.pagination} aria-label="Pages">
                <button
                  type="button"
                  className={styles.pageButton}
                  aria-label="Previous page"
                  disabled={page === 1}
                  onClick={() => setPage((p) => p - 1)}
                >
                  <img src={chevLeftIcon} width={18} height={18} alt="" />
                </button>
                {pageItems(page, TOTAL_PAGES).map((item, index) =>
                  item === '…' ? (
                    <span key={`gap-${index}`} className={styles.pageGap}>
                      …
                    </span>
                  ) : (
                    <button
                      key={item}
                      type="button"
                      className={item === page ? styles.pageButtonOn : styles.pageButton}
                      aria-current={item === page ? 'page' : undefined}
                      onClick={() => setPage(item)}
                    >
                      {item}
                    </button>
                  ),
                )}
                <button
                  type="button"
                  className={styles.pageButton}
                  aria-label="Next page"
                  disabled={page === TOTAL_PAGES}
                  onClick={() => setPage((p) => p + 1)}
                >
                  <img src={chevRightIcon} width={18} height={18} alt="" />
                </button>
              </nav>
            )}
          </section>
        </div>
      </main>
    </div>
  )
}
