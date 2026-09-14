import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { clearNuxtState, useNuxtApp } from '#app'
import { useSessionState } from '../../../app/composables/useSessionState'
import { useAuth } from '../../../app/composables/useAuth'
import { createApiClient, type ApiClient } from '../../../app/services/api-client'
import type { User } from '../../../app/types/api'

const mocks = vi.hoisted(() => ({ clear: vi.fn(), api: { clearTokens: vi.fn(), auth: { refresh: async () => ({ token: '' }) } } as unknown as ApiClient }))
mockNuxtImport('clearNuxtData', () => mocks.clear)
mockNuxtImport('useApi', () => () => mocks.api!)

const user = { id: 1, username: 'viewer', birthDate: '1990-01-01', roles: ['ROLE_USER'] } as User

describe('coordinated session state', () => {
  beforeEach(() => {
    clearNuxtState()
    mocks.clear.mockClear()
  })

  function setup(fetch: Parameters<typeof createApiClient>[0]['fetch']) {
    return useNuxtApp().runWithContext(() => {
      const state = useSessionState()
      mocks.api = createApiClient({
        baseURL: '/api', fetch,
        getAccessToken: () => state.status.value === 'degraded' ? null : state.accessToken.value,
        setAccessToken: state.setAccessToken, setCurrentUser: state.setUser, clearTokens: state.clear,
        getSessionRevision: () => state.revision.value, onRefreshUnavailable: state.degrade
      })
      state.setAccessToken('token')
      state.setUser(user)
      return { state, auth: useAuth(), api: mocks.api }
    })
  }

  it('clears the user and personalized cache on automatic session rejection', async () => {
    const { auth, api, state } = setup(async () => { throw { status: 401 } })
    mocks.clear.mockClear()
    await expect(api.auth.refresh()).rejects.toMatchObject({ status: 401 })
    expect(state.accessToken.value).toBeNull()
    expect(state.user.value).toBeNull()
    expect(auth.isAuthenticated.value).toBe(false)
    expect(state.status.value).toBe('anonymous')
    expect(mocks.clear).toHaveBeenCalled()
  })

  it('keeps recovery credentials but hides uncertain personalized UI on temporary failure', async () => {
    const { auth, state } = setup(async () => { throw { status: 503 } })
    await expect(auth.refreshSession()).rejects.toMatchObject({ status: 503 })
    expect(state.accessToken.value).toBe('token')
    expect(state.user.value).toEqual(user)
    expect(auth.currentUser.value).toBeNull()
    expect(auth.status.value).toBe('degraded')
    expect(mocks.clear).toHaveBeenCalled()
  })

  it('invalidates cached data when the viewer changes even if both are authenticated', () => {
    const { state } = setup(vi.fn())
    mocks.clear.mockClear()
    state.setUser({ ...user, id: 2, username: 'another_viewer' })
    expect(mocks.clear).toHaveBeenCalledOnce()
  })

  it('rebinds the viewer when another tab changed the account behind the cookie', async () => {
    const otherUser = { ...user, id: 2, username: 'other_account' }
    const { api, auth } = setup(async (path: string) => path === '/auth/refresh' ? { token: 'other-token' } : otherUser)
    await api.auth.refresh()
    expect(auth.currentUser.value).toEqual(otherUser)
    expect(auth.isAuthenticated.value).toBe(true)
  })

  it('preserves page/editor state on ordinary renewal for the same viewer', async () => {
    const { api, state } = setup(async (path: string) => path === '/auth/refresh' ? { token: 'renewed' } : user)
    const revision = state.revision.value
    mocks.clear.mockClear()
    await api.auth.refresh()
    expect(state.status.value).toBe('authenticated')
    expect(state.revision.value).toBe(revision)
    expect(mocks.clear).not.toHaveBeenCalled()
  })

  it.each(['POST', 'PUT', 'PATCH', 'DELETE'] as const)('does not replay a %s prepared by A after refresh identifies B', async (method) => {
    const otherUser = { ...user, id: 2, username: 'other_account' }
    let writes = 0
    const { api, auth } = setup(async (path: string) => {
      if (path === '/auth/refresh') return { token: 'other-token' }
      if (path === '/me') return otherUser
      if (++writes === 1) throw { status: 401 }
      return { saved: true }
    })
    await expect(api.request('/action', { method, body: { message: 'Prepared by A' } })).rejects.toMatchObject({ code: 'session_changed' })
    expect(writes).toBe(1)
    expect(auth.currentUser.value).toEqual(otherUser)
  })

  it('waits for pending viewer verification before handling a late 401', async () => {
    let rejectWrite!: (reason: unknown) => void
    let finishProfile!: (value: User) => void
    let profileStarted!: () => void
    const verifying = new Promise<void>((resolve) => { profileStarted = resolve })
    let writes = 0
    const { api, auth } = setup(async (path: string) => {
      if (path === '/auth/refresh') return { token: 'other-token' }
      if (path === '/me') {
        profileStarted()
        return new Promise<User>((resolve) => { finishProfile = resolve })
      }
      if (++writes === 1) return new Promise((_resolve, reject) => { rejectWrite = reject })
      return { saved: true }
    })
    const write = api.comments.create('recipe', { message: 'Prepared by A' })
    const assertion = expect(write).rejects.toMatchObject({ code: 'session_changed' })
    const refresh = api.auth.refresh()
    await verifying
    rejectWrite({ status: 401 })
    await Promise.resolve()
    await Promise.resolve()
    expect(writes).toBe(1)
    const otherUser = { ...user, id: 2, username: 'other_account' }
    finishProfile(otherUser)
    await refresh
    await assertion
    expect(writes).toBe(1)
    expect(auth.currentUser.value).toEqual(otherUser)
  })

  it('still retries a write after renewal for the same viewer', async () => {
    let writes = 0
    const { api } = setup(async (path: string) => {
      if (path === '/auth/refresh') return { token: 'renewed' }
      if (path === '/me') return user
      if (++writes === 1) throw { status: 401 }
      return { saved: true }
    })
    await expect(api.request('/action', { method: 'POST' })).resolves.toEqual({ saved: true })
    expect(writes).toBe(2)
  })

  it('requires login immediately after confirmed password change', async () => {
    const { api, auth, state } = setup(async () => ({ changed: true }))
    await api.account.changePassword({ currentPassword: 'current-password', newPassword: 'new-secure-password' })
    expect(auth.currentUser.value).toBeNull()
    expect(state.status.value).toBe('anonymous')
  })
})
