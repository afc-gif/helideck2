import { useEffect, useState, type FormEvent } from 'react'
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router-dom'
import { useAuth } from './AuthContext'
import { ApiError, api, type RegistrationOrganization } from '../lib/api'

export function RegistrationPage() {
  const { user, loading } = useAuth()
  const navigate = useNavigate()
  const [organizations, setOrganizations] = useState<RegistrationOrganization[]>([])
  const [accountType, setAccountType] = useState<'organization' | 'field_staff'>('field_staff')
  const [organizationId, setOrganizationId] = useState('')
  const [organizationName, setOrganizationName] = useState('')
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  useEffect(() => {
    let active = true
    api.registrationOrganizations()
      .then((available) => {
        if (!active) return
        setOrganizations(available)
        setOrganizationId(available[0]?.public_id ?? '')
      })
      .catch(() => { if (active) setError('We could not load the organization list. Please try again later.') })
    return () => { active = false }
  }, [])

  if (user) return <Navigate to="/" replace />
  if (loading) return <div className="auth-loading" role="status">Restoring your secure session…</div>

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)
    setFieldErrors({})
    setSubmitting(true)
    try {
      await api.register({
        account_type: accountType,
        ...(accountType === 'organization' ? { organization_name: organizationName } : { organization_public_id: organizationId }),
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
      })
      navigate(`/registration-submitted?type=${accountType}`, { replace: true })
    } catch (cause) {
      if (cause instanceof ApiError) {
        setFieldErrors(cause.errors)
        setError(cause.status === 422 ? 'Please correct the fields marked below.' : cause.message)
      } else {
        setError('Unable to submit your request. Check your connection and try again.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <main className="login-page">
      <section className="login-card registration-card" aria-labelledby="registration-title">
        <div className="login-brand"><span className="brand-mark">A</span><span className="brand-name">ARTSCI<span>FIELD OPERATIONS</span></span></div>
        <div className="login-intro"><div className="eyebrow">ARTSCI SURVEY PLATFORM</div><h1 id="registration-title">Create an account</h1><p>Choose whether you’re registering an organization or requesting access as field staff.</p></div>
        {error && <div className="login-error" role="alert">{error}</div>}
        <form onSubmit={handleSubmit}>
          <label htmlFor="account-type">I’m signing up as</label>
          <select id="account-type" value={accountType} onChange={(event) => setAccountType(event.target.value as 'organization' | 'field_staff')}>
            <option value="field_staff">Field staff</option>
            <option value="organization">An organization</option>
          </select>
          {accountType === 'organization' ? <>
            <label htmlFor="organization-name">Organization name</label>
            <input id="organization-name" autoComplete="organization" value={organizationName} onChange={(event) => setOrganizationName(event.target.value)} required aria-invalid={Boolean(fieldErrors.organization_name)} />
            {fieldErrors.organization_name && <span className="field-error">{fieldErrors.organization_name[0]}</span>}
            <p className="registration-approval-note">ARTSCI will review your organization application. If approved, you’ll become its first organization administrator.</p>
          </> : <>
            <label htmlFor="organization">Organization</label>
            <select id="organization" value={organizationId} onChange={(event) => setOrganizationId(event.target.value)} required disabled={!organizations.length}>
              {organizations.map((organization) => <option key={organization.public_id} value={organization.public_id}>{organization.name}</option>)}
            </select>
            {organizations.length === 0 && <span className="auth-field-hint">No organizations are currently accepting field staff requests.</span>}
            {fieldErrors.organization_public_id && <span className="field-error">{fieldErrors.organization_public_id[0]}</span>}
          </>}
          <label htmlFor="registration-name">Full name</label>
          <input id="registration-name" autoComplete="name" value={name} onChange={(event) => setName(event.target.value)} required aria-invalid={Boolean(fieldErrors.name)} />
          {fieldErrors.name && <span className="field-error">{fieldErrors.name[0]}</span>}
          <label htmlFor="registration-email">Work email</label>
          <input id="registration-email" type="email" autoComplete="email" value={email} onChange={(event) => setEmail(event.target.value)} required aria-invalid={Boolean(fieldErrors.email)} />
          {fieldErrors.email && <span className="field-error">{fieldErrors.email[0]}</span>}
          <label htmlFor="registration-password">Password</label>
          <input id="registration-password" type="password" autoComplete="new-password" minLength={14} value={password} onChange={(event) => setPassword(event.target.value)} required aria-invalid={Boolean(fieldErrors.password)} />
          <span className="auth-field-hint">Use at least 14 characters.</span>
          <label htmlFor="registration-password-confirmation">Confirm password</label>
          <input id="registration-password-confirmation" type="password" autoComplete="new-password" value={passwordConfirmation} onChange={(event) => setPasswordConfirmation(event.target.value)} required />
          {fieldErrors.password && <span className="field-error">{fieldErrors.password[0]}</span>}
          <button className="primary-button login-submit" type="submit" disabled={submitting || (accountType === 'field_staff' && !organizationId) || (accountType === 'organization' && !organizationName.trim())}>{submitting ? 'Sending request…' : accountType === 'organization' ? 'Apply for organization access' : 'Request field staff access'} <span>→</span></button>
        </form>
        <p className="login-help">Already have an account? <Link to="/login">Sign in</Link></p>
      </section>
      <footer className="login-footer">Access is enabled after administrator approval</footer>
    </main>
  )
}

export function RegistrationSubmittedPage() {
  const [searchParams] = useSearchParams()
  const organizationRequest = searchParams.get('type') === 'organization'
  return (
    <main className="login-page">
      <section className="login-card" aria-labelledby="registration-submitted-title">
        <div className="login-brand"><span className="brand-mark">A</span><span className="brand-name">ARTSCI<span>FIELD OPERATIONS</span></span></div>
        <div className="login-intro"><div className="eyebrow">REQUEST RECEIVED</div><h1 id="registration-submitted-title">Thank you</h1><p>{organizationRequest ? 'Your organization application is waiting for ARTSCI review. If approved, you’ll be able to sign in as its first administrator.' : 'Your field staff request is waiting for the organization administrator to approve it and assign your role.'}</p></div>
        <Link className="primary-button login-submit registration-back-link" to="/login">Return to sign in</Link>
      </section>
      <footer className="login-footer">You can sign in after your request is approved</footer>
    </main>
  )
}
