import { createApiClient } from '../services/api-client'

export default defineNuxtPlugin(() => {
  const runtimeConfig = useRuntimeConfig()
  const accessToken = useState<string | null>('auth.accessToken', () => null)

  const api = createApiClient({
    baseURL: import.meta.server ? runtimeConfig.apiBaseUrl : runtimeConfig.public.apiBaseUrl,
    fetch: $fetch,
    getAccessToken: () => accessToken.value,
    setAccessToken: (token) => {
      accessToken.value = token
    },
    clearTokens: () => {
      accessToken.value = null
    }
  })

  return {
    provide: {
      api
    }
  }
})
