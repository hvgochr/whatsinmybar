import { expect, test, type APIRequestContext } from '@playwright/test'

interface RecipeCollection {
  'hydra:member'?: Array<{ slug: string }>
  member?: Array<{ slug: string }>
}

async function accessToken(request: APIRequestContext, email: string, password = 'very-secure-password'): Promise<string> {
  const response = await request.post('/api/auth/login', { data: { email, password } })
  expect(response.status()).toBe(200)
  return (await response.json()).token as string
}

function slugs(collection: RecipeCollection): string[] {
  return (collection.member ?? collection['hydra:member'] ?? []).map(recipe => recipe.slug)
}

test('real stack covers age visibility, authoring, upload, social actions and moderation', async ({ request }) => {
  const suffix = crypto.randomUUID().replaceAll('-', '').slice(0, 12)
  const minor = {
    email: `release-minor-${suffix}@example.com`,
    username: `release_minor_${suffix}`,
    password: 'very-secure-password',
    birthDate: '2012-01-01'
  }
  expect((await request.post('/api/auth/register', { data: minor })).status()).toBe(201)

  const [adultToken, minorToken, adminToken] = await Promise.all([
    accessToken(request, 'max@example.com'),
    accessToken(request, minor.email),
    accessToken(request, 'admin@example.com')
  ])
  const adultHeaders = { Authorization: `Bearer ${adultToken}` }
  const minorHeaders = { Authorization: `Bearer ${minorToken}` }
  const adminHeaders = { Authorization: `Bearer ${adminToken}` }

  const anonymousCollectionResponse = await request.get('/api/recipes?page=1', { headers: { Accept: 'application/ld+json' } })
  expect(anonymousCollectionResponse.status()).toBe(200)
  expect(slugs(await anonymousCollectionResponse.json())).not.toContain('seed-negroni')
  expect((await request.get('/api/recipes/seed-negroni')).status()).toBe(401)

  const minorCollectionResponse = await request.get('/api/recipes?page=1', {
    headers: { ...minorHeaders, Accept: 'application/ld+json' }
  })
  expect(minorCollectionResponse.status()).toBe(200)
  expect(slugs(await minorCollectionResponse.json())).not.toContain('seed-negroni')
  expect((await request.get('/api/recipes/seed-negroni', { headers: minorHeaders })).status()).toBe(403)
  expect((await request.get('/api/recipes/seed-negroni', { headers: adultHeaders })).status()).toBe(200)

  const created = await request.post('/api/recipes/aggregate', {
    headers: adultHeaders,
    data: {
      title: `Release Flow ${suffix}`,
      description: 'A zero-proof recipe created by the real-stack release acceptance test.',
      difficulty: 'easy',
      preparationTimeMinutes: 3,
      servings: 1,
      categories: ['/api/categories/zero-proof'],
      steps: [{ instruction: 'Build over ice and stir.' }],
      ingredients: [{ ingredient: '/api/ingredients/lime-juice', quantity: '20', unit: 'ml' }]
    }
  })
  expect(created.status()).toBe(201)
  const recipe = await created.json() as { id: number, slug: string }

  const image = await request.post(`/api/recipes/${recipe.slug}/image`, {
    headers: adultHeaders,
    multipart: {
      image: {
        name: 'release-flow.png',
        mimeType: 'image/png',
        buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', 'base64')
      }
    }
  })
  expect(image.status()).toBe(200)
  const imagePath = (await image.json()).imagePath as string
  expect(imagePath).toMatch(/^\/uploads\/recipes\/[a-f0-9]{32}\.png$/)
  const filename = imagePath.split('/').at(-1)!
  expect((await request.get(`/api/recipe-images/${filename}`)).status()).toBe(404)
  expect((await request.get(`/api/recipe-images/${filename}`, { headers: adultHeaders })).status()).toBe(200)

  const published = await request.post(`/api/recipes/${recipe.slug}/publish`, { headers: adultHeaders })
  expect(published.status()).toBe(200)
  expect((await published.json()).status).toBe('published')
  expect((await request.get(`/api/recipes/${recipe.slug}`)).status()).toBe(200)
  expect((await request.get(`/api/recipe-images/${filename}`)).status()).toBe(200)

  const firstFavorite = await request.post(`/api/recipes/${recipe.slug}/favorite`, { headers: minorHeaders })
  expect(firstFavorite.status()).toBe(200)
  expect(await firstFavorite.json()).toMatchObject({ changed: true, favorited: true })
  const duplicateFavorite = await request.post(`/api/recipes/${recipe.slug}/favorite`, { headers: minorHeaders })
  expect(await duplicateFavorite.json()).toMatchObject({ changed: false, favorited: true })

  const commentResponse = await request.post(`/api/recipes/${recipe.slug}/comments`, {
    headers: minorHeaders,
    data: { message: 'Release acceptance comment.' }
  })
  expect(commentResponse.status()).toBe(201)
  const comment = await commentResponse.json() as { id: number }

  const reportResponse = await request.post('/api/reports', {
    headers: adultHeaders,
    data: { targetType: 'comment', targetId: comment.id, reason: 'spam', message: 'Release acceptance moderation check.' }
  })
  expect(reportResponse.status()).toBe(201)
  const report = await reportResponse.json() as { id: number }
  expect(report).not.toHaveProperty('targetContext')

  const moderated = await request.patch(`/api/admin/reports/${report.id}`, {
    headers: adminHeaders,
    data: { status: 'resolved', moderationStatus: 'hidden' }
  })
  expect(moderated.status()).toBe(200)
  expect(await moderated.json()).toMatchObject({ status: 'resolved', targetContext: { moderationStatus: 'hidden' } })

  const commentsResponse = await request.get(`/api/recipes/${recipe.slug}/comments`)
  expect(commentsResponse.status()).toBe(200)
  const comments = (await commentsResponse.json()).items as Array<{ id: number, message: string | null, moderationStatus: string }>
  expect(comments.find(item => item.id === comment.id)).toMatchObject({ message: null, moderationStatus: 'hidden' })

  expect((await request.delete(`/api/recipes/${recipe.slug}/favorite`, { headers: minorHeaders })).status()).toBe(200)
  expect((await request.delete(`/api/recipes/${recipe.slug}/favorite`, { headers: minorHeaders })).status()).toBe(200)
})
