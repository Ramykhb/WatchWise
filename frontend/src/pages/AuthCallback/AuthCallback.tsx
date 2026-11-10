import { LoadingIndicator } from '../../components/LoadingState/LoadingState.tsx'
import { useEffect, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { useAuth } from '../../contexts/useAuth.ts'
import { authApi, type AuthUser } from '../../lib/auth.ts'
import { callbackFailureMessage, SESSION_FAILURE, type CallbackFailure } from './authCallback.ts'

export default function AuthCallback() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const { setUser } = useAuth()
  const [failure, setFailure] = useState<CallbackFailure | null>(null)

  useEffect(() => {
    let active = true
    const complete = async () => {
      if (params.get('status') !== 'success') {
        setFailure(callbackFailureMessage(params.get('reason')))
        return
      }
      try {
        const response = await authApi.get<{ user: AuthUser }>('/auth/me')
        if (active) {
          setUser(response.data.user)
          navigate('/home-page', { replace: true })
        }
      } catch {
        if (active) setFailure(SESSION_FAILURE)
      }
    }
    void complete()
    return () => { active = false }
  }, [navigate, params, setUser])

  return (
    <main style={{ padding: '4rem 1rem', maxWidth: 720, margin: '0 auto' }}>
      {failure ? (
        <div role="alert">
          <h1>{failure.title}</h1>
          <p>{failure.body}</p>
          <p><Link to="/signin" replace>Back to sign in</Link></p>
        </div>
      ) : <LoadingIndicator label="Completing sign-in…" />}
    </main>
  )
}
