import { useState, type FormEvent } from 'react'
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from './AuthContext'
import { ApiError } from '../lib/api'

export function LoginPage() {
  const { user, loading, login, error: authError } = useAuth()
  const location = useLocation()
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [submitting, setSubmitting] = useState(false)

  if (user) {
    return <Navigate to="/" replace />
  }
  if (loading) {
    return <div className="auth-loading" role="status">Restoring your secure session…</div>
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setFieldErrors({})
    setSubmitting(true)

    try {
      await login(email, password)
      const destination = typeof location.state?.from === 'string' ? location.state.from : '/'
      navigate(destination, { replace: true })
    } catch (cause) {
      if (cause instanceof ApiError) {
        setFieldErrors(cause.errors)
        setError(cause.status === 422 ? 'Email or password is incorrect.' : cause.message)
      } else {
        setError('Unable to sign in. Check your connection and try again.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <main className="login-page">
      <section className="login-card" aria-labelledby="login-title">
        <div className="login-brand"><span className="brand-mark">H</span><span className="brand-name">Helideck Report<span>SURVEY OPERATIONS</span></span></div>
        <div className="login-intro"><div className="eyebrow">HELIDECK REPORT</div><h1 id="login-title">Welcome back</h1><p>Sign in to continue to your field workspace.</p></div>
        {(error || authError) && <div className="login-error" role="alert">{error ?? authError}</div>}
        <form onSubmit={handleSubmit} noValidate>
          <label htmlFor="email">Work email</label>
          <input id="email" name="email" type="email" autoComplete="username" value={email} onChange={(event) => setEmail(event.target.value)} required aria-invalid={Boolean(fieldErrors.email)} aria-describedby={fieldErrors.email ? 'email-error' : undefined} />
          {fieldErrors.email && <span className="field-error" id="email-error">{fieldErrors.email[0]}</span>}
          <div className="password-label"><label htmlFor="password">Password</label></div>
          <input id="password" name="password" type="password" autoComplete="current-password" value={password} onChange={(event) => setPassword(event.target.value)} required aria-invalid={Boolean(fieldErrors.password)} aria-describedby={fieldErrors.password ? 'password-error' : undefined} />
          {fieldErrors.password && <span className="field-error" id="password-error">{fieldErrors.password[0]}</span>}
          <button className="primary-button login-submit" type="submit" disabled={submitting}>{submitting ? 'Signing in…' : 'Sign in'} <span>→</span></button>
        </form>
        <p className="login-help">New to Helideck Report? <Link to="/register">Create an account</Link><br />Your organization administrator must approve your request before sign-in.</p>
      </section>
      <footer className="login-footer">Secure access for Helideck Report</footer>
    </main>
  )
}
