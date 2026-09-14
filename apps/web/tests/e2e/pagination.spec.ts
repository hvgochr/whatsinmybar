import { expect, test } from '@playwright/test'

for (const path of ['/recipes', '/categories/classics']) {
  test(`${path} paginates 65 recipes and preserves URL parameters`, async ({ page }) => {
    await page.goto(`${path}?q=pagination&ingredient=gin&sort=popular&tracking=kept`)
    const nav = page.getByRole('navigation', { name: /recipe.*pagination/i })
    await expect(nav).toContainText('Showing 1-30 of 65')
    await expect(nav.getByRole('button', { name: 'Previous' })).toBeDisabled()
    await nav.getByRole('link', { name: 'Next' }).click()
    await expect(nav).toContainText('Showing 31-60 of 65')
    expect(new URL(page.url()).searchParams.get('tracking')).toBe('kept')
    expect(new URL(page.url()).searchParams.get('ingredient')).toBe('gin')
    expect(new URL(page.url()).searchParams.get('sort')).toBe('popular')
    await nav.getByRole('link', { name: 'Next' }).click()
    await expect(nav).toContainText('Showing 61-65 of 65')
    await expect(nav.getByRole('button', { name: 'Next' })).toBeDisabled()

    await page.goto(`${path}?q=pagination&page=4&tracking=kept`)
    await expect(nav).toContainText('65 total results')
    await expect(nav.getByRole('button', { name: 'Next' })).toBeDisabled()
    await nav.getByRole('link', { name: 'Previous' }).click()
    await expect(nav).toContainText('Showing 61-65 of 65')
    expect(new URL(page.url()).searchParams.get('tracking')).toBe('kept')

    await page.waitForLoadState('networkidle')
    await page.getByRole('combobox', { name: 'Ingredient', exact: true }).selectOption('ingredient-65')
    await expect(nav).toContainText('Showing 1-30 of 65')
    expect(new URL(page.url()).searchParams.has('page')).toBe(false)

    await page.goto(`${path}?q=empty-pagination`)
    await expect(nav).toHaveCount(0)
    await expect(page.getByRole('heading', { name: /No recipes/ })).toBeVisible()
  })
}

test('public profile recipes paginate independently of private URL parameters', async ({ page }) => {
  await page.goto('/users/pagination_user?recipesPage=2&favoritesPage=3')
  const nav = page.getByRole('navigation', { name: 'Published recipes pagination' })
  await expect(nav).toContainText('Showing 1-30 of 65')
  await nav.getByRole('link', { name: 'Next' }).click()
  await expect(nav).toContainText('Showing 31-60 of 65')
  expect(new URL(page.url()).searchParams.get('recipesPage')).toBe('2')
  expect(new URL(page.url()).searchParams.get('favoritesPage')).toBe('3')
  await nav.getByRole('link', { name: 'Next' }).click()
  await expect(nav).toContainText('Showing 61-65 of 65')
  await expect(nav.getByRole('button', { name: 'Next' })).toBeDisabled()
})

test('category directory, selectors and direct ingredient editing reach item 65', async ({ context, page }) => {
  await page.goto('/categories')
  await expect(page.getByRole('heading', { name: 'Category 65', exact: true })).toBeVisible()
  await page.goto('/recipes')
  await expect(page.getByRole('combobox', { name: 'Category', exact: true }).locator('option[value="category-65"]')).toHaveCount(1)
  await expect(page.getByRole('combobox', { name: 'Ingredient', exact: true }).locator('option[value="ingredient-65"]')).toHaveCount(1)
  await context.addCookies([{ name: 'refresh_token', value: 'valid-session', domain: '127.0.0.1', path: '/', httpOnly: true, sameSite: 'Strict' }])
  await page.goto('/admin/ingredients/ingredient-65/edit')
  await expect(page.getByLabel('Name', { exact: true })).toHaveValue('Ingredient 65')
})
