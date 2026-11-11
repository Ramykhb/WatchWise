import { useState, type FormEvent } from 'react'
import PillButton from '../../components/PillButton/PillButton.tsx'
import TextField from '../../components/TextField/TextField.tsx'
import { passwordRuleError, setPassword, settlePassword } from '../../lib/password.ts'
import styles from './Settings.module.css'

type Errors = Partial<Record<'next' | 'confirm', string>>

/** For accounts without a password (Google/GitHub sign-ups): a new password and its confirmation, nothing to enter first. */
export default function SetPasswordForm({ onDone, onNote }: { onDone: () => Promise<void>; onNote: (note: string) => void }) {
  const [errors, setErrors] = useState<Errors>({})

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const form = event.currentTarget
    const data = new FormData(form)
    const next = String(data.get('next') ?? '')
    const confirm = String(data.get('confirm') ?? '')
    const found: Errors = {}
    if (passwordRuleError(next)) found.next = passwordRuleError(next) ?? undefined
    if (confirm !== next) found.confirm = "Passwords don't match."
    setErrors(found)
    if (Object.keys(found).length > 0) {
      onNote('')
      return
    }
    onNote('Saving…')
    const result = await settlePassword(setPassword({ password: next, password_confirmation: confirm }), "Couldn't set your password.")
    if (result.ok) {
      form.reset()
      onNote('Password set. You can now also sign in with your email.')
    } else {
      onNote(result.message)
      if (!result.conflict) setErrors({ next: result.errors.password, confirm: result.errors.password_confirmation })
    }
    // A success or a 409 both mean the account's password state changed (or was stale): reload it.
    if (result.ok || result.conflict) await onDone().catch(() => undefined)
  }

  return (
    <form className={styles.form} onSubmit={handleSubmit} noValidate aria-label="Set password">
      <div className={styles.row3}>
        <TextField label="New password" name="next" type="password" placeholder="••••••••••••" autoComplete="new-password" revealable error={errors.next} />
        <TextField label="Confirm new password" name="confirm" type="password" placeholder="••••••••••••" autoComplete="new-password" revealable error={errors.confirm} />
      </div>
      <div className={styles.actions}>
        <PillButton type="submit">Set password</PillButton>
      </div>
    </form>
  )
}
