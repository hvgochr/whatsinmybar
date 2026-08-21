import { ApiRequestError } from '../services/api-client'
import type { AuthTokens, LoginPayload, RegisterPayload, User } from '../types/api'

export function useAuth() {
  const api = useApi()
  const accessToken = useState<string | null>('auth.accessToken', () => null)
  const currentUser = useState<User | null>('auth.currentUser', () => null)

  const isAuthenticated = computed(() => Boolean(accessToken.value && currentUser.value))

  const applyTokens = (tokens: AuthTokens): void => {
    api.setTokens(tokens)
  }

  const clearSession = (): void => {
    api.clearTokens()
    currentUser.value = null
  }

  const setCurrentUser = (user: User): void => {
    currentUser.value = user
  }

  const fetchCurrentUser = async (): Promise<User> => {
    try {
      currentUser.value = await api.account.me()

      return currentUser.value
    } catch (error: unknown) {
      if (error instanceof ApiRequestError && error.status === 401) {
        clearSession()
      }

      throw error
    }
  }

  const login = async (payload: LoginPayload): Promise<User> => {
    applyTokens(await api.auth.login(payload))

    return fetchCurrentUser()
  }

  const register = async (payload: RegisterPayload): Promise<User> => {
    return api.auth.register(payload)
  }

  const refreshSession = async (): Promise<AuthTokens | null> => {
    try {
      const tokens = await api.auth.refresh()
      applyTokens(tokens)

      return tokens
    } catch (error: unknown) {
      clearSession()

      if (error instanceof ApiRequestError && error.status === 401) {
        return null
      }

      throw error
    }
  }

  const restoreSession = async (): Promise<User | null> => {
    if (!accessToken.value) {
      await refreshSession()
    }

    if (!accessToken.value) {
      return null
    }

    return fetchCurrentUser()
  }

  const logout = async (): Promise<void> => {
    try {
      await api.auth.logout()
    } finally {
      clearSession()
    }
  }

  return {
    accessToken: readonly(accessToken),
    currentUser: readonly(currentUser),
    isAuthenticated,
    clearSession,
    fetchCurrentUser,
    login,
    logout,
    refreshSession,
    register,
    restoreSession,
    setCurrentUser
  }
}
