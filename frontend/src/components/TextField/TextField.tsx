import { useId, useState, type InputHTMLAttributes, type ReactNode } from 'react'
import styles from './TextField.module.css'

type TextFieldProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'id' | 'className'> & {
  label: string
  /** Adds a Show/Hide toggle (password fields). */
  revealable?: boolean
  hint?: ReactNode
  error?: string
}

/** Labelled text field. Use `revealable` for password fields. */
export default function TextField({ label, revealable = false, hint, error, type = 'text', ...inputProps }: TextFieldProps) {
  const id = useId()
  const [revealed, setRevealed] = useState(false)
  const describedBy = [hint && `${id}-hint`, error && `${id}-error`].filter(Boolean).join(' ') || undefined

  return (
    <div className={styles.field}>
      <label htmlFor={id} className={styles.label}>
        {label}
      </label>
      <div className={[styles.control, error && styles.invalid].filter(Boolean).join(' ')}>
        <input
          id={id}
          type={revealable && revealed ? 'text' : type}
          className={styles.input}
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy}
          {...inputProps}
        />
        {revealable && (
          <button
            type="button"
            className={styles.toggle}
            aria-pressed={revealed}
            aria-label={revealed ? 'Hide password' : 'Show password'}
            onClick={() => setRevealed((value) => !value)}
          >
            {revealed ? 'Hide' : 'Show'}
          </button>
        )}
      </div>
      {hint && (
        <p id={`${id}-hint`} className={styles.hint}>
          {hint}
        </p>
      )}
      {error && (
        <p id={`${id}-error`} className={styles.error} role="alert">
          {error}
        </p>
      )}
    </div>
  )
}
