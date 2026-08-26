import { expect, test } from '@playwright/test'

async function useAdultSession(context: import('@playwright/test').BrowserContext) {
  await context.addCookies([{
    name: 'refresh_token',
    value: 'valid-session',
    domain: '127.0.0.1',
    path: '/',
    httpOnly: true,
    sameSite: 'Strict'
  }])
}

test('desktop header balances search and authenticated recipe actions', async ({ context, page }) => {
  await useAdultSession(context)
  await page.goto('/')

  await expect(page.getByRole('search').getByPlaceholder('Search recipes')).toBeVisible()
  await expect(page.getByRole('link', { name: 'Create recipe', exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Open profile menu for jane_doe' }).click()
  await expect(page.getByRole('menuitem', { name: 'Appearance' })).toBeVisible()
  await expect(page.getByRole('menuitem', { name: 'My recipes' })).toBeVisible()
})

test('anonymous header uses system appearance and keeps theme access in mobile navigation', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/')
  await page.getByRole('button', { name: 'Open navigation' }).click()

  await expect(page.getByRole('group', { name: 'Appearance' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'System' })).toHaveAttribute('aria-pressed', 'true')
  await expect(page.getByRole('dialog').getByRole('searchbox', { name: 'Search recipes' })).toBeVisible()
})

test('recipe cards expose unboxed favorite state and collection counts', async ({ context, page }) => {
  await useAdultSession(context)
  await page.goto('/')

  const favorite = page.getByRole('button', { name: 'Remove negroni from favorites' }).first()
  await expect(favorite).toHaveAttribute('aria-pressed', 'true')
  await expect(favorite).toContainText('4')
})

test('recipe filters separate primary and advanced controls and remove active filters', async ({ page }) => {
  await page.goto('/recipes?ingredient=gin')
  await expect(page.getByLabel('Category')).toBeVisible()
  await expect(page.locator('details').getByText('Advanced filters', { exact: true })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Remove Ingredient filter' })).toBeVisible()
  await page.getByRole('link', { name: 'Remove Ingredient filter' }).click()
  await expect(page).toHaveURL(/\/recipes$/)

  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/recipes')
  await page.getByRole('button', { name: 'Advanced' }).click()
  await expect(page.getByRole('dialog').getByRole('heading', { name: 'Advanced filters' })).toBeVisible()
})

test('categories render bounded recipe shelves with mobile scroll discovery', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/categories')

  await expect(page.getByRole('heading', { name: 'Classics', level: 2 })).toBeVisible()
  await expect(page.getByRole('link', { name: 'View Citrus Spritz' })).toBeVisible()
  const shelf = page.getByRole('link', { name: 'View Citrus Spritz' }).locator('..')
  await expect(shelf).toHaveCSS('scroll-snap-align', 'start')
})

test('recipe detail follows the cooking sequence and uses report dialog', async ({ context, page }) => {
  await useAdultSession(context)
  await page.route('**/api/reports', route => route.fulfill({
    contentType: 'application/json',
    json: { id: 19, message: 'This needs review.', reason: 'spam', status: 'open', targetId: 11, targetType: 'recipe' },
    status: 201
  }))
  await page.goto('/recipes/negroni')

  const ingredients = page.getByRole('heading', { name: 'Ingredients' })
  const preparation = page.getByRole('heading', { name: 'Preparation' })
  const comments = page.getByRole('heading', { name: 'Comments' })
  const related = page.getByRole('heading', { name: 'Related recipes' })
  const order = await Promise.all([ingredients, preparation, comments, related].map(locator => locator.evaluate(element => Array.from(document.querySelectorAll('h2')).indexOf(element))))
  expect(order).toEqual([...order].sort((a, b) => a - b))

  const favorite = page.getByRole('button', { name: 'Remove negroni from favorites' })
  await expect(favorite).toHaveAttribute('aria-pressed', 'true')
  // The page is server-rendered; wait for client hydration before exercising the modal trigger.
  await page.waitForTimeout(800)
  await page.getByRole('button', { name: 'Report this recipe' }).click()
  const dialog = page.getByRole('dialog')
  await expect(dialog.getByRole('heading', { name: 'Report this recipe' })).toBeVisible()
  await dialog.getByLabel(/Details/).fill('This needs review.')
  await dialog.getByRole('button', { name: 'Submit report' }).click()
  await expect(page.getByText('Report submitted.')).toBeVisible()
})

test('profile collections remain private and legacy collection routes redirect', async ({ context, page }) => {
  await page.goto('/users/jane_doe')
  await expect(page.getByRole('heading', { name: 'Published recipes' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'My recipes', exact: true })).toHaveCount(0)
  await expect(page.getByRole('heading', { name: 'My favorites', exact: true })).toHaveCount(0)

  await useAdultSession(context)
  await page.goto('/favorites')
  await expect(page).toHaveURL(/\/users\/jane_doe#favorites$/)
  await expect(page.getByRole('heading', { name: 'My favorites', exact: true })).toBeVisible()
})

test('admin resources use tables and taxonomy forms use dedicated pages', async ({ context, page }) => {
  await useAdultSession(context)

  for (const route of ['/admin/users', '/admin/recipes', '/admin/comments', '/admin/ingredients', '/admin/categories']) {
    await page.goto(route)
    await expect(page.getByRole('table')).toBeVisible()
    await expect(page.getByLabel('Filter this page')).toBeVisible()
  }

  for (const formRoute of [
    { path: '/admin/ingredients/new', heading: 'Create ingredient' },
    { path: '/admin/ingredients/gin/edit', heading: 'Edit ingredient' },
    { path: '/admin/categories/new', heading: 'Create category' },
    { path: '/admin/categories/classics/edit', heading: 'Edit category' }
  ]) {
    await page.goto(formRoute.path)
    await expect(page.getByRole('heading', { name: formRoute.heading, level: 1 })).toBeVisible()
    await expect(page.getByRole('button', { name: /category|ingredient/i })).toBeVisible()
  }
})

test('destructive recipe actions require an alert dialog', async ({ context, page }) => {
  await useAdultSession(context)
  await page.goto('/recipes/negroni/edit')
  // The editor is visible from SSR before its client-side action handlers hydrate.
  await page.waitForTimeout(800)
  await page.getByRole('button', { name: 'Delete recipe', exact: true }).click()

  const alert = page.getByRole('alertdialog')
  await expect(alert.getByRole('heading', { name: 'Delete this recipe?' })).toBeVisible()
  await expect(alert.getByRole('button', { name: 'Cancel' })).toBeVisible()
  await expect(alert.getByRole('button', { name: 'Delete recipe' })).toBeVisible()
})
