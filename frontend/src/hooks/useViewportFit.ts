import { useEffect, useState } from 'react'

export type ViewportFit = {
  /** False below `minWidth` — the page falls back to its normal responsive (scrolling) layout. */
  enabled: boolean
  scale: number
  width: number
  height: number
}

function measure(designWidth: number, designHeight: number, minWidth: number): ViewportFit {
  const width = window.innerWidth
  const height = window.innerHeight
  if (width < minWidth) return { enabled: false, scale: 1, width, height }
  // Shrink (never enlarge) so the whole design frame fits inside the window.
  const scale = Math.min(1, width / designWidth, height / designHeight)
  return { enabled: true, scale, width, height }
}

/** Scale factor that fits a fixed design frame (e.g. 1440 x 1000 from Figma) into the window. */
export function useViewportFit(designWidth: number, designHeight: number, minWidth: number): ViewportFit {
  const [fit, setFit] = useState(() => measure(designWidth, designHeight, minWidth))

  useEffect(() => {
    const update = () => setFit(measure(designWidth, designHeight, minWidth))
    update()
    window.addEventListener('resize', update)
    return () => window.removeEventListener('resize', update)
  }, [designWidth, designHeight, minWidth])

  return fit
}
