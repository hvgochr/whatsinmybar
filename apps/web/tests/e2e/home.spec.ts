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
