import { gsap } from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'
import { useGSAP } from '@gsap/react'

// Register GSAP plugins once for the whole app.
gsap.registerPlugin(useGSAP, ScrollTrigger)

/** Figma "Ease out back" used across the prototype animations. */
export const EASE_OUT_BACK = 'back.out(1.6)'

export { gsap, ScrollTrigger, useGSAP }
