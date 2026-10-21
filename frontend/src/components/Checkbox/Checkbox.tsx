import type { InputHTMLAttributes, ReactNode } from 'react'
import checkIcon from '../../assets/auth/icon-check.svg'
import styles from './Checkbox.module.css'

type CheckboxProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'type' | 'className'> & {
  children: ReactNode
}

/** Rounded violet checkbox with a label (Remember me, Terms). */
export default function Checkbox({ children, ...inputProps }: CheckboxProps) {
  return (
    <label className={styles.checkbox}>
      <input type="checkbox" className={styles.input} {...inputProps} />
      <span className={styles.box} aria-hidden="true">
        <img src={checkIcon} width={14} height={14} alt="" />
      </span>
      <span className={styles.label}>{children}</span>
    </label>
  )
}
