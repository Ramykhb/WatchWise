import { useRef } from 'react'
import type { Gradient } from '../../pages/Home/homeData.ts'
import { useInView } from '../../hooks/useInView.ts'
import { isLoading, useMovies } from '../../lib/movieDetails.ts'
import type { FilmKey } from '../../lib/movies.ts'
import PosterCollage from './PosterCollage.tsx'
import styles from './PosterCollage.module.css'

type ListCoverProps = {
  /** TMDB ids of the cover films (up to three). */
  tmdbIds: FilmKey[]
  /** Shown behind each poster, and for films with no poster. */
  gradients: Gradient[]
  size?: 'xl' | 'lg' | 'sm'
}

/**
 * A list's poster collage that only asks for its films once the card is on (or near) the screen, so a
 * page of many lists doesn't request every cover at once. Until then the posters shimmer.
 */
export default function ListCover({ tmdbIds, gradients, size }: ListCoverProps) {
  const ref = useRef<HTMLSpanElement>(null)
  const inView = useInView(ref)
  const movieOf = useMovies(tmdbIds, inView)
  const movies = tmdbIds.map((id) => movieOf(id))

  return (
    <span ref={ref} className={styles.observed}>
      <PosterCollage
        gradients={gradients}
        images={movies.map((movie) => movie.posterUrl)}
        pending={movies.map(isLoading)}
        size={size}
      />
    </span>
  )
}
