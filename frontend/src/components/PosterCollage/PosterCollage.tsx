import type { Gradient } from '../../pages/Home/homeData.ts'
import styles from './PosterCollage.module.css'

const gradientCss = ([top, middle, bottom]: Gradient) => `linear-gradient(180deg, ${top} 0%, ${middle} 50%, ${bottom} 100%)`

type PosterCollageProps = {
  /** Up to three posters, back to front. */
  gradients: Gradient[]
  /** "lg" = 210×150 (My lists cards), "sm" = 180×120 (public list cards). */
  size?: 'lg' | 'sm'
}

/** Three tilted, white-framed poster thumbs fanned out (Figma "Cover collage"). */
export default function PosterCollage({ gradients, size = 'lg' }: PosterCollageProps) {
  return (
    <div className={`${styles.collage} ${styles[size]}`} aria-hidden="true">
      {gradients.slice(0, 3).map((gradient, index) => (
        <span key={index} className={styles.poster} style={{ background: gradientCss(gradient) }} />
      ))}
    </div>
  )
}
