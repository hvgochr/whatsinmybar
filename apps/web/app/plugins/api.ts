import { appendResponseHeader, setResponseHeader } from 'h3'
import { createApiClient } from '../services/api-client'
import { createServerApiFetch } from '../services/server-api-fetch'
import { forwardedClientIp } from '../services/client-ip'

export default defineNuxtPlugin({
  name: 'api',
  setup() {
    const runtimeConfig = useRuntimeConfig()
    const session = useSessionState()
    const event = useRequestEvent()
    if (import.meta.server && event) {
      // HTML and Nuxt payloads can contain the viewer and a short-lived JWT.
      setResponseHeader(event, 'Cache-Control', 'private, no-store')
      appendResponseHeader(event, 'Vary', 'Cookie')
    }
    const api = createApiClient({
      baseURL: import.meta.server ? runtimeConfig.apiBaseUrl : runtimeConfig.public.apiBaseUrl,
      fetch: import.meta.server
        ? createServerApiFetch($fetch.raw, useRequestHeaders(['cookie']).cookie, (cookie) => {
            if (event) appendResponseHeader(event, 'set-cookie', cookie)
          }, forwardedClientIp(event?.node.req.socket.remoteAddress, useRequestHeaders(['x-forwarded-for'])['x-forwarded-for'], runtimeConfig.trustedProxyIp))
        : $fetch,
      getAccessToken: () => session.status.value === 'degraded' ? null : session.accessToken.value,
      setAccessToken: session.setAccessToken,
      setCurrentUser: session.setUser,
      clearTokens: session.clear,
      getSessionRevision: () => session.revision.value,
      onRefreshUnavailable: session.degrade
    })

    return { provide: { api } }
  }
})
