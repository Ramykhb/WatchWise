import { useState } from 'react'
import type { Gradient } from '../../pages/Home/homeData.ts'
import { posterAt } from '../../lib/movieDetails.ts'
import styles from './PosterCollage.module.css'

const gradientCss = ([top, middle, bottom]: Gradient) => `linear-gradient(180deg, ${top} 0%, ${middle} 50%, ${bottom} 100%)`

type PosterCollageProps = {
  /** Up to three posters, back to front. */
  gradients: Gradient[]
  /** Poster images in the same order; a missing one shows its gradient. */
  images?: (string | undefined)[]
  /** Posters whose film is still loading; they shimmer until their image arrives. */
  pending?: boolean[]
  /** "xl" = 260×220 (List Detail header), "lg" = 210×150 (My lists cards), "sm" = 180×120 (public list cards). */
  size?: 'xl' | 'lg' | 'sm'
}

/** Three tilted, white-framed poster thumbs fanned out (Figma "Cover collage"). */
export default function PosterCollage({ gradients, images = [], pending = [], size = 'lg' }: PosterCollageProps) {
  return (
    <div className={`${styles.collage} ${styles[size]}`} aria-hidden="true">
      {gradients.slice(0, 3).map((gradient, index) => (
        <Poster
          key={index}
          gradient={gradient}
          image={posterAt(images[index], size === 'xl' ? 'w342' : 'w185')}
          pending={pending[index] ?? false}
        />
      ))}
    </div>
  )
}

/** One poster: shimmers while its film or image loads, then the image fades in over the gradient. */
function Poster({ gradient, image, pending }: { gradient: Gradient; image?: string; pending: boolean }) {
  // The image that finished loading (or failed), so a new image shimmers again until it arrives.
  const [settled, setSettled] = useState<{ src: string; ok: boolean } | null>(null)
  const done = image !== undefined && settled?.src === image
  const shimmering = pending || (image !== undefined && !done)

  return (
    <span className={shimmering ? `${styles.poster} ${styles.shimmer}` : styles.poster} style={{ background: gradientCss(gradient) }}>
      {image && !(done && !settled?.ok) && (
        <img
          className={done ? `${styles.image} ${styles.imageShown}` : styles.image}
          src={image}
          alt=""
          draggable={false}
          onLoad={() => setSettled({ src: image, ok: true })}
          onError={() => setSettled({ src: image, ok: false })}
        />
      )}
    </span>
  )
}
