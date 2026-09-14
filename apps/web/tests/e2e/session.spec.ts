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
    expect(response?.ok()).toBe(true)
    await expect(page.getByRole('button', { name: 'Open profile menu for jane_doe' })).toBeVisible()
    await expect(page.getByText('Adult-only Negroni').first()).toBeVisible()
    await expect(page.getByRole('link', { name: 'Log in', exact: true })).toHaveCount(0)
    expect((await context.cookies()).find(cookie => cookie.name === 'refresh_token')?.value).toBe('rotated-session')

    await page.goto('/recipes/negroni')
    await expect(page.getByText('Authorized note')).toBeVisible()
  })

  test('renders private recipe and favorites sections on the owner profile', async ({ context, page }) => {
    await context.addCookies([{
      name: 'refresh_token',
      value: 'valid-session',
      domain: '127.0.0.1',
      path: '/',
      httpOnly: true,
      sameSite: 'Strict'
    }])

    const response = await page.goto('/users/jane_doe#my-recipes')

    expect(response?.ok()).toBe(true)
    await expect(page.getByRole('heading', { name: 'My recipes', exact: true })).toBeVisible()
    await expect(page.getByText('Unfinished Collins')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Publish' })).toBeVisible()

    await page.goto('/users/jane_doe#favorites')
    await expect(page.getByRole('heading', { name: 'My favorites', exact: true })).toBeVisible()
    await expect(page.getByRole('region', { name: 'My favorites' }).getByRole('link', { name: 'View Adult-only Negroni' })).toBeVisible()

    await page.goto('/users/jane_doe#my-recipes')
    await expect(page.getByRole('heading', { name: 'My recipes', exact: true })).toBeVisible()

    await page.goto('/recipes/new')
    await expect(page.getByRole('heading', { name: 'Create a recipe' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Basic information' })).toBeVisible()

    await page.goto('/recipes/negroni/edit')
    await expect(page.getByRole('heading', { name: 'Edit Adult-only Negroni' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Basic information' })).toBeVisible()
  })

  test('rejects an invalid session without overwriting a possibly newer cookie', async ({ context, page }) => {
    await context.addCookies([{
      name: 'refresh_token',
      value: 'invalid-session',
      domain: '127.0.0.1',
      path: '/',
      httpOnly: true,
      sameSite: 'Strict'
    }])

    const response = await page.goto('/')
    expect(response?.ok()).toBe(true)
    await expect(page.getByRole('link', { name: 'Log in', exact: true })).toBeVisible()
    await expect(page.getByText('Citrus Spritz').first()).toBeVisible()
    expect((await context.cookies()).find(cookie => cookie.name === 'refresh_token')?.value).toBe('invalid-session')
  })

  for (const value of ['temporary-session', 'timeout-session']) {
    test(`keeps public SSR usable and the cookie intact during ${value}`, async ({ context, page }) => {
      await context.addCookies([{ name: 'refresh_token', value, domain: '127.0.0.1', path: '/', httpOnly: true, sameSite: 'Strict' }])
      const started = Date.now()
      const response = await page.goto('/')
      expect(response?.ok()).toBe(true)
      expect(Date.now() - started).toBeLessThan(8000)
      expect(await response!.text()).toContain('Citrus Spritz')
      await expect(page.getByRole('button', { name: 'Retry session' })).toBeVisible()
      await expect(page.getByText('Adult-only Negroni')).toHaveCount(0)
      expect((await context.cookies()).find(cookie => cookie.name === 'refresh_token')?.value).toBe(value)
    })
  }

  test('renders public data when no session exists', async ({ page }) => {
    const response = await page.goto('/')
    expect(response?.ok()).toBe(true)
    await expect(page.getByRole('link', { name: 'Log in', exact: true })).toBeVisible()
    await expect(page.getByText('Citrus Spritz').first()).toBeVisible()
    await expect(page.getByText('Adult-only Negroni')).toHaveCount(0)
  })
})
