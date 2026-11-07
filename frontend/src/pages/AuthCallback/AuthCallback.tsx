import { LoadingIndicator } from '../../components/LoadingState/LoadingState.tsx'
import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { useAuth } from '../../contexts/useAuth.ts'
import { authApi, type AuthUser } from '../../lib/auth.ts'

export default function AuthCallback() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const { setUser } = useAuth()
  const [message, setMessage] = useState('')

  useEffect(() => {
    let active = true
    const complete = async () => {
      if (params.get('status') !== 'success') {
        const reason = params.get('reason')
        setMessage(reason === 'email_required' ? 'Your provider account needs an email address.' : 'Social sign-in failed. Please try again.')
        return
      }
      try {
        const response = await authApi.get<{ user: AuthUser }>('/auth/me')
        if (active) {
          setUser(response.data.user)
          navigate('/home-page', { replace: true })
        }
      } catch {
        if (active) setMessage('Could not complete sign-in. Please try again.')
      }
    }
    void complete()
    return () => { active = false }
  }, [navigate, params, setUser])

  return <main style={{ padding: '4rem', maxWidth: 720, margin: '0 auto' }}>{message ? <p role="alert">{message}</p> : <LoadingIndicator label="Completing sign-in…" />}</main>
}
