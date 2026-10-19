import { useEffect, useState } from 'react'
import { BrowserRouter, Link, NavLink, Outlet, Route, Routes, useLocation } from 'react-router-dom'
import { useAuth } from './auth/AuthContext'
import { apiUrl } from './lib/api'
import { LoginPage } from './auth/LoginPage'
import { ProtectedRoute } from './auth/ProtectedRoute'
import { ChecklistAdministrationPage } from './checklists/ChecklistAdministrationPage'
import { SurveyTakingPage } from './surveys/SurveyTakingPage'
import './App.css'

const pageLoadDate = new Date()

const navigation: { label: string; path: string; icon: string; permission?: string[] }[] = [
  { label: 'Dashboard', path: '/', icon: '▦' },
  { label: 'Inspections', path: '/inspections', icon: '▤', permission: ['inspections.manage', 'inspections.assigned.view', 'reviews.manage'] },
  { label: 'My Assignments', path: '/assignments', icon: '✓', permission: ['inspections.assigned.view'] },
  { label: 'Checklists', path: '/checklists', icon: '☷', permission: ['checklists.manage'] },
  { label: 'Locations', path: '/locations', icon: '⌖', permission: ['organizations.view'] },
  { label: 'Reviews', path: '/reviews', icon: '◷', permission: ['reviews.manage'] },
]

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<LoginPage />} />
        <Route element={<ProtectedRoute />}>
          <Route element={<Shell />}>
            <Route path="/" element={<Dashboard />} />
            <Route path="/checklists" element={<ChecklistAdministrationPage />} />
            {navigation.slice(1).filter((item) => item.path !== '/checklists').map((item) => (
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
  const location = useLocation()
  const visibleNavigation = navigation.filter((item) => !item.permission || user?.is_super_admin || user?.memberships.some((membership) => item.permission?.some((permission) => membership.permissions.includes(permission))))
  const initials = user?.name.split(/\s+/).slice(0, 2).map((name) => name[0]).join('').toUpperCase() ?? 'A'
  const currentRole = user?.is_super_admin ? 'Super Administrator' : user?.memberships[0]?.role.replaceAll('_', ' ') ?? 'Member'

  return (
    <div className="app-shell">
      <aside className={`sidebar ${menuOpen ? 'sidebar-open' : ''}`}>
        <Link className="brand" to="/" aria-label="ARTSCI Survey home">
          <span className="brand-mark">A</span>
          <span className="brand-name">ARTSCI<span>FIELD OPERATIONS</span></span>
        </Link>
        <div className="workspace-label">WORKSPACE</div>
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
          <div className="topbar-right"><ApiStatus /><span className="topbar-divider" /><span className="avatar" aria-hidden="true">{initials}</span><div className="user-meta"><strong>{user?.name}</strong><span>{currentRole}</span></div><button className="sign-out-button" type="button" onClick={() => { setLogoutError(null); void logout().catch(() => setLogoutError('Sign out failed. Check your connection and retry.')) }}>Sign out</button></div>
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
  const firstName = user?.name.trim().split(/\s+/)[0] ?? 'there'
  const today = new Intl.DateTimeFormat(undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(pageLoadDate).toUpperCase()
  const thisMonth = new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(pageLoadDate)
  return (
    <>
      <div className="page-heading">
        <div><div className="eyebrow">{today}</div><h1>Good morning, {firstName}</h1><p>Here’s the latest across your survey operations.</p></div>
        <NavLink className="primary-button" to="/inspections"><span>＋</span> New inspection</NavLink>
      </div>
      <section className="stat-grid" aria-label="Inspection statistics">
        <StatCard label="Active inspections" value="0" note="Across all locations" icon="◉" accent="blue" />
        <StatCard label="My assignments" value="0" note="Ready for fieldwork" icon="⌖" accent="green" />
        <StatCard label="Pending review" value="0" note="Awaiting your review" icon="◷" accent="amber" />
        <StatCard label="Approved this month" value="0" note={thisMonth} icon="✓" accent="purple" />
      </section>

      <section className="content-grid">
        <article className="panel inspections-panel">
          <div className="panel-heading"><div><h2>Recent inspections</h2><p>Track activity across your organization</p></div><NavLink to="/inspections" className="text-link">View all <span>→</span></NavLink></div>
          <div className="table-wrap"><table><thead><tr><th>INSPECTION</th><th>LOCATION</th><th>SURVEY TYPE</th><th>STATUS</th><th>UPDATED</th><th /></tr></thead><tbody><tr><td colSpan={6}><div className="empty-table"><span className="empty-icon">▤</span><strong>No inspections yet</strong><span>New inspections will appear here once they’re created.</span><NavLink to="/inspections" className="text-link">Create your first inspection <span>→</span></NavLink></div></td></tr></tbody></table></div>
        </article>
        <article className="panel activity-panel">
          <div className="panel-heading"><div><h2>Quick access</h2><p>Pick up where you left off</p></div></div>
          <div className="quick-links">
            <NavLink to="/assignments" className="quick-link"><span className="quick-icon blue-icon">✓</span><span><strong>My assignments</strong><small>Open your fieldwork queue</small></span><b>→</b></NavLink>
            <NavLink to="/checklists" className="quick-link"><span className="quick-icon violet-icon">☷</span><span><strong>Checklist library</strong><small>Manage survey definitions</small></span><b>→</b></NavLink>
            <NavLink to="/locations" className="quick-link"><span className="quick-icon green-icon">⌖</span><span><strong>Locations</strong><small>Manage survey locations</small></span><b>→</b></NavLink>
          </div>
          <div className="setup-note"><div className="note-icon">✦</div><div><strong>Start with your survey setup</strong><p>Create a checklist and location to prepare your first inspection.</p><NavLink to="/checklists">Set up checklists <span>→</span></NavLink></div></div>
        </article>
      </section>
      <footer className="page-footer"><span>ARTSCI Survey Platform</span><span>Sample workspace · No approved aviation checklist data loaded</span></footer>
    </>
  )
}

function StatCard({ label, value, note, icon, accent }: { label: string; value: string; note: string; icon: string; accent: string }) {
  return <article className="stat-card"><div className={`stat-icon ${accent}`}>{icon}</div><span className="stat-label">{label}</span><strong className="stat-value">{value}</strong><span className="stat-note">{note}</span></article>
}

function EmptySection({ title }: { title: string }) {
  return <section className="section-placeholder"><div className="eyebrow">ARTSCI FIELD OPERATIONS</div><h1>{title}</h1><p>This workspace is ready for the next implementation slice.</p><div className="placeholder-card"><span className="empty-icon">▤</span><strong>No records to show</strong><span>Records created for your organization will appear here.</span></div></section>
}

export default App
