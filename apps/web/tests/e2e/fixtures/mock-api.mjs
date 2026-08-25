import { createServer } from 'node:http'

const adultUser = {
  id: 1,
  email: 'jane@example.com',
  username: 'jane_doe',
  birthDate: '1990-01-01',
  bio: null,
  avatarPath: null,
  roles: ['ROLE_USER'],
  createdAt: '2026-07-25T10:00:00+00:00',
  updatedAt: '2026-07-25T10:00:00+00:00'
}

const negroni = {
  id: 1,
  title: 'Adult-only Negroni',
  slug: 'negroni',
  description: 'A bitter, stirred classic.',
  difficulty: 'easy',
  preparationTimeMinutes: 5,
  servings: 1,
  status: 'published',
  moderationStatus: 'visible',
  containsAlcohol: true,
  imagePath: null,
  favoriteCount: 4,
  favorited: true,
  authorUsername: 'jane_doe',
  categories: [],
  recipeIngredients: [],
  steps: [],
  publishedAt: '2026-07-25T10:00:00+00:00'
}

const zeroProofRecipe = {
  ...negroni,
  id: 2,
  title: 'Citrus Spritz',
  slug: 'citrus-spritz',
  containsAlcohol: false,
  favorited: false
}

const draftRecipe = {
  ...zeroProofRecipe,
  id: 3,
  title: 'Unfinished Collins',
  slug: 'unfinished-collins',
  status: 'draft'
}

createServer((request, response) => {
  const url = new URL(request.url ?? '/', 'http://127.0.0.1:3001')
  const authorized = request.headers.authorization === 'Bearer adult-access-token'

  if (url.pathname === '/health') {
    return json(response, 200, { ok: true })
  }

  if (url.pathname === '/api/auth/refresh' && request.method === 'POST') {
    if (/refresh_token=(?:valid|rotated)-session/.test(request.headers.cookie ?? '')) {
      response.setHeader('Set-Cookie', 'refresh_token=rotated-session; Path=/; HttpOnly; SameSite=Strict')
      return json(response, 200, { token: 'adult-access-token' })
    }

    response.setHeader('Set-Cookie', 'refresh_token=; Path=/; Max-Age=0; HttpOnly; SameSite=Strict')
    return apiError(response, 401, 'No refresh session.')
  }

  if (url.pathname === '/api/auth/logout' && request.method === 'POST') {
    response.setHeader('Set-Cookie', 'refresh_token=; Path=/; Max-Age=0; HttpOnly; SameSite=Strict')
    return json(response, 200, { loggedOut: true })
  }

  if (url.pathname === '/api/me') {
    return authorized ? json(response, 200, adultUser) : apiError(response, 401, 'Unauthorized.')
  }

  if (url.pathname === '/api/me/recipes') {
    return authorized ? paginated(response, [draftRecipe, negroni]) : apiError(response, 401, 'Unauthorized.')
  }

  if (url.pathname === '/api/me/saved-recipes') {
    return authorized ? paginated(response, [negroni]) : apiError(response, 401, 'Unauthorized.')
  }

  if (url.pathname === '/api/recipes/negroni/favorite' && request.method === 'DELETE') {
    return authorized
      ? json(response, 200, { recipeSlug: 'negroni', favoriteCount: 3, favorited: false, changed: true })
      : apiError(response, 401, 'Unauthorized.')
  }

  if (url.pathname === '/api/recipes/negroni/comments') {
    return json(response, 200, {
      items: authorized
        ? [{
            id: 1,
            recipeSlug: 'negroni',
            authorUsername: 'jane_doe',
            parentId: null,
            message: 'Authorized note',
            moderationStatus: 'visible',
            replyCount: 0,
            deleted: false,
            createdAt: '2026-07-25T10:00:00+00:00',
            updatedAt: '2026-07-25T10:00:00+00:00'
          }]
        : []
    })
  }

  if (url.pathname === '/api/recipes/negroni') {
    return authorized ? json(response, 200, negroni) : apiError(response, 404, 'Not found.')
  }

  if (url.pathname === '/api/recipes') {
    return json(response, 200, authorized ? [negroni, zeroProofRecipe] : [zeroProofRecipe])
  }

  if (url.pathname === '/api/categories' || url.pathname === '/api/ingredients') {
    return json(response, 200, [])
  }

  return apiError(response, 404, `Unhandled mock endpoint: ${url.pathname}`)
}).listen(3001, '127.0.0.1')

function json(response, status, body) {
  response.writeHead(status, { 'Content-Type': 'application/json' })
  response.end(JSON.stringify(body))
}

function apiError(response, status, message) {
  return json(response, status, {
    error: {
      status,
      code: 'unauthorized',
      message
    }
  })
}

function paginated(response, items) {
  return json(response, 200, {
    items,
    page: 1,
    pageSize: 20,
    totalItems: items.length,
    totalPages: items.length > 0 ? 1 : 0
  })
}
