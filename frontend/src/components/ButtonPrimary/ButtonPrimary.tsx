import { Link } from 'react-router'
import arrowIcon from '../../assets/landing/icon-arrow.svg'
import styles from './ButtonPrimary.module.css'

type ButtonPrimaryProps = {
  label: string
  to: string
  className?: string
}

/** Pill-shaped primary call to action with trailing arrow. */
export default function ButtonPrimary({ label, to, className }: ButtonPrimaryProps) {
  return (
    <Link to={to} className={[styles.button, className].filter(Boolean).join(' ')}>
      <span className={styles.label}>{label}</span>
      <span className={styles.icon} aria-hidden="true">
        <img src={arrowIcon} width={22} height={22} alt="" />
      </span>
    </Link>
  )
}
