import type { ReactNode } from 'react'
import { Link } from 'react-router'
import styles from './PillButton.module.css'

export type PillVariant = 'solid' | 'outline' | 'soft' | 'accent'

type CommonProps = {
  children: ReactNode
  variant?: PillVariant
  className?: string
}

type Never<K extends string> = { [P in K]?: never }

/** In-app route. */
type LinkProps = CommonProps & { to: string } & Never<'href' | 'onClick' | 'pressed' | 'type' | 'disabled'>
/** External page, opened in a new tab. */
type ExternalProps = CommonProps & { href: string } & Never<'to' | 'onClick' | 'pressed' | 'type' | 'disabled'>
type ButtonProps = CommonProps & {
  onClick?: () => void
  pressed?: boolean
  type?: 'button' | 'submit'
  disabled?: boolean
} & Never<'to' | 'href'>

/** Small pill button (Figma "Pill Button": Solid / Outline / Soft / Accent). */
export default function PillButton(props: LinkProps | ExternalProps | ButtonProps) {
  const { children, variant = 'solid', className } = props
  const classes = [styles.pill, styles[variant], className].filter(Boolean).join(' ')

  if (props.to !== undefined) {
    return (
      <Link to={props.to} className={classes}>
        {children}
      </Link>
    )
  }

  if (props.href !== undefined) {
    return (
      <a href={props.href} className={classes} target="_blank" rel="noreferrer">
        {children}
      </a>
    )
  }

  return (
    <button
      type={props.type ?? 'button'}
      className={classes}
      onClick={props.onClick}
      aria-pressed={props.pressed}
      disabled={props.disabled}
    >
      {children}
    </button>
  )
}
