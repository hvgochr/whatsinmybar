import { expect, test } from '@playwright/test'

test.describe('session bootstrap', () => {
  test('restores a valid session before rendering viewer-sensitive data', async ({ context, page }) => {
    await context.addCookies([{
      name: 'refresh_token',
      value: 'valid-session',
      domain: '127.0.0.1',
      path: '/',
      httpOnly: true,
      sameSite: 'Strict'
    }])

    const response = await page.goto('/')
    const navigation = page.getByLabel('Main navigation')

    expect(response?.ok()).toBe(true)
    await expect(navigation.getByRole('link', { name: 'Account', exact: true })).toBeVisible()
    await expect(page.getByText('Adult-only Negroni')).toBeVisible()
    await expect(navigation.getByRole('link', { name: 'Log in', exact: true })).toHaveCount(0)
    expect((await context.cookies()).find(cookie => cookie.name === 'refresh_token')?.value).toBe('rotated-session')

    await page.goto('/recipes/negroni')
    await expect(page.getByText('Authorized note')).toBeVisible()
  })

  test('clears an invalid session and keeps public pages usable', async ({ context, page }) => {
    await context.addCookies([{
      name: 'refresh_token',
      value: 'invalid-session',
      domain: '127.0.0.1',
      path: '/',
      httpOnly: true,
      sameSite: 'Strict'
    }])

    const response = await page.goto('/')
    const navigation = page.getByLabel('Main navigation')

    expect(response?.ok()).toBe(true)
    await expect(navigation.getByRole('link', { name: 'Log in', exact: true })).toBeVisible()
    await expect(page.getByText('Citrus Spritz')).toBeVisible()
    expect((await context.cookies()).find(cookie => cookie.name === 'refresh_token')).toBeUndefined()
  })

  test('renders public data when no session exists', async ({ page }) => {
    const response = await page.goto('/')
    const navigation = page.getByLabel('Main navigation')

    expect(response?.ok()).toBe(true)
    await expect(navigation.getByRole('link', { name: 'Log in', exact: true })).toBeVisible()
    await expect(page.getByText('Citrus Spritz')).toBeVisible()
    await expect(page.getByText('Adult-only Negroni')).toHaveCount(0)
  })
})
