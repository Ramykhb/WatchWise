import type { RefObject } from 'react'
import { EASE_OUT_BACK, gsap, useGSAP } from '../lib/gsap.ts'

/**
 * GSAP entrance timeline for every `[data-enter]` element inside `scope` (timings from the Figma prototype).
 *
 *  data-enter="rise" (default) — fade in while rising; `data-enter-rise` px (default 40)
 *  data-enter="pop"            — fade in while scaling up from 0.4
 *  data-enter-delay            — start delay in seconds
 *
 * Skipped when `enabled` is false (e.g. arriving through the Sign in ⇄ Sign up view transition)
 * and when the user prefers reduced motion.
 */
export function useEntranceAnimation(scope: RefObject<HTMLElement | null>, enabled = true) {
  useGSAP(
    () => {
      const root = scope.current
      if (!enabled || !root) return

      const mm = gsap.matchMedia()
      mm.add('(prefers-reduced-motion: no-preference)', () => {
        root.querySelectorAll<HTMLElement>('[data-enter]').forEach((el) => {
          const delay = Number(el.dataset.enterDelay ?? 0)
          // GSAP owns the transform while tweening and resets the CSS `rotate` property, so carry
          // the element's CSS tilt (e.g. the poster cards) into the tween, then hand back to CSS.
          // Cached on first read: after a revert GSAP leaves `rotate: none` inline, hiding the CSS value.
          el.dataset.enterRotation ??= String(parseFloat(getComputedStyle(el).rotate) || 0)
          const rotation = Number(el.dataset.enterRotation)
          const handBack = 'transform,translate,rotate,scale'
          if (el.dataset.enter === 'pop') {
            gsap.fromTo(
              el,
              { autoAlpha: 0, scale: 0.4, rotation },
              { autoAlpha: 1, scale: 1, rotation, delay, duration: 0.5, ease: EASE_OUT_BACK, clearProps: handBack },
            )
            return
          }
          const rise = Number(el.dataset.enterRise ?? 40)
          gsap.fromTo(
            el,
            { autoAlpha: 0, y: rise, rotation },
            { autoAlpha: 1, y: 0, rotation, delay, duration: 0.6, ease: EASE_OUT_BACK, clearProps: handBack },
          )
        })
      })
      return () => mm.revert()
    },
    { scope, dependencies: [enabled] },
  )
}
