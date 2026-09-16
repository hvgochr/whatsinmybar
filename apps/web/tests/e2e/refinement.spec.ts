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
  // Wait for the SSR header to hydrate before opening its menu.
  await page.waitForLoadState('networkidle')
  await page.getByRole('button', { name: 'Open profile menu for jane_doe' }).click()
  await expect(page.getByRole('menuitem', { name: 'Profile' })).toBeVisible()
  await expect(page.getByRole('menuitem', { name: 'Settings' })).toBeVisible()
  await expect(page.getByRole('menuitem', { name: 'Administration' })).toBeVisible()
  await expect(page.getByRole('menuitem', { name: 'Log out' })).toBeVisible()
  await expect(page.getByRole('menuitem', { name: /My recipes|My favorites|Appearance/ })).toHaveCount(0)
})

test('anonymous header uses system appearance and keeps theme access in mobile navigation', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/')
  await page.waitForLoadState('networkidle')
  await page.getByRole('button', { name: 'Open navigation' }).click()

  await expect(page.getByRole('group', { name: 'Appearance' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'System' })).toHaveAttribute('aria-pressed', 'true')
  await expect(page.getByRole('dialog').getByRole('searchbox', { name: 'Search recipes' })).toBeVisible()
})

test('theme choices remain available in settings and provide feedback', async ({ context, page }) => {
  await useAdultSession(context)
  await page.goto('/settings#appearance')
  await page.getByRole('group', { name: 'Appearance' }).getByRole('button', { name: 'Dark' }).click()
  await expect(page.getByText('Appearance updated.')).toBeVisible()
  await expect(page.getByRole('group', { name: 'Appearance' }).getByRole('button', { name: 'Dark' })).toHaveAttribute('aria-pressed', 'true')
})

test('system theme is applied on first render and follows operating system changes', async ({ page }) => {
  await page.emulateMedia({ colorScheme: 'dark' })
  await page.addInitScript(() => localStorage.setItem('theme', 'system'))
  await page.goto('/')
  await expect(page.locator('html')).toHaveClass(/dark/)
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'system')

  await page.emulateMedia({ colorScheme: 'light' })
  await expect(page.locator('html')).not.toHaveClass(/dark/)
})

test('account mutations provide concise completion feedback', async ({ context, page }) => {
  await useAdultSession(context)
  await page.goto('/settings')

  await page.getByLabel('Bio').fill('Updated profile biography.')
  await page.getByRole('button', { name: 'Save profile' }).click()
  await expect(page.getByText('Profile updated.')).toBeVisible()

  await page.getByLabel('Avatar image').setInputFiles({
    name: 'avatar.png',
    mimeType: 'image/png',
    buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=', 'base64')
  })
  await page.getByRole('button', { name: 'Upload avatar' }).click()
  await expect(page.getByText('Avatar updated.')).toBeVisible()

  await page.getByLabel('Current password').fill('very-secure-password')
  await page.getByLabel('New password').fill('another-secure-password')
  await page.getByRole('button', { name: 'Update password' }).click()
  await expect(page.getByText('Password updated.')).toBeVisible()
})

test('recipe cards expose unboxed favorite state and collection counts', async ({ context, page }) => {
  await useAdultSession(context)
  await page.goto('/')

  const favorite = page.getByRole('button', { name: 'Remove negroni from favorites' }).first()
  await expect(favorite).toHaveAttribute('aria-pressed', 'true')
  await expect(favorite).toContainText('4')
  await page.waitForLoadState('networkidle')
  await favorite.click()
  await expect(page.getByText('Removed from favorites.')).toBeVisible()
})

test('recipe filters remain visible, update the URL, and remove active filters', async ({ page }) => {
  await page.goto('/recipes?ingredient=gin&page=2')
  await expect(page.getByRole('combobox', { name: 'Sort order', exact: true })).toBeVisible()
  await expect(page.getByRole('combobox', { name: 'Category', exact: true })).toBeVisible()
  await expect(page.getByRole('combobox', { name: 'Ingredient', exact: true })).toBeVisible()
  await expect(page.getByRole('combobox', { name: 'Alcohol preference', exact: true })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Filter recipes' })).toHaveCount(0)
  await expect(page.getByText('Advanced filters', { exact: true })).toHaveCount(0)

  await page.waitForLoadState('networkidle')
  await page.getByRole('combobox', { name: 'Category', exact: true }).selectOption('classics')
  await expect(page).toHaveURL(/\/recipes\?category=classics&ingredient=gin$/)
  await expect(page.getByRole('link', { name: 'Remove Ingredient filter' })).toBeVisible()
  await page.getByRole('link', { name: 'Remove Ingredient filter' }).click()
  await expect(page).toHaveURL(/\/recipes\?category=classics$/)
  await expect(page.getByRole('link', { name: 'Clear filters' })).toBeVisible()
  await page.getByRole('link', { name: 'Clear filters' }).click()
  await expect(page).toHaveURL(/\/recipes$/)
  await expect(page.locator('[data-sonner-toast]')).toHaveCount(0)

  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/recipes')
  await expect(page.getByRole('combobox', { name: 'Category', exact: true })).toBeVisible()
  await expect(page.getByRole('combobox', { name: 'Ingredient', exact: true })).toBeVisible()
  await expect(page.getByRole('combobox', { name: 'Alcohol preference', exact: true })).toBeVisible()
  await expect(page.getByText('Advanced filters', { exact: true })).toHaveCount(0)
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true)
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

test('profile collections remain private and obsolete collection routes do not exist', async ({ context, page }) => {
  await page.goto('/users/jane_doe')
  await expect(page.getByRole('heading', { name: 'Published recipes' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'My recipes', exact: true })).toHaveCount(0)
  await expect(page.getByRole('heading', { name: 'My favorites', exact: true })).toHaveCount(0)

  await useAdultSession(context)
  await page.goto('/users/jane_doe#favorites')
  await page.reload()
  await expect(page.getByRole('heading', { name: 'My favorites', exact: true })).toBeVisible()

  for (const path of ['/favorites', '/my-recipes']) {
    const response = await page.goto(path)
    expect(response?.status()).toBe(404)
  }
})

test('category collections reuse contextual filters without a category selector', async ({ page }) => {
  await page.goto('/categories/classics?category=mocktails&ingredient=gin&alcohol=with&page=2')
  await expect(page.getByRole('combobox', { name: 'Sort order', exact: true })).toBeVisible()
  await expect(page.getByRole('combobox', { name: 'Category', exact: true })).toHaveCount(0)
  await expect(page.getByRole('combobox', { name: 'Ingredient', exact: true })).toBeVisible()
  await expect(page.getByRole('combobox', { name: 'Alcohol preference', exact: true })).toBeVisible()
  await expect(page.getByText('Advanced filters', { exact: true })).toHaveCount(0)
  await expect(page.getByRole('link', { name: 'Remove Ingredient filter' })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Remove Alcohol filter' })).toBeVisible()

  await page.waitForLoadState('networkidle')
  await page.getByRole('combobox', { name: 'Ingredient', exact: true }).selectOption('')
  await expect(page).toHaveURL(/\/categories\/classics\?alcohol=with$/)

  await page.setViewportSize({ width: 390, height: 844 })
  await page.reload()
  await expect(page.getByRole('combobox', { name: 'Ingredient', exact: true })).toBeVisible()
  await expect(page.getByRole('combobox', { name: 'Alcohol preference', exact: true })).toBeVisible()
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true)
})

test('comments show author identity and keep actions together', async ({ context, page }) => {
  await useAdultSession(context)
  await page.goto('/recipes/negroni')
  const profileLink = page.getByRole('link', { name: "View jane_doe's profile" })
  await expect(profileLink).toBeVisible()
  await expect(profileLink).toContainText('J')
  const actions = page.getByLabel('Comment actions')
  await expect(actions.getByRole('button', { name: 'Reply' })).toBeVisible()
  await expect(actions.getByRole('button', { name: 'Edit' })).toBeVisible()
  await expect(actions.getByRole('button', { name: 'Delete' })).toBeVisible()
  await expect(actions.getByRole('button', { name: 'Report this comment' })).toBeVisible()
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

  await page.goto('/admin/recipes')
  await page.getByRole('table').getByRole('combobox').first().selectOption('draft')
  await expect(page.getByText('Recipe updated.')).toBeVisible()
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
