import type { ReactNode } from 'react'
import { Link } from 'react-router'
import styles from './PillButton.module.css'

export type PillVariant = 'solid' | 'outline' | 'soft' | 'accent'

type CommonProps = {
  children: ReactNode
  variant?: PillVariant
  className?: string
}

type LinkProps = CommonProps & { to: string; onClick?: never; pressed?: never }
type ButtonProps = CommonProps & { to?: never; onClick?: () => void; pressed?: boolean }

/** Small pill button (Figma "Pill Button": Solid / Outline / Soft / Accent). */
export default function PillButton(props: LinkProps | ButtonProps) {
  const { children, variant = 'solid', className } = props
  const classes = [styles.pill, styles[variant], className].filter(Boolean).join(' ')

  if (props.to !== undefined) {
    return (
      <Link to={props.to} className={classes}>
        {children}
      </Link>
    )
  }

  return (
    <button type="button" className={classes} onClick={props.onClick} aria-pressed={props.pressed}>
      {children}
    </button>
  )
}
