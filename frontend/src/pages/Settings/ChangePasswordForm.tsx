import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import PillButton from '../../components/PillButton/PillButton.tsx'
import TextField from '../../components/TextField/TextField.tsx'
import { PASSWORD_PATTERN, changePassword, settlePassword } from '../../lib/password.ts'
import styles from './Settings.module.css'

type Errors = Partial<Record<'current' | 'next' | 'confirm', string>>

/** For accounts that have a password: the current one, then a new one and its confirmation. */
export default function ChangePasswordForm({ onDone, onNote }: { onDone: () => Promise<void>; onNote: (note: string) => void }) {
  const [errors, setErrors] = useState<Errors>({})

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const form = event.currentTarget
    const data = new FormData(form)
    const current = String(data.get('current') ?? '')
    const next = String(data.get('next') ?? '')
    const confirm = String(data.get('confirm') ?? '')
    const found: Errors = {}
    if (!current) found.current = 'Enter your current password.'
    if (!PASSWORD_PATTERN.test(next)) found.next = 'Use at least 8 characters, including a number.'
    else if (next === current) found.next = 'Pick a password you have not used here.'
    if (confirm !== next) found.confirm = "Passwords don't match."
    setErrors(found)
    if (Object.keys(found).length > 0) {
      onNote('')
      return
    }
    onNote('Saving…')
    const result = await settlePassword(
      changePassword({ current_password: current, password: next, password_confirmation: confirm }),
      "Couldn't update your password.",
    )
    if (result.ok) {
      form.reset()
      onNote('Password updated. Other devices have been signed out.')
      return
    }
    onNote(result.message)
    if (result.conflict) await onDone().catch(() => undefined)
    else setErrors({ current: result.errors.current_password, next: result.errors.password })
  }

  return (
    <form className={styles.form} onSubmit={handleSubmit} noValidate aria-label="Change password">
      <div className={styles.row3}>
        <TextField label="Current password" name="current" type="password" placeholder="••••••••••" autoComplete="current-password" revealable error={errors.current} />
        <TextField label="New password" name="next" type="password" placeholder="••••••••••••" autoComplete="new-password" revealable error={errors.next} />
        <TextField label="Confirm new password" name="confirm" type="password" placeholder="••••••••••••" autoComplete="new-password" revealable error={errors.confirm} />
      </div>
      <div className={styles.actions}>
        <PillButton type="submit">Update password</PillButton>
        <Link to="/forgot-password" className={styles.textLink}>
          Forgot your password?
        </Link>
      </div>
    </form>
  )
}
