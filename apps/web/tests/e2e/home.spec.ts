import { expect, test } from '@playwright/test'

const publicPages = [
  { path: '/', heading: "What's In My Bar" },
  { path: '/recipes', heading: 'Find your next cocktail' },
  { path: '/categories', heading: 'Browse by occasion and style' },
  { path: '/login', heading: 'Log in to your bar' },
  { path: '/register', heading: 'Create your cocktail profile' }
] as const

for (const publicPage of publicPages) {
  test(`renders ${publicPage.path}`, async ({ page }) => {
    const response = await page.goto(publicPage.path)

    expect(response?.ok()).toBe(true)
    await expect(page.getByRole('heading', { level: 1, name: publicPage.heading })).toBeVisible()
  })
}
