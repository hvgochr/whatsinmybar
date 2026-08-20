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

  it('exposes public taxonomy and profile endpoints without bearer tokens', async () => {
    const fetch = vi.fn(async (path: string, options?: Record<string, unknown>) => {
      expect((options?.headers as Headers).get('Authorization')).toBeNull()

      if (path === '/categories') {
        expect(options?.query).toEqual({ pagination: false })
        return { member: [] }
      }

      if (path === '/ingredients') {
        expect(options?.query).toEqual({ pagination: false })
        return { member: [] }
      }

      if (path === '/users/jane_doe') {
        return {
          avatarPath: null,
          bio: null,
          createdAt: '2026-07-25T10:00:00+00:00',
          id: 1,
          username: 'jane_doe'
        }
      }

      throw new Error(`Unexpected request: ${path}`)
    })
    const api = createTestClient(fetch, {
      accessToken: 'access-token',
      refreshToken: 'refresh-token'
    })

    await api.categories.list()
    await api.ingredients.list()
    await api.profiles.get('jane_doe')

    expect(fetch).toHaveBeenCalledTimes(3)
  })

  it('maps alcohol recipe filters to boolean API query values', async () => {
    const fetch = vi.fn(async () => ({ member: [] }))
    const api = createTestClient(fetch, {
      accessToken: null,
      refreshToken: null
    })

    await api.recipes.list({ alcohol: 'with', page: 2 })
    await api.recipes.list({ alcohol: 'without' })

    expect(fetch).toHaveBeenNthCalledWith(1, '/recipes', expect.objectContaining({
      query: {
        alcohol: true,
        page: 2
      }
    }))
    expect(fetch).toHaveBeenNthCalledWith(2, '/recipes', expect.objectContaining({
      query: {
        alcohol: false
      }
    }))
  })

  it('passes pagination parameters to every admin collection', async () => {
    const fetch = vi.fn(async () => ({
      items: [],
      page: 2,
      pageSize: 10,
      totalItems: 0,
      totalPages: 0
    }))
    const api = createTestClient(fetch, {
      accessToken: 'access-token',
      refreshToken: 'refresh-token'
    })

    await api.admin.users.list({ page: 2, pageSize: 10 })
    await api.admin.recipes.list({ page: 2, pageSize: 10 })
    await api.admin.categories.list({ page: 2, pageSize: 10 })
    await api.admin.ingredients.list({ page: 2, pageSize: 10 })
    await api.admin.reports.list({ page: 2, pageSize: 10 })

    for (const [path, options] of fetch.mock.calls) {
      expect(path).toMatch(/^\/admin\/(users|recipes|categories|ingredients|reports)$/)
      expect(options).toEqual(expect.objectContaining({
        query: { page: 2, pageSize: 10 }
      }))
    }
  })

  it('manages recipe workflow subresources', async () => {
    const fetch = vi.fn(async (path: string, options?: Record<string, unknown>) => {
      if (path === '/recipe_steps') {
        expect(options).toEqual(expect.objectContaining({
          body: {
            instruction: 'Stir with ice.',
            position: 1,
            recipe: '/api/recipes/negroni'
          },
          method: 'POST'
        }))

        return { id: 10, instruction: 'Stir with ice.', position: 1 }
      }

      if (path === '/recipe_ingredients/12') {
        expect(options).toEqual(expect.objectContaining({ method: 'DELETE' }))

        return undefined
      }

      if (path === '/recipes/negroni/image') {
        expect(options).toEqual(expect.objectContaining({ method: 'DELETE' }))

        return { imagePath: null, recipeSlug: 'negroni' }
      }

      throw new Error(`Unexpected request: ${path}`)
    })
    const api = createTestClient(fetch, {
      accessToken: 'access-token',
      refreshToken: 'refresh-token'
    })

    await expect(api.recipeSteps.create({
      instruction: 'Stir with ice.',
      position: 1,
      recipe: '/api/recipes/negroni'
    })).resolves.toEqual({ id: 10, instruction: 'Stir with ice.', position: 1 })
    await expect(api.recipeIngredients.delete(12)).resolves.toBeUndefined()
    await expect(api.recipes.removeImage('negroni')).resolves.toEqual({ imagePath: null, recipeSlug: 'negroni' })
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
