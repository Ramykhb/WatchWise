import { Link } from 'react-router'
import arrowIcon from '../../assets/landing/icon-arrow.svg'
import styles from './ButtonPrimary.module.css'

type CommonProps = {
  label: string
  /** Stretch to the container width with the arrow pushed to the end (auth forms). */
  fullWidth?: boolean
  className?: string
  disabled?: boolean
}

type LinkProps = CommonProps & { to: string; type?: never }
type ButtonProps = CommonProps & { to?: never; type?: 'button' | 'submit'; disabled?: boolean }

/** Pill-shaped primary call to action with trailing arrow. */
export default function ButtonPrimary(props: LinkProps | ButtonProps) {
  const { label, fullWidth = false, className } = props
  const classes = [styles.button, fullWidth && styles.fullWidth, className].filter(Boolean).join(' ')
  const content = (
    <>
      <span className={styles.label}>{label}</span>
      <span className={styles.icon} aria-hidden="true">
        <img src={arrowIcon} width={22} height={22} alt="" />
      </span>
    </>
  )

  if (props.to !== undefined) {
    return (
      <Link to={props.to} className={classes}>
        {content}
      </Link>
    )
  }

  return (
    <button type={props.type ?? 'button'} disabled={props.disabled} className={classes} aria-busy={props.disabled || undefined}>
      {content}
    </button>
  )
}
