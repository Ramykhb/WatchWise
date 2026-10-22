import { useRef, useState } from 'react'
import { useAuthSwap } from '../../hooks/useAuthSwap.ts'
import { gsap, useGSAP } from '../../lib/gsap.ts'

/** Which side of the auth page the violet ground sits on (Sign in: right, Sign up: left). */
export type WaveSide = 'left' | 'right'

type Point = [number, number]

const WIDTH = 1440
const HEIGHT = 1024
/** Distance from a band's wavy edge to its far edge, which always stays off-screen. */
const BAND = 900

/** Wavy edges of the Sign in waves (two cubic Béziers each), from the Figma SVGs. Sign up mirrors them. */
const LAYERS: { fill: string; edge: Point[] }[] = [
  {
    fill: 'var(--accent-coral)',
    edge: [[800, 0], [680, 170], [880, 300], [760, 470], [640, 640], [860, 800], [720, 1024]],
  },
  {
    fill: 'var(--ground-violet)',
    edge: [[880, 0], [760, 160], [960, 290], [840, 460], [720, 630], [940, 780], [800, 1024]],
  },
]

/**
 * Each wave is a band with two wavy edges, so both sides share one path structure and GSAP can
 * tween between them: on the right the left edge is the visible wave, on the left the right one is.
 */
function bandPath(edge: Point[], side: WaveSide) {
  const wave: Point[] = side === 'right' ? edge : edge.map(([x, y]) => [WIDTH - x, y])
  const left = side === 'right' ? wave : wave.map(([x, y]): Point => [x - BAND, y])
  const right = side === 'right' ? wave.map(([x, y]): Point => [x + BAND, y]) : wave
  const [l0, l1, l2, l3, l4, l5, l6] = left.map(([x, y]) => `${x} ${y}`)
  const [r0, r1, r2, r3, r4, r5, r6] = [...right].reverse().map(([x, y]) => `${x} ${y}`)
  return `M${l0}C${l1} ${l2} ${l3}C${l4} ${l5} ${l6}L${r0}C${r1} ${r2} ${r3}C${r4} ${r5} ${r6}Z`
}

/** Side shown by the last auth page, so the next one knows where the waves are coming from. */
let lastSide: WaveSide | null = null

type AuthWavesProps = {
  side: WaveSide
  className?: string
}

/**
 * Coral + violet waves behind the auth pages. On a Sign in ⇄ Sign up swap the waves sweep across
 * to the other side (Figma Smart Animate). The view transition shows this live: see `auth-waves`
 * in styles/motion.css.
 */
export default function AuthWaves({ side, className }: AuthWavesProps) {
  const ref = useRef<SVGSVGElement>(null)
  const swapped = useAuthSwap()
  // Read once per mount (StrictMode replays effects, which would otherwise see this page's side).
  const [from] = useState(() => lastSide)

  useGSAP(
    () => {
      lastSide = side
      const svg = ref.current
      if (!swapped || !from || from === side || !svg) return

      const mm = gsap.matchMedia()
      mm.add('(prefers-reduced-motion: no-preference)', () => {
        svg.querySelectorAll('path').forEach((path, index) => {
          const { edge } = LAYERS[index]
          gsap.fromTo(
            path,
            { attr: { d: bandPath(edge, from) } },
            // Figma: Smart Animate, "Gentle" spring, 0.7s; the violet ground trails the coral wave.
            { attr: { d: bandPath(edge, side) }, duration: 0.7, delay: index * 0.04, ease: 'back.out(1.1)' },
          )
        })
      })
      return () => mm.revert()
    },
    { scope: ref, dependencies: [side] },
  )

  return (
    <svg
      ref={ref}
      className={['vt-auth-waves', className].filter(Boolean).join(' ')}
      viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
      preserveAspectRatio="none"
      aria-hidden="true"
    >
      {LAYERS.map(({ fill, edge }) => (
        <path key={fill} d={bandPath(edge, side)} fill={fill} />
      ))}
    </svg>
  )
}
