import { expect, test } from '@playwright/test'

test('renders the home page', async ({ page }) => {
  await page.goto('/')

  await expect(page.getByRole('heading', { name: "What's In My Bar" })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Create an account' })).toBeVisible()
})
