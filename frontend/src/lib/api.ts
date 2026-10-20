export type AuthUser = {
  id: number
  name: string
  email: string
  is_super_admin: boolean
  platform_roles: string[]
  memberships: {
    organization: { public_id: string; name: string; slug: string }
    role: string
    permissions: string[]
  }[]
}

export type ChecklistFieldType = 'text' | 'textarea' | 'integer' | 'decimal' | 'date' | 'time' | 'boolean' | 'single_select' | 'multi_select' | 'informational'

export type ChecklistOption = { value: string; label: string }
export type SourceProvenance = { document_identifier: string | null; wording: string | null; locator: Record<string, string> | null; review_status: 'unreviewed' | 'review_required' | 'reviewed' }
export type ChecklistTableColumn = { public_id?: string; stable_key: string; label: string; source_label: string | null; data_type: 'text' | 'integer' | 'decimal' | 'date' | 'time' | 'boolean'; unit: string | null; source_provenance: SourceProvenance }
export type ChecklistTableBlock = { public_id?: string; stable_key: string; title: string; description: string | null; columns: ChecklistTableColumn[] }
export type ChecklistQuestion = {
  stable_key: string
  label: string
  description: string | null
  instructions: string | null
  field_type: ChecklistFieldType
  is_required: boolean
  unit: string | null
  options: ChecklistOption[]
  source_provenance: SourceProvenance
}
export type ChecklistSection = { title: string; description: string | null; source_provenance: SourceProvenance; questions: ChecklistQuestion[]; table_blocks: ChecklistTableBlock[] }
export type ChecklistVersion = {
  version_number: number
  status: 'draft' | 'published'
  published_at: string | null
  sections: ChecklistSection[]
}
export type ChecklistDefinition = {
  public_id: string
  name: string
  description: string | null
  survey_type: { slug: string; name: string }
  versions: ChecklistVersion[]
}
export type OrganizationSummary = { public_id: string; name: string; slug: string }
export type RegistrationOrganization = { public_id: string; name: string }
export type OrganizationMember = { id: number; name: string; email: string; role: string; status: 'pending' | 'active' }
export type OrganizationRequest = { public_id: string; name: string; requested_at: string | null; contact: { name: string; email: string } | null }
export type ChecklistLibrary = {
  survey_types: { slug: string; name: string }[]
  checklists: ChecklistDefinition[]
}
export type SurveyTemplate = {
  public_id: string
  name: string
  survey_type: { slug: string; name: string }
  versions: { version_number: number; published_at: string | null }[]
}
export type SurveyQuestion = ChecklistQuestion & { public_id: string }
export type SurveySection = { public_id: string; title: string; description: string | null; source_provenance: SourceProvenance; questions: SurveyQuestion[]; table_blocks: (ChecklistTableBlock & { public_id: string; columns: (ChecklistTableColumn & { public_id: string })[] })[] }
export type SurveyAnswer = { value: unknown; comment: string | null }
export type SurveyTableRow = { public_id: string; cells: Record<string, SurveyAnswer> }
export type SurveySummary = {
  public_id: string
  site_name: string
  status: 'in_progress' | 'completed'
  started_at: string
  completed_at: string | null
  checklist: { public_id: string; name: string; survey_type: string; version_number: number }
  assignee: { id: number; name: string } | null
}
export type SurveyInstance = SurveySummary & { sections: SurveySection[]; answers: Record<string, SurveyAnswer>; table_rows: Record<string, SurveyTableRow[]> }

const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL ?? '').replace(/\/+$/, '')

export function apiUrl(path: string): string {
  return `${apiBaseUrl}${path}`
}

type ApiEnvelope<T> = { data: T }

export class ApiError extends Error {
  public readonly status: number
  public readonly errors: Record<string, string[]>

  constructor(
    message: string,
    status: number,
    errors: Record<string, string[]> = {},
  ) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }
}

function xsrfToken(): string | undefined {
  const token = document.cookie.split('; ').find((cookie) => cookie.startsWith('XSRF-TOKEN='))?.split('=').slice(1).join('=')
  return token ? decodeURIComponent(token) : undefined
}

async function getCsrfCookie(): Promise<void> {
  const response = await fetch(apiUrl('/sanctum/csrf-cookie'), {
    credentials: 'include',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  })

  if (!response.ok) {
    if (response.status === 401) {
      window.dispatchEvent(new Event('artsci:unauthorized'))
    }
    throw new ApiError('Could not start a secure session. Try again.', response.status)
  }
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const method = (init.method ?? 'GET').toUpperCase()
  if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
    await getCsrfCookie()
  }

  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')
  headers.set('X-Requested-With', 'XMLHttpRequest')
  if (init.body) {
    headers.set('Content-Type', 'application/json')
  }
  const token = xsrfToken()
  if (token && !['GET', 'HEAD', 'OPTIONS'].includes(method)) {
    headers.set('X-XSRF-TOKEN', token)
  }

  const response = await fetch(apiUrl(path), { ...init, method, headers, credentials: 'include' })
  const payload = await response.json().catch(() => ({})) as Record<string, unknown>
  if (!response.ok) {
    const errors = typeof payload.errors === 'object' && payload.errors !== null
      ? payload.errors as Record<string, string[]>
      : {}
    const message = typeof payload.message === 'string' ? payload.message : 'The request could not be completed.'
    throw new ApiError(message, response.status, errors)
  }

  return payload as T
}

export const api = {
  async me(): Promise<AuthUser> {
    return (await request<ApiEnvelope<AuthUser>>('/api/me')).data
  },
  async login(email: string, password: string): Promise<AuthUser> {
    return (await request<ApiEnvelope<AuthUser>>('/api/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    })).data
  },
  async registrationOrganizations(): Promise<RegistrationOrganization[]> {
    return (await request<ApiEnvelope<RegistrationOrganization[]>>('/api/registration/organizations')).data
  },
  async register(payload: { account_type: 'organization' | 'field_staff'; organization_public_id?: string; organization_name?: string; name: string; email: string; password: string; password_confirmation: string }): Promise<{ message: string }> {
    return request<{ message: string }>('/api/register', { method: 'POST', body: JSON.stringify(payload) })
  },
  async logout(): Promise<void> {
    await request('/api/logout', { method: 'POST' })
  },
  async organizations(): Promise<OrganizationSummary[]> {
    return (await request<ApiEnvelope<OrganizationSummary[]>>('/api/organizations')).data
  },
  async organizationRequests(): Promise<OrganizationRequest[]> {
    return (await request<ApiEnvelope<OrganizationRequest[]>>('/api/platform/organization-requests')).data
  },
  async approveOrganizationRequest(publicId: string): Promise<{ public_id: string; name: string; status: string }> {
    return (await request<ApiEnvelope<{ public_id: string; name: string; status: string }>>(`/api/platform/organization-requests/${encodeURIComponent(publicId)}/approve`, {
      method: 'POST',
      body: JSON.stringify({}),
    })).data
  },
  async organizationMembers(organizationPublicId: string): Promise<OrganizationMember[]> {
    return (await request<ApiEnvelope<OrganizationMember[]>>(`/api/organizations/${encodeURIComponent(organizationPublicId)}/members`)).data
  },
  async approveOrganizationMember(organizationPublicId: string, userId: number, role: string): Promise<OrganizationMember> {
    return (await request<ApiEnvelope<OrganizationMember>>(`/api/organizations/${encodeURIComponent(organizationPublicId)}/members/${userId}/approve`, {
      method: 'POST',
      body: JSON.stringify({ role }),
    })).data
  },
  async checklists(organizationPublicId: string): Promise<ChecklistLibrary> {
    return (await request<ApiEnvelope<ChecklistLibrary>>(`/api/organizations/${encodeURIComponent(organizationPublicId)}/checklists`)).data
  },
  async createChecklist(organizationPublicId: string, payload: { name: string; description: string; survey_type: string }): Promise<ChecklistDefinition> {
    return (await request<ApiEnvelope<ChecklistDefinition>>(`/api/organizations/${encodeURIComponent(organizationPublicId)}/checklists`, {
      method: 'POST',
      body: JSON.stringify(payload),
    })).data
  },
  async updateChecklist(organizationPublicId: string, checklistPublicId: string, payload: { name: string; description: string }): Promise<ChecklistDefinition> {
    return (await request<ApiEnvelope<ChecklistDefinition>>(`/api/organizations/${encodeURIComponent(organizationPublicId)}/checklists/${encodeURIComponent(checklistPublicId)}`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    })).data
  },
  async createChecklistVersion(organizationPublicId: string, checklistPublicId: string): Promise<ChecklistDefinition> {
    return (await request<ApiEnvelope<ChecklistDefinition>>(`/api/organizations/${encodeURIComponent(organizationPublicId)}/checklists/${encodeURIComponent(checklistPublicId)}/versions`, {
      method: 'POST',
      body: JSON.stringify({}),
    })).data
  },
  async saveChecklistVersion(organizationPublicId: string, checklistPublicId: string, versionNumber: number, sections: ChecklistSection[]): Promise<ChecklistDefinition> {
    return (await request<ApiEnvelope<ChecklistDefinition>>(`/api/organizations/${encodeURIComponent(organizationPublicId)}/checklists/${encodeURIComponent(checklistPublicId)}/versions/${versionNumber}`, {
      method: 'PUT',
      body: JSON.stringify({ sections }),
    })).data
  },
  async publishChecklistVersion(organizationPublicId: string, checklistPublicId: string, versionNumber: number): Promise<ChecklistDefinition> {
    return (await request<ApiEnvelope<ChecklistDefinition>>(`/api/organizations/${encodeURIComponent(organizationPublicId)}/checklists/${encodeURIComponent(checklistPublicId)}/versions/${versionNumber}/publish`, {
      method: 'POST',
      body: JSON.stringify({}),
    })).data
  },
  async surveyTemplates(organizationPublicId: string): Promise<SurveyTemplate[]> {
    return (await request<ApiEnvelope<SurveyTemplate[]>>(`/api/organizations/${encodeURIComponent(organizationPublicId)}/survey-templates`)).data
  },
  async surveys(organizationPublicId: string): Promise<SurveySummary[]> {
    return (await request<ApiEnvelope<SurveySummary[]>>(`/api/organizations/${encodeURIComponent(organizationPublicId)}/surveys`)).data
  },
  async createSurvey(organizationPublicId: string, payload: { checklist_public_id: string; version_number: number; site_name: string }): Promise<SurveyInstance> {
    return (await request<ApiEnvelope<SurveyInstance>>(`/api/organizations/${encodeURIComponent(organizationPublicId)}/surveys`, {
      method: 'POST',
      body: JSON.stringify(payload),
    })).data
  },
  async survey(organizationPublicId: string, surveyPublicId: string): Promise<SurveyInstance> {
    return (await request<ApiEnvelope<SurveyInstance>>(`/api/organizations/${encodeURIComponent(organizationPublicId)}/surveys/${encodeURIComponent(surveyPublicId)}`)).data
  },
  async saveSurveyAnswers(organizationPublicId: string, surveyPublicId: string, answers: { question_public_id: string; value: unknown; comment: string | null }[], status: 'in_progress' | 'completed', table_rows: { table_public_id: string; rows: { cells: { column_public_id: string; value: unknown; comment: string | null }[] }[] }[]): Promise<SurveyInstance> {
    return (await request<ApiEnvelope<SurveyInstance>>(`/api/organizations/${encodeURIComponent(organizationPublicId)}/surveys/${encodeURIComponent(surveyPublicId)}/answers`, {
      method: 'PUT',
      body: JSON.stringify({ answers, status, table_rows }),
    })).data
  },
}
