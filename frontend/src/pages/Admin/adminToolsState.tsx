import { useRef, useState } from 'react'
import { apiErrorMessage } from '../../lib/auth.ts'
import s from './AdminTools.module.css'
export { s }
export const dateText = (value?: string | null) => (value ? new Date(value).toLocaleString() : '—')
export function useAction() {
  const [busy, setBusy] = useState(false)
  const [message, setMessage] = useState('')
  const running = useRef(false)
  const run = async (work: () => Promise<unknown>, success = 'Saved.', after?: () => void) => {
    if (running.current) return
    running.current = true
    setBusy(true)
    setMessage('')
    try {
      await work()
      setMessage(success)
      after?.()
    } catch (error) {
      setMessage(apiErrorMessage(error, 'Unable to complete this action. Please try again.'))
    } finally {
      running.current = false
      setBusy(false)
    }
  }
  return {
    busy,
    message,
    setMessage,
    run,
    notice: message ? (
      <p className={s.notice} role="status">
        {message}
      </p>
    ) : null,
  }
}
