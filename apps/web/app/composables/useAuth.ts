import { ApiRequestError } from '../services/api-client'
import { announceSessionChange } from '../services/session-events'
import type { AuthTokens, LoginPayload, RegisterPayload, User } from '../types/api'

export function useAuth() {
  const api = useApi()
  const session = useSessionState()
  const currentUser = computed(() => session.status.value === 'authenticated' ? session.user.value : null)
  const isAuthenticated = computed(() => Boolean(session.accessToken.value && currentUser.value))

  const fetchCurrentUser = async (): Promise<User> => {
    try {
      const user = await api.account.me()
      session.setUser(user)
      return user
    } catch (error) {
      if (error instanceof ApiRequestError && error.status === 401) api.clearTokens()
      else if (!(error instanceof ApiRequestError) || error.code !== 'session_changed') session.degrade()
      throw error
    }
  }
  const login = async (payload: LoginPayload): Promise<User> => {
    await api.auth.login(payload)
    const user = await fetchCurrentUser()
    announceSessionChange('changed')
    return user
  }
  const refreshSession = async (): Promise<AuthTokens | null> => {
    try {
      return await api.auth.refresh()
    } catch (error) {
      if (error instanceof ApiRequestError && error.status === 401) return null
      throw error
    }
  }
  const restoreSession = async (): Promise<User | null> => {
    if (isAuthenticated.value) return currentUser.value
    if (!session.accessToken.value || session.status.value === 'degraded') await refreshSession()
    if (!session.accessToken.value) return null
    if (isAuthenticated.value) return currentUser.value
    return fetchCurrentUser()
  }
  const logout = async (): Promise<void> => {
    // A failed revocation is reported to the user, who can retry it.
    await api.auth.logout()
    announceSessionChange('logout')
  }

  return {
    accessToken: readonly(session.accessToken),
    currentUser,
    status: readonly(session.status),
    isAuthenticated,
    clearSession: api.clearTokens,
    fetchCurrentUser,
    login,
    logout,
    refreshSession,
    register: (payload: RegisterPayload) => api.auth.register(payload),
    restoreSession,
    setCurrentUser: session.setUser
  }
}
