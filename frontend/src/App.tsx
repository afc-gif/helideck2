import { useEffect, useMemo, useState } from 'react'
import { BrowserRouter, Link, NavLink, Outlet, Route, Routes, useLocation } from 'react-router-dom'
import { useAuth } from './auth/AuthContext'
import { api, apiUrl, type SurveySummary } from './lib/api'
import { LoginPage } from './auth/LoginPage'
import { RegistrationPage, RegistrationSubmittedPage } from './auth/RegistrationPage'
import { ProtectedRoute } from './auth/ProtectedRoute'
import { MemberManagementPage } from './members/MemberManagementPage'
import { OrganizationRequestsPage } from './organizations/OrganizationRequestsPage'
import { ChecklistAdministrationPage } from './checklists/ChecklistAdministrationPage'
import { SurveyTakingPage } from './surveys/SurveyTakingPage'
import './App.css'
import './theme.css'

type ThemeMode = 'light' | 'dark'

function preferredTheme(): ThemeMode {
  try {
    const savedTheme = window.localStorage.getItem('artsci-theme')
    if (savedTheme === 'dark' || savedTheme === 'light') return savedTheme
  } catch {
    // Use the system preference when browser storage is unavailable.
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

const navigation: { label: string; path: string; icon: string; permission?: string[] }[] = [
  { label: 'Dashboard', path: '/', icon: '▦' },
  { label: 'Inspections', path: '/inspections', icon: '▤', permission: ['inspections.manage', 'inspections.assigned.view', 'reviews.manage'] },
  { label: 'My Assignments', path: '/assignments', icon: '✓', permission: ['inspections.assigned.view'] },
  { label: 'Checklists', path: '/checklists', icon: '☷', permission: ['checklists.manage'] },
  { label: 'Locations', path: '/locations', icon: '⌖', permission: ['organizations.view'] },
  { label: 'Reviews', path: '/reviews', icon: '◷', permission: ['reviews.manage'] },
  { label: 'Team', path: '/team', icon: '♙', permission: ['organization.members.manage'] },
  { label: 'Organization requests', path: '/organization-requests', icon: '▣', permission: ['platform.roles.view'] },
]

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<LoginPage />} />
        <Route path="/register" element={<RegistrationPage />} />
        <Route path="/registration-submitted" element={<RegistrationSubmittedPage />} />
        <Route element={<ProtectedRoute />}>
          <Route element={<Shell />}>
            <Route path="/" element={<Dashboard />} />
            <Route path="/checklists" element={<ChecklistAdministrationPage />} />
            <Route path="/team" element={<MemberManagementPage />} />
            <Route path="/organization-requests" element={<OrganizationRequestsPage />} />
            {navigation.slice(1).filter((item) => item.path !== '/checklists' && item.path !== '/team' && item.path !== '/organization-requests').map((item) => (
              <Route key={item.path} path={item.path} element={item.path === '/inspections' ? <SurveyTakingPage /> : <EmptySection title={item.label} />} />
            ))}
            <Route path="*" element={<EmptySection title="Page not found" />} />
          </Route>
        </Route>
      </Routes>
    </BrowserRouter>
  )
}

function Shell() {
  const { user, logout } = useAuth()
  const [menuOpen, setMenuOpen] = useState(false)
  const [logoutError, setLogoutError] = useState<string | null>(null)
  const [theme, setTheme] = useState<ThemeMode>(preferredTheme)
  const location = useLocation()
  const visibleNavigation = navigation.filter((item) => !item.permission || user?.is_super_admin || user?.memberships.some((membership) => item.permission?.some((permission) => membership.permissions.includes(permission))))
  const initials = user?.name.split(/\s+/).slice(0, 2).map((name) => name[0]).join('').toUpperCase() ?? 'A'
  const currentRole = user?.is_super_admin ? 'Super Administrator' : user?.memberships[0]?.role.replaceAll('_', ' ') ?? 'Member'

  useEffect(() => {
    try {
      window.localStorage.setItem('artsci-theme', theme)
    } catch {
      // The selected theme still applies for this session.
    }
  }, [theme])

  return (
    <div className="app-shell" data-theme={theme}>
      <aside className={`sidebar ${menuOpen ? 'sidebar-open' : ''}`}>
        <Link className="brand" to="/" aria-label="ARTSCI Survey home">
          <span className="brand-mark">A</span>
          <span className="brand-name">ARTSCI<span>SURVEY OPERATIONS</span></span>
        </Link>
        <div className="workspace-label">{user?.is_super_admin ? 'PLATFORM' : user?.memberships[0]?.organization.name.toUpperCase() ?? 'WORKSPACE'}</div>
        <nav className="nav-list" aria-label="Main navigation">
          {visibleNavigation.map((item) => (
            <NavLink key={item.path} to={item.path} end={item.path === '/'} onClick={() => setMenuOpen(false)} className={({ isActive }) => `nav-item ${isActive ? 'nav-active' : ''}`}>
              <span className="nav-icon" aria-hidden="true">{item.icon}</span>
              {item.label}
              {item.label === 'Reviews' && <span className="nav-count">0</span>}
            </NavLink>
          ))}
        </nav>
        <div className="sidebar-footer">
          <div className="sidebar-help-icon">i</div>
          <div><strong>Need a hand?</strong><span>View field guide</span></div>
          <span className="help-arrow">↗</span>
        </div>
      </aside>

      <div className="main-column">
        <header className="topbar">
          <button className="mobile-menu" type="button" onClick={() => setMenuOpen(!menuOpen)} aria-label="Toggle navigation">☰</button>
          <div className="breadcrumbs"><span>ARTSCI</span><b>/</b><strong>{navigation.find((item) => item.path === location.pathname)?.label ?? 'Workspace'}</strong></div>
          <div className="topbar-right"><ApiStatus /><button className="theme-toggle" type="button" onClick={() => setTheme(theme === 'light' ? 'dark' : 'light')} aria-label={`Switch to ${theme === 'light' ? 'dark' : 'light'} mode`} title={`Switch to ${theme === 'light' ? 'dark' : 'light'} mode`}><span aria-hidden="true">{theme === 'light' ? '☾' : '☀'}</span><span className="theme-toggle-label">{theme === 'light' ? 'Dark mode' : 'Light mode'}</span></button><span className="topbar-divider" /><span className="avatar" aria-hidden="true">{initials}</span><div className="user-meta"><strong>{user?.name}</strong><span>{currentRole}</span></div><button className="sign-out-button" type="button" onClick={() => { setLogoutError(null); void logout().catch(() => setLogoutError('Sign out failed. Check your connection and retry.')) }}>Sign out</button></div>
        </header>
        {logoutError && <div className="logout-error" role="alert">{logoutError}</div>}
        <main className="page-content"><Outlet /></main>
      </div>
    </div>
  )
}

function ApiStatus() {
  const [apiReady, setApiReady] = useState<boolean | null>(null)
  useEffect(() => {
    const controller = new AbortController()
    fetch(apiUrl('/api/health'), { signal: controller.signal })
      .then((response) => setApiReady(response.ok))
      .catch(() => setApiReady(false))
    return () => controller.abort()
  }, [])

  return <span className={`connection-status ${apiReady === false ? 'connection-offline' : ''}`}><i />{apiReady === null ? 'Connecting' : apiReady ? 'API connected' : 'API unavailable'}</span>
}

function Dashboard() {
  const { user } = useAuth()
  const [surveys, setSurveys] = useState<SurveySummary[]>([])
  const [loadingSurveys, setLoadingSurveys] = useState(true)
  const [surveyError, setSurveyError] = useState<string | null>(null)
  const [currentDate] = useState(() => new Date())
  const firstName = user?.name.trim().split(/\s+/)[0] ?? 'there'
  const today = new Intl.DateTimeFormat(undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(currentDate)
  const greeting = currentDate.getHours() < 12 ? 'Good morning' : currentDate.getHours() < 18 ? 'Good afternoon' : 'Good evening'
  const canViewSurveys = Boolean(user?.is_super_admin || user?.memberships.some((membership) => membership.permissions.some((permission) => ['inspections.manage', 'inspections.assigned.view'].includes(permission))))
  const canManageChecklists = Boolean(user?.is_super_admin || user?.memberships.some((membership) => membership.permissions.includes('checklists.manage')))
  const canManageMembers = Boolean(user?.is_super_admin || user?.memberships.some((membership) => membership.permissions.includes('organization.members.manage')))
  const metrics = useMemo(() => {
    return {
      active: surveys.filter((survey) => survey.status === 'in_progress').length,
      completed: surveys.filter((survey) => {
        if (survey.status !== 'completed' || !survey.completed_at) return false
        const completed = new Date(survey.completed_at)
        return completed.getMonth() === currentDate.getMonth() && completed.getFullYear() === currentDate.getFullYear()
      }).length,
      total: surveys.length,
    }
  }, [currentDate, surveys])

  useEffect(() => {
    let active = true
    async function loadSurveys() {
      setLoadingSurveys(true)
      setSurveyError(null)
      try {
        if (!canViewSurveys || !user) {
          if (active) setSurveys([])
          return
        }
        const organizationIds = user.is_super_admin
          ? (await api.organizations()).map((organization) => organization.public_id)
          : [...new Set(user.memberships.filter((membership) => membership.permissions.some((permission) => ['inspections.manage', 'inspections.assigned.view'].includes(permission))).map((membership) => membership.organization.public_id))]
        const results = await Promise.all(organizationIds.map((organizationId) => api.surveys(organizationId)))
        if (active) setSurveys(results.flat().sort((first, second) => new Date(second.started_at).getTime() - new Date(first.started_at).getTime()))
      } catch {
        if (active) setSurveyError('Inspection activity could not be loaded. Try refreshing the page.')
      } finally {
        if (active) setLoadingSurveys(false)
      }
    }
    void loadSurveys()
    return () => { active = false }
  }, [canViewSurveys, user])

  const recentSurveys = surveys.slice(0, 5)
  return (
    <section className="dashboard-page">
      <div className="dashboard-hero">
        <div className="dashboard-hero-copy"><div className="eyebrow">OPERATIONS OVERVIEW <span className="hero-eyebrow-divider">·</span> {today.toUpperCase()}</div><h1>{greeting}, {firstName}</h1><p>{user?.is_super_admin ? 'Review organization applications and oversee survey operations across ARTSCI workspaces.' : 'A clear view of inspections and tools available in your workspace.'}</p>
          <div className="dashboard-hero-actions">{canViewSurveys && <NavLink className="primary-button" to="/inspections"><span>＋</span> Start an inspection</NavLink>}{canManageChecklists && <NavLink className="hero-secondary-link" to="/checklists">Manage checklists <span>↗</span></NavLink>}{user?.is_super_admin && <NavLink className="hero-secondary-link" to="/organization-requests">Review organization applications <span>↗</span></NavLink>}</div>
        </div>
        <div className="dashboard-hero-side"><span className="hero-side-label">SIGNED IN AS</span><strong>{user?.is_super_admin ? 'Platform administrator' : user?.memberships[0]?.role.replaceAll('_', ' ') ?? 'Member'}</strong><span>{user?.is_super_admin ? 'Platform-wide access' : `${user?.memberships.length ?? 0} organization ${(user?.memberships.length ?? 0) === 1 ? 'workspace' : 'workspaces'}`}</span></div>
      </div>
      <section className="stat-grid dashboard-stat-grid" aria-label="Inspection statistics">
        <StatCard label="In progress" value={loadingSurveys ? '—' : String(metrics.active)} note="Surveys awaiting completion" icon="◉" accent="blue" />
        <StatCard label="Completed this month" value={loadingSurveys ? '—' : String(metrics.completed)} note="Based on your accessible workspaces" icon="✓" accent="green" />
        <StatCard label="Total surveys" value={loadingSurveys ? '—' : String(metrics.total)} note="Visible to your account" icon="▤" accent="purple" />
      </section>

      <section className="dashboard-content-grid">
        <article className="panel dashboard-inspections-panel">
          <div className="panel-heading"><div><span className="section-kicker">WORK QUEUE</span><h2>Recent inspections</h2><p>Latest surveys in the workspaces you can access</p></div>{canViewSurveys && <NavLink to="/inspections" className="text-link">Open inspections <span>→</span></NavLink>}</div>
          {surveyError ? <div className="dashboard-inline-state dashboard-inline-error" role="alert"><strong>Activity unavailable</strong><span>{surveyError}</span></div> : loadingSurveys ? <div className="dashboard-inline-state" role="status"><span className="loading-dot" />Loading inspection activity…</div> : recentSurveys.length === 0 ? <div className="dashboard-empty-state"><span className="dashboard-empty-icon">▤</span><strong>{canViewSurveys ? 'No inspections yet' : 'Inspection access is not enabled'}</strong><span>{canViewSurveys ? 'When a survey is started, its progress will appear here.' : 'Your administrator can grant access to inspection workspaces.'}</span>{canViewSurveys && <NavLink to="/inspections" className="text-link">Start your first inspection <span>→</span></NavLink>}</div> : <div className="dashboard-table-wrap"><table><thead><tr><th>LOCATION</th><th>CHECKLIST</th><th>TYPE</th><th>STATUS</th><th>STARTED</th></tr></thead><tbody>{recentSurveys.map((survey) => <tr key={survey.public_id}><td><strong>{survey.site_name}</strong></td><td>{survey.checklist.name} <small>v{survey.checklist.version_number}</small></td><td>{survey.checklist.survey_type === 'offshore-helideck' ? 'Offshore helideck' : 'Onshore heliport'}</td><td><span className={`dashboard-status ${survey.status}`}>{survey.status === 'in_progress' ? 'In progress' : 'Completed'}</span></td><td>{new Date(survey.started_at).toLocaleDateString()}</td></tr>)}</tbody></table></div>}
        </article>
        <article className="panel dashboard-actions-panel">
          <div className="panel-heading"><div><span className="section-kicker">WORKSPACE TOOLS</span><h2>Quick actions</h2><p>Go straight to a task</p></div></div>
          <div className="dashboard-action-list">
            {canViewSurveys && <NavLink to="/inspections" className="dashboard-action"><span className="action-icon action-blue">＋</span><span><strong>Start an inspection</strong><small>Choose a published checklist and site</small></span><b>→</b></NavLink>}
            {user?.memberships.some((membership) => membership.permissions.includes('inspections.assigned.view')) && <NavLink to="/assignments" className="dashboard-action"><span className="action-icon action-green">✓</span><span><strong>My assignments</strong><small>Continue your assigned fieldwork</small></span><b>→</b></NavLink>}
            {canManageChecklists && <NavLink to="/checklists" className="dashboard-action"><span className="action-icon action-violet">☷</span><span><strong>Checklist library</strong><small>Edit drafts and review versions</small></span><b>→</b></NavLink>}
            {canManageMembers && <NavLink to="/team" className="dashboard-action"><span className="action-icon action-blue">♙</span><span><strong>Review team access</strong><small>Approve signup requests and assign roles</small></span><b>→</b></NavLink>}
            {user?.is_super_admin && <NavLink to="/organization-requests" className="dashboard-action"><span className="action-icon action-amber">▣</span><span><strong>Organization applications</strong><small>Approve new company workspaces</small></span><b>→</b></NavLink>}
            {user?.memberships.some((membership) => membership.permissions.includes('organizations.view')) && <NavLink to="/locations" className="dashboard-action"><span className="action-icon action-amber">⌖</span><span><strong>Locations</strong><small>Manage survey site information</small></span><b>→</b></NavLink>}
            {!canViewSurveys && !canManageChecklists && <div className="dashboard-access-note"><strong>Workspace access</strong><span>Available actions depend on your organization role. Contact your administrator if you need additional access.</span></div>}
          </div>
        </article>
      </section>
      <footer className="page-footer dashboard-footer"><span>ARTSCI Survey Platform</span><span>Showing records available to your account</span></footer>
    </section>
  )
}

function StatCard({ label, value, note, icon, accent }: { label: string; value: string; note: string; icon: string; accent: string }) {
  return <article className="stat-card"><div className={`stat-icon ${accent}`}>{icon}</div><span className="stat-label">{label}</span><strong className="stat-value">{value}</strong><span className="stat-note">{note}</span></article>
}

function EmptySection({ title }: { title: string }) {
  return <section className="section-placeholder"><div className="eyebrow">ARTSCI FIELD OPERATIONS</div><h1>{title}</h1><p>This workspace is ready for the next implementation slice.</p><div className="placeholder-card"><span className="empty-icon">▤</span><strong>No records to show</strong><span>Records created for your organization will appear here.</span></div></section>
}

export default App
