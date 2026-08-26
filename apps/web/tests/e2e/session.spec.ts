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

  test('renders private recipe and favorites pages after session restoration', async ({ context, page }) => {
    await context.addCookies([{
      name: 'refresh_token',
      value: 'valid-session',
      domain: '127.0.0.1',
      path: '/',
      httpOnly: true,
      sameSite: 'Strict'
    }])

    const response = await page.goto('/my-recipes')

    expect(response?.ok()).toBe(true)
    await expect(page.getByRole('heading', { name: 'My recipes' })).toBeVisible()
    await expect(page.getByText('Unfinished Collins')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Publish' })).toBeVisible()

    await page.goto('/favorites')
    await expect(page.getByRole('heading', { name: 'My favorites' })).toBeVisible()
    await expect(page.getByText('Adult-only Negroni')).toBeVisible()

    await page.goto('/recipes/new')
    await expect(page.getByRole('heading', { name: 'Create a recipe' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Basic information' })).toBeVisible()

    await page.goto('/recipes/negroni/edit')
    await expect(page.getByRole('heading', { name: 'Edit Adult-only Negroni' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Basic information' })).toBeVisible()
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
    expect(response?.ok()).toBe(true)
    await expect(page.getByRole('link', { name: 'Log in', exact: true })).toBeVisible()
    await expect(page.getByText('Citrus Spritz').first()).toBeVisible()
    expect((await context.cookies()).find(cookie => cookie.name === 'refresh_token')).toBeUndefined()
  })

  test('renders public data when no session exists', async ({ page }) => {
    const response = await page.goto('/')
    expect(response?.ok()).toBe(true)
    await expect(page.getByRole('link', { name: 'Log in', exact: true })).toBeVisible()
    await expect(page.getByText('Citrus Spritz').first()).toBeVisible()
    await expect(page.getByText('Adult-only Negroni')).toHaveCount(0)
  })
})
