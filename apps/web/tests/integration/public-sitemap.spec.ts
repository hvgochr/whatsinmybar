import { expect, test, type APIRequestContext } from '@playwright/test'

interface RecipeSummary {
  slug: string
}

interface RecipeCollection {
  'hydra:member'?: RecipeSummary[]
  'hydra:totalItems'?: number
  member?: RecipeSummary[]
  totalItems?: number
}

function collectionItems(collection: RecipeCollection): RecipeSummary[] {
  return collection.member ?? collection['hydra:member'] ?? []
}

function collectionTotal(collection: RecipeCollection): number {
  return collection.totalItems ?? collection['hydra:totalItems'] ?? 0
}

async function publicRecipes(request: APIRequestContext, page = 1): Promise<RecipeCollection> {
  const response = await request.get(`/api/recipes?page=${page}`, { headers: { Accept: 'application/ld+json' } })
  expect(response.status()).toBe(200)
  return await response.json() as RecipeCollection
}

test('real sitemap follows multiple anonymous recipe pages without exposing alcohol', async ({ request }) => {
  test.setTimeout(120_000)

  const initial = await publicRecipes(request)
  const missingRecipes = Math.max(0, 31 - collectionTotal(initial))

  if (missingRecipes > 0) {
    const login = await request.post('/api/auth/login', {
      data: { email: 'max@example.com', password: 'very-secure-password' }
    })
    expect(login.status(), 'Run `make seed` before the real-stack integration suite.').toBe(200)
    const token = (await login.json()).token as string
    const suffix = crypto.randomUUID().replaceAll('-', '').slice(0, 12)
    const headers = { Authorization: `Bearer ${token}` }

    for (let index = 1; index <= missingRecipes; index++) {
      const created = await request.post('/api/recipes/aggregate', {
        headers,
        data: {
          title: `Sitemap Pagination ${suffix} ${index}`,
          description: 'A zero-proof integration fixture for sitemap pagination.',
          difficulty: 'easy',
          preparationTimeMinutes: 2,
          servings: 1,
          categories: ['/api/categories/zero-proof'],
          steps: [{ instruction: 'Build over ice and stir.' }],
          ingredients: [{ ingredient: '/api/ingredients/lime-juice', quantity: '15', unit: 'ml' }]
        }
      })
      expect(created.status()).toBe(201)
      const slug = (await created.json()).slug as string
      const published = await request.post(`/api/recipes/${slug}/publish`, { headers })
      expect(published.status()).toBe(200)
    }
  }

  const firstPage = await publicRecipes(request)
  const totalItems = collectionTotal(firstPage)
  expect(totalItems).toBeGreaterThan(30)
  const lastPage = await publicRecipes(request, Math.ceil(totalItems / 30))
  const firstSlug = collectionItems(firstPage)[0]?.slug
  const lastSlug = collectionItems(lastPage).at(-1)?.slug
  expect(firstSlug).toBeTruthy()
  expect(lastSlug).toBeTruthy()

  const response = await request.get('/sitemap.xml')
  expect(response.status()).toBe(200)
  const xml = await response.text()
  expect(xml).toContain(`/recipes/${firstSlug}</loc>`)
  expect(xml).toContain(`/recipes/${lastSlug}</loc>`)
  expect(xml).not.toContain('/recipes/seed-negroni</loc>')
})
