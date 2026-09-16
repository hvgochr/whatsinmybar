import { expect, test } from '@playwright/test'

const publicPages = [
  { path: '/', heading: 'Find a recipe worth making.' },
  { path: '/recipes', heading: 'Recipes' },
  { path: '/recipes/citrus-spritz', heading: 'Citrus Spritz' },
  { path: '/categories', heading: 'Categories' },
  { path: '/users/jane_doe', heading: 'jane_doe' },
  { path: '/login', heading: 'Log in' },
  { path: '/register', heading: 'Create an account' }
] as const

for (const publicPage of publicPages) {
  test(`renders ${publicPage.path}`, async ({ page }) => {
    const response = await page.goto(publicPage.path)

    expect(response?.ok()).toBe(true)
    await expect(page.getByRole('heading', { level: 1, name: publicPage.heading })).toBeVisible()
  })
}

test('sitemap contains only resources exposed by the anonymous API', async ({ request }) => {
  const response = await request.get('/sitemap.xml')
  expect(response.ok()).toBe(true)
  expect(response.headers()['content-type']).toContain('application/xml')

  const xml = await response.text()
  expect(xml).toContain('/recipes/citrus-spritz</loc>')
  expect(xml).toContain('/categories/classics</loc>')
  expect(xml).toContain('/users/jane_doe</loc>')
  expect(xml).not.toContain('/recipes/negroni</loc>')
  expect(xml).not.toContain('/recipes/unfinished-collins</loc>')
  expect(xml).not.toContain('/admin')
})

test('robots advertises the sitemap and keeps private areas out of crawlers', async ({ request }) => {
  const response = await request.get('/robots.txt')
  const robots = await response.text()

  expect(response.ok()).toBe(true)
  expect(robots).toContain('Disallow: /admin')
  expect(robots).toContain('Disallow: /recipes/*/edit')
  expect(robots).toMatch(/Sitemap: http:\/\/[^/]+\/sitemap\.xml/)
})

test('restricted recipe responses use the error design and a real HTTP status', async ({ page }) => {
  const response = await page.goto('/recipes/negroni')

  expect(response?.status()).toBe(404)
  await expect(page.getByRole('heading', { level: 1, name: 'Page not found' })).toBeVisible()
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex, nofollow')
})

test('private pages expose consistent noindex metadata and response headers', async ({ context, page }) => {
  await context.addCookies([{
    name: 'refresh_token',
    value: 'valid-session',
    domain: '127.0.0.1',
    path: '/',
    httpOnly: true,
    sameSite: 'Strict'
  }])

  const response = await page.goto('/settings')
  expect(response?.headers()['x-robots-tag']).toBe('noindex, nofollow')
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex, nofollow')
})
