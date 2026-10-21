import type { CSSProperties, ReactNode } from 'react'
import { useViewportFit } from '../../hooks/useViewportFit.ts'
import styles from './FitToViewport.module.css'

type FitToViewportProps = {
  /**
   * Height the page needs in its compact fit layout (width is always 1440). Smaller values
   * zoom the page in more, so keep it at or above the real content height.
   */
  designHeight: number
  children: ReactNode
}

const DESIGN_WIDTH = 1440
/** Matches the pages' tablet breakpoint (max-width: 1024px). */
const MIN_WIDTH = 1025
/** Share of the width beyond 1440px that becomes extra side margin. */
const EXTRA_WIDTH_TO_MARGIN = 0.5

/**
 * On desktop widths, scales the whole page so it fits the window without scrolling.
 * Inside the frame:
 *  - `data-fit` switches pages to their compact vertical spacing,
 *  - `--fit-height` is the frame height in design pixels,
 *  - `--stage-max-width` widens the content column with the window, keeping side margins.
 * Below 1025px the page renders normally and keeps its responsive layout.
 */
export default function FitToViewport({ designHeight, children }: FitToViewportProps) {
  const { enabled, scale, width, height } = useViewportFit(DESIGN_WIDTH, designHeight, MIN_WIDTH)

  if (!enabled) return <>{children}</>

  const frameWidth = width / scale
  const stageWidth = DESIGN_WIDTH + Math.max(0, frameWidth - DESIGN_WIDTH) * (1 - EXTRA_WIDTH_TO_MARGIN)
  const frameStyle = {
    width: frameWidth,
    height: height / scale,
    transform: `scale(${scale})`,
    '--fit-height': `${height / scale}px`,
    '--stage-max-width': `${stageWidth}px`,
  } as CSSProperties

  return (
    // data-lenis-prevent: this box scrolls natively (only when content overflows), outside Lenis.
    <div className={styles.viewport} data-lenis-prevent>
      <div className={styles.frame} style={frameStyle} data-fit="">
        {children}
      </div>
    </div>
  )
}
