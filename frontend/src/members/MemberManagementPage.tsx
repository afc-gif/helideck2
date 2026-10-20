import { useCallback, useEffect, useMemo, useState } from 'react'
import { ApiError, api, type OrganizationMember, type OrganizationSummary } from '../lib/api'
import { useAuth } from '../auth/AuthContext'
import './MemberManagementPage.css'

const approvalRoles = [
  { slug: 'surveyor', label: 'Field staff (surveyor)' },
  { slug: 'reviewer', label: 'Reviewer' },
  { slug: 'organization_admin', label: 'Organization admin' },
]

export function MemberManagementPage() {
  const { user } = useAuth()
  const [organizations, setOrganizations] = useState<OrganizationSummary[]>([])
  const [organizationId, setOrganizationId] = useState('')
  const [members, setMembers] = useState<OrganizationMember[]>([])
  const [roles, setRoles] = useState<Record<number, string>>({})
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [approvingId, setApprovingId] = useState<number | null>(null)
  const [notice, setNotice] = useState<string | null>(null)

  const loadMembers = useCallback(async (selectedOrganizationId: string) => {
    if (!selectedOrganizationId) return
    try {
      const response = await api.organizationMembers(selectedOrganizationId)
      setMembers(response)
      setRoles((current) => ({ ...current, ...Object.fromEntries(response.filter((member) => member.status === 'pending').map((member) => [member.id, current[member.id] ?? 'surveyor'])) }))
    } catch {
      setError('Team access requests could not be loaded. Try again.')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    let active = true
    async function loadOrganizations() {
      try {
        const available = await api.organizations()
        const manageable = user?.is_super_admin ? available : available.filter((organization) => user?.memberships.some((membership) => membership.organization.public_id === organization.public_id && membership.permissions.includes('organization.members.manage')))
        if (!active) return
        setOrganizations(manageable)
        const initialOrganizationId = manageable[0]?.public_id ?? ''
        setOrganizationId(initialOrganizationId)
        if (initialOrganizationId) {
          await loadMembers(initialOrganizationId)
        } else {
          setLoading(false)
        }
      } catch {
        if (active) {
          setError('Your organization workspaces could not be loaded.')
          setLoading(false)
        }
      }
    }
    void loadOrganizations()
    return () => { active = false }
  }, [user, loadMembers])

  const pendingMembers = useMemo(() => members.filter((member) => member.status === 'pending'), [members])
  const activeMembers = useMemo(() => members.filter((member) => member.status === 'active'), [members])

  async function approve(member: OrganizationMember) {
    if (!organizationId) return
    setApprovingId(member.id)
    setError(null)
    setNotice(null)
    try {
      await api.approveOrganizationMember(organizationId, member.id, roles[member.id] ?? 'surveyor')
      setNotice(`${member.name} has been approved.`)
      await loadMembers(organizationId)
    } catch (cause) {
      setError(cause instanceof ApiError ? cause.message : 'The account could not be approved. Try again.')
    } finally {
      setApprovingId(null)
    }
  }

  return (
    <section className="members-page">
      <header className="members-heading">
        <div><div className="eyebrow">ORGANIZATION ACCESS</div><h1>Team members</h1><p>Review account requests and choose each person’s role before granting access.</p></div>
        {organizations.length > 1 && <label className="members-organization-picker">Workspace<select value={organizationId} onChange={(event) => { const nextOrganizationId = event.target.value; setLoading(true); setError(null); setOrganizationId(nextOrganizationId); void loadMembers(nextOrganizationId) }}>{organizations.map((organization) => <option key={organization.public_id} value={organization.public_id}>{organization.name}</option>)}</select></label>}
      </header>

      {error && <div className="members-alert members-alert-error" role="alert">{error}</div>}
      {notice && <div className="members-alert members-alert-success" role="status">{notice}</div>}
      {loading ? <div className="members-loading" role="status">Loading organization access…</div> : organizations.length === 0 ? <div className="members-empty"><strong>No workspace administration access</strong><span>Ask an organization admin to grant you member management permission.</span></div> : <>
        <section className="members-summary" aria-label="Team access summary">
          <article><span>Requests waiting</span><strong>{pendingMembers.length}</strong></article>
          <article><span>Approved members</span><strong>{activeMembers.length}</strong></article>
        </section>
        <section className="members-panel">
          <div className="members-panel-heading"><div><span className="section-kicker">REVIEW REQUIRED</span><h2>Access requests</h2><p>New signups cannot sign in until you approve them.</p></div><span className="members-count">{pendingMembers.length} pending</span></div>
          {pendingMembers.length === 0 ? <div className="members-empty"><strong>No pending requests</strong><span>New staff signups will appear here for review.</span></div> : <div className="member-list">{pendingMembers.map((member) => <article className="member-row member-pending" key={member.id}>
            <div className="member-avatar" aria-hidden="true">{member.name.split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase()}</div>
            <div className="member-details"><strong>{member.name}</strong><span>{member.email}</span><small>Waiting for approval</small></div>
            <label className="member-role-picker">Assign role<select value={roles[member.id] ?? 'surveyor'} onChange={(event) => setRoles((current) => ({ ...current, [member.id]: event.target.value }))}>{approvalRoles.map((role) => <option key={role.slug} value={role.slug}>{role.label}</option>)}</select></label>
            <button className="member-approve-button" type="button" onClick={() => void approve(member)} disabled={approvingId !== null}>{approvingId === member.id ? 'Approving…' : 'Approve access'}</button>
          </article>)}</div>}
        </section>
        <section className="members-panel">
          <div className="members-panel-heading"><div><span className="section-kicker">CURRENT ACCESS</span><h2>Approved members</h2><p>People who can currently sign in to this workspace.</p></div><span className="members-count">{activeMembers.length} members</span></div>
          {activeMembers.length === 0 ? <div className="members-empty"><strong>No approved members yet</strong></div> : <div className="member-list">{activeMembers.map((member) => <article className="member-row" key={member.id}>
            <div className="member-avatar" aria-hidden="true">{member.name.split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase()}</div><div className="member-details"><strong>{member.name}</strong><span>{member.email}</span></div><span className="member-role-badge">{approvalRoles.find((role) => role.slug === member.role)?.label ?? member.role}</span>
          </article>)}</div>}
        </section>
      </>}
      <p className="members-footnote">Each person’s account is limited to the organization workspace they selected during signup.</p>
    </section>
  )
}
