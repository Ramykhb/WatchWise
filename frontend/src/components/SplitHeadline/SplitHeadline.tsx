import SplitText from '../reactbits/SplitText/SplitText.tsx'

/** `accentEnd` appends accent-coloured words to the same line, e.g. "you, " + "Maya.". */
export type HeadlineLine = { text: string; accent?: boolean; accentEnd?: string }

type SplitHeadlineProps = {
  lines: HeadlineLine[]
  id?: string
  className?: string
  /** Class applied to accent lines (e.g. the coral "frame."). */
  accentClassName?: string
  /** Set false to render static text (e.g. during a page-swap view transition). */
  animate?: boolean
}

function Part({ text, className, animate }: { text: string; className?: string; animate: boolean }) {
  if (!animate) return <span className={className}>{text}</span>
  return (
    <SplitText
      text={text}
      tag="span"
      className={className}
      splitType="chars"
      delay={28}
      duration={0.9}
      ease="back.out(1.6)"
      effect="rise"
      hoverEffect="lift"
      trigger="load"
      textAlign="left"
    />
  )
}

/**
 * Multi-line display headline animated with React Bits' SplitText (GSAP):
 * characters rise in on load and lift under the cursor on hover.
 */
export default function SplitHeadline({ lines, id, className, accentClassName, animate = true }: SplitHeadlineProps) {
  return (
    <h1 id={id} className={className}>
      {lines.map((line) => {
        const lineClass = line.accent ? accentClassName : undefined
        if (!line.accentEnd) {
          return <Part key={line.text} text={line.text} className={lineClass} animate={animate} />
        }
        return (
          <span key={line.text}>
            <Part text={line.text} className={lineClass} animate={animate} />{' '}
            <Part text={line.accentEnd} className={accentClassName} animate={animate} />
          </span>
        )
      })}
    </h1>
  )
}
