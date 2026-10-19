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
  async logout(): Promise<void> {
    await request('/api/logout', { method: 'POST' })
  },
}
