import { useLayoutEffect, useRef, useState } from 'react'

/**
 * Scales a fixed-size artboard (e.g. a 720px-wide decorative stack from Figma)
 * down to fit its container. Returns a ref for the container and the scale (≤ 1).
 */
export function useFitScale<T extends HTMLElement>(designWidth: number) {
  const ref = useRef<T>(null)
  const [scale, setScale] = useState(1)

  useLayoutEffect(() => {
    const element = ref.current
    if (!element) return
    const update = (width: number) => setScale(width > 0 ? Math.min(1, width / designWidth) : 1)
    // Measure before first paint so the artboard never flashes at full size.
    update(element.clientWidth)
    const observer = new ResizeObserver(([entry]) => update(entry.contentRect.width))
    observer.observe(element)
    return () => observer.disconnect()
  }, [designWidth])

  return { ref, scale }
}
