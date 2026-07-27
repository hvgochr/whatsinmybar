import { describe, expect, it, vi } from 'vitest'
import { ApiRequestError, createApiClient, normalizeApiError } from '../../../app/services/api-client'
import type { AuthTokens, User } from '../../../app/types/api'

const user: User = {
  id: 1,
  email: 'jane@example.com',
  username: 'jane_doe',
  birthDate: '1990-01-01',
  bio: null,
  avatarPath: null,
  roles: ['ROLE_USER'],
  createdAt: '2026-07-25T10:00:00+00:00',
  updatedAt: '2026-07-25T10:00:00+00:00'
}

describe('api client', () => {
  it('adds the bearer token to authenticated requests', async () => {
    const fetch = vi.fn(async (_path: string, options?: Record<string, unknown>) => {
      expect((options?.headers as Headers).get('Authorization')).toBe('Bearer access-token')

      return user
    })
    const api = createTestClient(fetch, {
      accessToken: 'access-token',
      refreshToken: 'refresh-token'
    })

    await expect(api.account.me()).resolves.toEqual(user)
    expect(fetch).toHaveBeenCalledWith('/me', expect.objectContaining({ baseURL: '/api' }))
  })

  it('refreshes tokens and retries once after an authenticated 401', async () => {
    const tokens: AuthTokens = {
      token: 'new-access-token',
      refresh_token: 'new-refresh-token'
    }
    const state = {
      accessToken: 'expired-token',
      refreshToken: 'refresh-token'
    }
    const fetch = vi.fn(async (path: string, options?: Record<string, unknown>) => {
      const authorization = (options?.headers as Headers).get('Authorization')

      if (path === '/me' && authorization === 'Bearer expired-token') {
        throw unauthorizedError()
      }

      if (path === '/auth/refresh') {
        expect(authorization).toBeNull()
        expect(options?.body).toEqual({ refresh_token: 'refresh-token' })

        return tokens
      }

      expect(path).toBe('/me')
      expect(authorization).toBe('Bearer new-access-token')

      return user
    })
    const api = createTestClient(fetch, state)

    await expect(api.account.me()).resolves.toEqual(user)
    expect(state).toEqual(tokensToState(tokens))
    expect(fetch).toHaveBeenCalledTimes(3)
  })

  it('clears tokens when refresh fails', async () => {
    const state = {
      accessToken: 'expired-token',
      refreshToken: 'refresh-token'
    }
    const fetch = vi.fn(async () => {
      throw unauthorizedError()
    })
    const api = createTestClient(fetch, state)

    await expect(api.account.me()).rejects.toMatchObject({
      code: 'unauthorized',
      status: 401
    })
    expect(state).toEqual({ accessToken: null, refreshToken: null })
  })

  it('normalizes validation errors', () => {
    const error = normalizeApiError({
      data: {
        error: {
          status: 422,
          code: 'validation_failed',
          message: 'Validation failed.',
          violations: [{ property: '[email]', message: 'Invalid email.' }]
        }
      }
    })

    expect(error).toBeInstanceOf(ApiRequestError)
    expect(error.status).toBe(422)
    expect(error.code).toBe('validation_failed')
    expect(error.violations).toEqual([{ property: '[email]', message: 'Invalid email.' }])
  })
})

function createTestClient(
  fetch: <T>(request: string, options?: Record<string, unknown>) => Promise<T>,
  state: { accessToken: string | null, refreshToken: string | null }
) {
  return createApiClient({
    baseURL: '/api',
    fetch,
    getAccessToken: () => state.accessToken,
    setAccessToken: (token) => {
      state.accessToken = token
    },
    getRefreshToken: () => state.refreshToken,
    setRefreshToken: (token) => {
      state.refreshToken = token
    },
    clearTokens: () => {
      state.accessToken = null
      state.refreshToken = null
    }
  })
}

function tokensToState(tokens: AuthTokens): { accessToken: string, refreshToken: string } {
  return {
    accessToken: tokens.token,
    refreshToken: tokens.refresh_token
  }
}

function unauthorizedError() {
  return {
    data: {
      error: {
        status: 401,
        code: 'unauthorized',
        message: 'Expired token.'
      }
    },
    status: 401
  }
}
