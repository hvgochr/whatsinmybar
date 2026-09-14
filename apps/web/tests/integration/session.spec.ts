import { expect, test } from '@playwright/test'

test('concurrent refreshes converge on one bounded rotation with Symfony', async ({ request }) => {
  const suffix = crypto.randomUUID().replaceAll('-', '').slice(0, 12)
  const account = { email: `session-${suffix}@example.com`, username: `session_${suffix}`, password: 'very-secure-password', birthDate: '1990-01-01' }
  expect((await request.post('/api/auth/register', { data: account })).status()).toBe(201)
  const login = await request.post('/api/auth/login', { data: account })
  expect(login.status()).toBe(200)
  const cookie = login.headers()['set-cookie']!.split(';')[0]!
  const responses = await Promise.all(Array.from({ length: 6 }, () => request.post('/api/auth/refresh', {
    headers: { Cookie: cookie, 'X-CSRF-Protection': '1' }
  })))
  expect(responses.map(response => response.status())).toEqual(Array(6).fill(200))
  expect(new Set(responses.map(response => response.headers()['set-cookie']!.split(';')[0])).size).toBe(1)
})
