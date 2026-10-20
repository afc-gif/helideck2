import { useCallback, useEffect, useState } from 'react'
import { ApiError, api, type OrganizationRequest } from '../lib/api'
import './OrganizationRequestsPage.css'

export function OrganizationRequestsPage() {
  const [requests, setRequests] = useState<OrganizationRequest[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [approvingId, setApprovingId] = useState<string | null>(null)

  const refresh = useCallback(async () => {
    try {
      setRequests(await api.organizationRequests())
    } catch (cause) {
      setError(cause instanceof ApiError && cause.status === 403 ? 'Only ARTSCI platform administrators can review organization applications.' : 'Organization applications could not be loaded. Try again.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    let active = true
    api.organizationRequests()
      .then((pendingRequests) => { if (active) setRequests(pendingRequests) })
      .catch((cause: unknown) => {
        if (!active) return
        setError(cause instanceof ApiError && cause.status === 403 ? 'Only ARTSCI platform administrators can review organization applications.' : 'Organization applications could not be loaded. Try again.')
      })
      .finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [])

  async function approve(request: OrganizationRequest) {
    setApprovingId(request.public_id)
    setError(null)
    setNotice(null)
    try {
      await api.approveOrganizationRequest(request.public_id)
      setNotice(`${request.name} and its first administrator have been approved.`)
      await refresh()
    } catch (cause) {
      setError(cause instanceof ApiError ? cause.message : 'The organization could not be approved. Try again.')
    } finally {
      setApprovingId(null)
    }
  }

  return (
    <section className="organization-requests-page">
      <header className="organization-requests-heading"><div><div className="eyebrow">ARTSCI PLATFORM ADMINISTRATION</div><h1>Organization applications</h1><p>Review organizations requesting a workspace. Approval activates the workspace and its first administrator.</p></div><span className="organization-request-count">{requests.length} waiting</span></header>
      {error && <div className="organization-request-alert organization-request-error" role="alert">{error}</div>}
      {notice && <div className="organization-request-alert organization-request-success" role="status">{notice}</div>}
      <section className="organization-request-panel">
        <div className="organization-request-panel-heading"><div><span className="section-kicker">AWAITING ARTSCI REVIEW</span><h2>New workspaces</h2></div></div>
        {loading ? <div className="organization-request-empty" role="status">Loading applications…</div> : requests.length === 0 ? <div className="organization-request-empty"><strong>No applications waiting</strong><span>New organization signup requests will appear here.</span></div> : <div className="organization-request-list">{requests.map((request) => <article className="organization-request-row" key={request.public_id}>
          <div className="organization-request-mark" aria-hidden="true">{request.name.trim().charAt(0).toUpperCase()}</div>
          <div className="organization-request-details"><strong>{request.name}</strong><span>Organization workspace application</span>{request.contact && <small>First admin: {request.contact.name} · {request.contact.email}</small>}<small>Submitted {request.requested_at ? new Date(request.requested_at).toLocaleDateString() : 'date unavailable'}</small></div>
          <button className="organization-approve-button" type="button" onClick={() => void approve(request)} disabled={approvingId !== null}>{approvingId === request.public_id ? 'Approving…' : 'Approve organization'}</button>
        </article>)}</div>}
      </section>
      <p className="organization-request-footnote">After approval, the first administrator can invite or approve staff. ARTSCI platform administrators retain platform-wide oversight.</p>
    </section>
  )
}
