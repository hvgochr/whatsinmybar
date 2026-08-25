import { appendResponseHeader } from 'h3'
import { createApiClient } from '../services/api-client'

export default defineNuxtPlugin({
  name: 'api',
  setup() {
    const runtimeConfig = useRuntimeConfig()
    const accessToken = useState<string | null>('auth.accessToken', () => null)

    const api = createApiClient({
      baseURL: import.meta.server ? runtimeConfig.apiBaseUrl : runtimeConfig.public.apiBaseUrl,
      fetch: import.meta.server ? serverApiFetch() : $fetch,
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
  }
})

function serverApiFetch() {
  const event = useRequestEvent()
  const requestCookie = useRequestHeaders(['cookie']).cookie

  return async <T>(request: string, options: Record<string, unknown> = {}): Promise<T> => {
    const headers = new Headers(options.headers as HeadersInit | undefined)

    if (requestCookie && !headers.has('cookie')) {
      headers.set('cookie', requestCookie)
    }

    try {
      const response = await $fetch.raw<T>(request, { ...options, headers })
      forwardResponseCookies(response.headers)

      return response._data as T
    } catch (error: unknown) {
      const response = (error as { response?: { headers?: Headers } }).response

      if (response?.headers) {
        forwardResponseCookies(response.headers)
      }

      throw error
    }
  }

  function forwardResponseCookies(headers: Headers): void {
    if (!event) {
      return
    }

    for (const cookie of headers.getSetCookie()) {
      appendResponseHeader(event, 'set-cookie', cookie)
    }
  }
}
