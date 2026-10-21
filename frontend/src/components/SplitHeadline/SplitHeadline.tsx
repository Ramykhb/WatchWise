import SplitText from '../reactbits/SplitText/SplitText.tsx'

export type HeadlineLine = { text: string; accent?: boolean }

type SplitHeadlineProps = {
  lines: HeadlineLine[]
  id?: string
  className?: string
  /** Class applied to accent lines (e.g. the coral "frame."). */
  accentClassName?: string
  /** Set false to render static text (e.g. during a page-swap view transition). */
  animate?: boolean
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
        if (!animate) {
          return (
            <span key={line.text} className={lineClass}>
              {line.text}
            </span>
          )
        }
        return (
          <SplitText
            key={line.text}
            text={line.text}
            tag="span"
            className={lineClass}
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
      })}
    </h1>
  )
}
