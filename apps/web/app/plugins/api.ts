import { createApiClient } from '../services/api-client'

export default defineNuxtPlugin(() => {
  const runtimeConfig = useRuntimeConfig()
  const accessToken = useState<string | null>('auth.accessToken', () => null)
  const refreshToken = useRefreshTokenCookie()

  const api = createApiClient({
    baseURL: import.meta.server ? runtimeConfig.apiBaseUrl : runtimeConfig.public.apiBaseUrl,
    fetch: $fetch,
    getAccessToken: () => accessToken.value,
    setAccessToken: (token) => {
      accessToken.value = token
    },
    getRefreshToken: () => refreshToken.value,
    setRefreshToken: (token) => {
      refreshToken.value = token
    },
    clearTokens: () => {
      accessToken.value = null
      refreshToken.value = null
    }
  })

  return {
    provide: {
      api
    }
  }
})
