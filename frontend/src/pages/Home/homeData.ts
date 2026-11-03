export type Gradient = [string, string, string]

/** "Coming soon" date tiles take these in turn. */
export const RELEASE_GRADIENTS: Gradient[] = [
  ['#fa8cbf', '#9940b2', '#2b1a63'],
  ['#ffbf66', '#d94033', '#400d26'],
  ['#bfeb8c', '#4d9973', '#143338'],
]

export const gradientCss = ([top, middle, bottom]: Gradient) => `linear-gradient(180deg, ${top} 0%, ${middle} 50%, ${bottom} 100%)`
