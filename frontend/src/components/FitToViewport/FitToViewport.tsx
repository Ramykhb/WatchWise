import type { CSSProperties, ReactNode } from 'react'
import { useViewportFit } from '../../hooks/useViewportFit.ts'
import styles from './FitToViewport.module.css'

type FitToViewportProps = {
  /** Height of the Figma frame the page was designed at (width is always 1440). */
  designHeight: number
  children: ReactNode
}

const DESIGN_WIDTH = 1440
/** Matches the pages' tablet breakpoint (max-width: 1024px). */
const MIN_WIDTH = 1025

/**
 * On desktop widths, scales the whole page so the full design fits the window without
 * scrolling. Children read the scaled frame height from `--fit-height` and drop their
 * 1440px column cap via `--stage-max-width`.
 * Below 1025px the page renders normally and keeps its responsive layout.
 */
export default function FitToViewport({ designHeight, children }: FitToViewportProps) {
  const { enabled, scale, width, height } = useViewportFit(DESIGN_WIDTH, designHeight, MIN_WIDTH)

  if (!enabled) return <>{children}</>

  const frameStyle = {
    width: width / scale,
    height: height / scale,
    transform: `scale(${scale})`,
    '--fit-height': `${height / scale}px`,
    // Use the full scaled width instead of a centered 1440px column with empty sides.
    '--stage-max-width': 'none',
  } as CSSProperties

  return (
    <div className={styles.viewport}>
      <div className={styles.frame} style={frameStyle}>
        {children}
      </div>
    </div>
  )
}
