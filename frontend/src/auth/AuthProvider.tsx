import { useEffect, useMemo, useState, type ReactNode } from 'react'
import { ApiError, api, type AuthUser } from '../lib/api'
import { AuthContext, type AuthContextValue } from './AuthContext'

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let active = true
    api.me()
      .then((currentUser) => { if (active) setUser(currentUser) })
      .catch((cause: unknown) => {
        if (active && !(cause instanceof ApiError && cause.status === 401)) {
          setError('Authentication service is unavailable. Check your connection and try again.')
        }
      })
      .finally(() => { if (active) setLoading(false) })

    const onUnauthorized = () => setUser(null)
    window.addEventListener('artsci:unauthorized', onUnauthorized)
    return () => {
      active = false
      window.removeEventListener('artsci:unauthorized', onUnauthorized)
    }
  }, [])

  const value = useMemo<AuthContextValue>(() => ({
    user,
    loading,
    error,
    async login(email, password) {
      setError(null)
      setUser(await api.login(email, password))
    },
    async logout() {
      await api.logout()
      setUser(null)
    },
  }), [user, loading, error])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
