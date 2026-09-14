type RawResponse<T> = { headers: Headers, _data?: T }
type RawFetch = <T>(path: string, options: Record<string, unknown>) => Promise<RawResponse<T>>

export function createServerApiFetch(raw: RawFetch, incomingCookie: string | undefined, appendCookie: (cookie: string) => void, clientIp?: string) {
  let refreshCookie = /(?:^|;\s*)(refresh_token=[^;]+)/.exec(incomingCookie ?? '')?.[1]

  return async <T>(path: string, options: Record<string, unknown> = {}): Promise<T> => {
    const headers = new Headers(options.headers as HeadersInit | undefined)
    headers.delete('Forwarded')
    headers.delete('X-Real-IP')
    headers.delete('X-Forwarded-For')
    if (clientIp) headers.set('X-Forwarded-For', clientIp)
    // Only the documented cookie-authenticated endpoints receive this credential.
    if (refreshCookie && ['/auth/login', '/auth/refresh', '/auth/logout'].includes(path)) headers.set('cookie', refreshCookie)
    const forward = (responseHeaders: Headers) => {
      for (const cookie of responseHeaders.getSetCookie()) {
        if (cookie.startsWith('refresh_token=')) {
          refreshCookie = cookie.split(';')[0]
          appendCookie(cookie)
        }
      }
    }
    try {
      const response = await raw<T>(path, { ...options, headers })
      forward(response.headers)
      return response._data as T
    } catch (error) {
      const response = (error as { response?: { headers?: Headers } }).response
      if (response?.headers) forward(response.headers)
      throw error
    }
  }
}
