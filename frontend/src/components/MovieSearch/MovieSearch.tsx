import { useState } from 'react'
import type { Movie } from '../MovieTile/MovieTile.tsx'
import { rememberMovie } from '../../lib/movieDetails.ts'
import { Thumb, useTitleSearch } from '../../pages/Lists/ListDialog.tsx'
import styles from '../../pages/Lists/ListDialog.module.css'

/** Movie nights store movie IDs only, so TV shows are excluded at the search endpoint. */
export default function MovieSearch({ excluded = [], onPick }: { excluded?: number[]; onPick: (movie: Movie) => void }) {
  const [query, setQuery] = useState('')
  const { results, searching, failed } = useTitleSearch(query, 'movie')
  const choices = results.filter((movie) => !excluded.includes(Number(movie.slug)))

  return (
    <div className={styles.searchWrap}>
      <label className={styles.search}>
        <input
          type="search"
          aria-label="Search movies"
          placeholder="Search movies…"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          onKeyDown={(event) => {
            if (event.key === 'Enter') event.preventDefault()
          }}
        />
      </label>
      {query.trim() && (
        <ul className={styles.results} aria-label="Movie results" aria-busy={searching} data-lenis-prevent>
          {searching && <li className={styles.noResults} role="status">Searching…</li>}
          {failed && <li className={styles.noResults} role="alert">Couldn't search. Change the search to try again.</li>}
          {!searching && !failed && choices.length === 0 && <li className={styles.noResults}>No matching movies.</li>}
          {!searching && !failed && choices.map((movie) => (
            <li key={movie.slug}>
              <button type="button" className={styles.result} onClick={() => {
                rememberMovie(movie)
                onPick(movie)
                setQuery('')
              }}>
                <Thumb movie={movie} className={styles.resultThumb} />
                <span className={styles.resultTitle}>{movie.title}</span>
                <span className={styles.resultMeta}>{movie.meta}</span>
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
