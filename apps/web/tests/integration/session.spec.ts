import { expect, test, type APIRequestContext } from '@playwright/test'
import { createApiClient } from '../../app/services/api-client'

interface Account {
  email: string
  username: string
  password: string
  birthDate: string
}

const suffix = crypto.randomUUID().replaceAll('-', '').slice(0, 12)
const primaryAccount: Account = {
  email: `session-${suffix}@example.com`,
  username: `session_${suffix}`,
  password: 'very-secure-password',
  birthDate: '1990-01-01'
}
const seededSecondAccount: Account = {
  email: 'max@example.com',
  username: 'max_mixer',
  password: 'very-secure-password',
  birthDate: '1990-01-01'
}
let primaryRegistered = false

async function login(request: APIRequestContext, account = primaryAccount) {
  if (account === primaryAccount && !primaryRegistered) {
    expect((await request.post('/api/auth/register', { data: account })).status()).toBe(201)
    primaryRegistered = true
  }
  const response = await request.post('/api/auth/login', { data: account })
  expect(response.status()).toBe(200)
  return { account, cookie: response.headers()['set-cookie']!.split(';')[0]!, token: (await response.json()).token as string }
}

const csrf = { 'X-CSRF-Protection': '1' }

test('concurrent HTTP refreshes converge; replay is bounded and revocation includes predecessors', async ({ request }) => {
  const { cookie } = await login(request)
  const responses = await Promise.all(Array.from({ length: 6 }, () => request.post('/api/auth/refresh', {
    headers: { Cookie: cookie, ...csrf }
  })))
  expect(responses.map(response => response.status())).toEqual(Array(6).fill(200))
  const successor = responses[0]!.headers()['set-cookie']!.split(';')[0]!
  expect(successor).not.toBe(cookie)
  expect(new Set(responses.map(response => response.headers()['set-cookie']!.split(';')[0])).size).toBe(1)
  for (const response of responses) {
    expect(response.headers()['cache-control']).toContain('no-store')
    expect(await response.json()).not.toHaveProperty('refresh_token')
  }
  // Real elapsed time against Symfony, not a mock accepting the old cookie forever.
  await new Promise(resolve => setTimeout(resolve, 11_000))
  const replay = await request.post('/api/auth/refresh', { headers: { Cookie: cookie, ...csrf } })
  expect(replay.status()).toBe(401)
  expect(replay.headers()['set-cookie']).toBeUndefined()
  const next = await request.post('/api/auth/refresh', { headers: { Cookie: successor, ...csrf } })
  expect(next.status()).toBe(200)
  const latest = next.headers()['set-cookie']!.split(';')[0]!
  expect(latest).not.toBe(successor)
  expect((await request.post('/api/auth/logout', { headers: { Cookie: successor, ...csrf } })).status()).toBe(200)
  expect((await request.post('/api/auth/refresh', { headers: { Cookie: latest, ...csrf } })).status()).toBe(401)
})

test('one API client and independent API clients recover concurrent 401s using the real rotation', async ({ request }) => {
  const { account, cookie } = await login(request)
  let refreshCalls = 0
  const makeClient = () => {
    let token: string | null = 'expired-access-token'
    return createApiClient({
      baseURL: '/api',
      getAccessToken: () => token,
      setAccessToken: value => { token = value },
      clearTokens: () => { token = null },
      fetch: async <T>(path: string, options?: Record<string, unknown>): Promise<T> => {
        if (path === '/auth/refresh') refreshCalls++
        const headers = Object.fromEntries((options?.headers as Headers).entries())
        const response = await request.fetch(`/api${path}`, { method: (options?.method as string) ?? 'GET', headers: { ...headers, Cookie: cookie } })
        const data = await response.json()
        if (!response.ok()) throw { status: response.status(), data }
        return data as T
      }
    })
  }
  const client = makeClient()
  const users = await Promise.all(Array.from({ length: 6 }, () => client.account.me()))
  expect(users.every(user => user.username === account.username)).toBe(true)
  expect(refreshCalls).toBe(1)
  const independent = await Promise.all(Array.from({ length: 4 }, () => makeClient().account.me()))
  expect(independent.every(user => user.username === account.username)).toBe(true)
})

test('parallel SSR requests and browser tabs share rotation without leaking between viewers', async ({ browser, baseURL }) => {
  const context = await browser.newContext({ baseURL })
  const other = await browser.newContext({ baseURL })
  try {
    const first = await login(context.request)
    const second = await login(other.request, seededSecondAccount)
    const pages = await Promise.all(Array.from({ length: 3 }, () => context.newPage()))
    const responses = await Promise.all([
      ...pages.map(page => page.goto('/')),
      context.request.get('/', { headers: { Cookie: first.cookie } }),
      context.request.get('/', { headers: { Cookie: first.cookie } }),
      other.request.get('/', { headers: { Cookie: second.cookie } })
    ])
    for (let i = 0; i < responses.length; i++) {
      const response = responses[i]!
      expect(response?.status()).toBe(200)
      const headers = await response.headers()
      expect(headers['cache-control']).toContain('private')
      expect(headers['cache-control']).toContain('no-store')
      const html = await response.text()
      expect(html).toContain(i === 5 ? second.account.username : first.account.username)
      expect(html).not.toContain(i === 5 ? first.account.email : second.account.email)
      expect(html).not.toContain(first.cookie.split('=')[1]!)
    }
    for (const page of pages) await expect(page.getByRole('button', { name: `Open profile menu for ${first.account.username}` })).toBeVisible()
    const cookie = (await context.cookies()).find(cookie => cookie.name === 'refresh_token')!
    expect(cookie.httpOnly).toBe(true)
    expect(cookie.sameSite).toBe('Strict')
    expect(cookie.value).not.toBe(first.cookie.split('=')[1])
    // Password change must clear local UI, notify the other tabs and require login.
    await pages[0]!.goto('/settings')
    await pages[0]!.getByLabel('Current password', { exact: true }).fill(first.account.password)
    const newPassword = 'session-password-after-browser-change'
    await pages[0]!.getByLabel('New password', { exact: true }).fill(newPassword)
    await pages[0]!.getByRole('button', { name: 'Update password', exact: true }).click()
    await expect(pages[0]!).toHaveURL(/\/login$/)
    for (const page of pages.slice(1)) await expect(page.getByRole('link', { name: 'Log in', exact: true })).toBeVisible()
    expect((await context.request.post('/api/auth/refresh', { headers: { Cookie: first.cookie, ...csrf } })).status()).toBe(401)
    expect((await context.request.post('/api/auth/refresh', { headers: { Cookie: `refresh_token=${cookie.value}`, ...csrf } })).status()).toBe(401)
    expect((await other.request.post('/api/auth/refresh', { headers: csrf })).status()).toBe(200)
    primaryAccount.password = newPassword
  } finally {
    await context.close()
    await other.close()
  }
})

test('password revocation wins against concurrent rotations and covers another device', async ({ request, playwright, baseURL }) => {
  const first = await login(request)
  const newPassword = 'session-password-after-race'
  const device = await playwright.request.newContext({ baseURL })
  try {
    const secondLogin = await device.post('/api/auth/login', { data: first.account })
    expect(secondLogin.status()).toBe(200)
    const secondCookie = secondLogin.headers()['set-cookie']!.split(';')[0]!
    const results = await Promise.all([
      ...Array.from({ length: 4 }, () => request.post('/api/auth/refresh', { headers: { Cookie: first.cookie, ...csrf } })),
      request.patch('/api/me/password', {
        headers: { Authorization: `Bearer ${first.token}` },
        data: { currentPassword: first.account.password, newPassword }
      })
    ])
    expect(results[4]!.status()).toBe(200)
    const cookies = [first.cookie, secondCookie]
    for (const result of results.slice(0, 4)) {
      expect([200, 401]).toContain(result.status())
      if (result.ok()) cookies.push(result.headers()['set-cookie']!.split(';')[0]!)
    }
    for (const cookie of cookies) {
      expect((await request.post('/api/auth/refresh', { headers: { Cookie: cookie, ...csrf } })).status()).toBe(401)
    }
    primaryAccount.password = newPassword
  } finally {
    await device.dispose()
  }
})

test('explicit browser logout clears every open tab after confirmed revocation', async ({ context, page }) => {
  const { account } = await login(context.request)
  const other = await context.newPage()
  await Promise.all([page.goto('/'), other.goto('/')])
  await expect(other.getByRole('button', { name: `Open profile menu for ${account.username}` })).toBeVisible()
  await page.goto('/logout')
  await expect(page).toHaveURL(/\/login$/)
  await expect(other.getByRole('link', { name: 'Log in', exact: true })).toBeVisible()
  expect((await context.cookies()).find(cookie => cookie.name === 'refresh_token')).toBeUndefined()
})

test('does not replay a comment prepared by A when the real refresh cookie identifies B', async ({ request }) => {
  const first = await login(request)
  const profile = await request.get('/api/me', { headers: { Authorization: `Bearer ${first.token}` } })
  let viewer = await profile.json()
  const second = await login(request, seededSecondAccount)
  let token: string | null = 'expired-access-token'
  let revision = 0
  let commentCalls = 0
  const api = createApiClient({
    baseURL: '/api',
    getAccessToken: () => token,
    setAccessToken: value => { token = value },
    clearTokens: () => { token = null; revision++ },
    getSessionRevision: () => revision,
    setCurrentUser: (user) => {
      if (viewer.id !== user.id) revision++
      viewer = user
    },
    fetch: async <T>(path: string, options?: Record<string, unknown>): Promise<T> => {
      if (path.endsWith('/comments')) commentCalls++
      const response = await request.fetch(`/api${path}`, {
        method: (options?.method as string) ?? 'GET',
        headers: Object.fromEntries((options?.headers as Headers).entries()),
        data: options?.body
      })
      const data = await response.json()
      if (!response.ok()) throw { status: response.status(), data }
      return data as T
    }
  })
  // No recipe fixture is necessary: the initial JWT fails before controller
  // dispatch, and the request must never be sent again after identifying B.
  await expect(api.comments.create('does-not-exist', { message: 'Prepared by A' })).rejects.toMatchObject({ code: 'session_changed' })
  expect(commentCalls).toBe(1)
  expect(viewer.username).toBe(second.account.username)
})
