import { createServer } from 'node:http'

const adultUser = {
  id: 1,
  email: 'jane@example.com',
  username: 'jane_doe',
  birthDate: '1990-01-01',
  bio: null,
  avatarPath: null,
  roles: ['ROLE_USER', 'ROLE_ADMIN'],
  createdAt: '2026-07-25T10:00:00+00:00',
  updatedAt: '2026-07-25T10:00:00+00:00'
}

const classics = {
  id: 1,
  name: 'Classics',
  slug: 'classics',
  description: 'Established recipes worth knowing.'
}

const gin = {
  id: 1,
  name: 'Gin',
  slug: 'gin',
  containsAlcohol: true
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
  categories: [classics],
  recipeIngredients: [{
    id: 1,
    ingredient: gin,
    quantity: '30',
    unit: 'ml',
    position: 1,
    note: null
  }],
  steps: [{ id: 1, position: 1, instruction: 'Stir with ice and strain into a chilled glass.' }],
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

const adminUser = {
  ...adultUser,
  deleted: false,
  deletedAt: null
}

const adminRecipe = {
  id: negroni.id,
  title: negroni.title,
  slug: negroni.slug,
  authorUsername: negroni.authorUsername,
  status: negroni.status,
  moderationStatus: negroni.moderationStatus,
  containsAlcohol: negroni.containsAlcohol,
  containsAlcoholOverride: null,
  favoriteCount: negroni.favoriteCount,
  deleted: false,
  deletedAt: null,
  publishedAt: negroni.publishedAt,
  createdAt: negroni.publishedAt,
  updatedAt: negroni.publishedAt
}

const report = {
  id: 1,
  reporterUsername: 'jane_doe',
  targetType: 'comment',
  targetId: 1,
  reason: 'spam',
  message: 'Repeated promotional links.',
  status: 'open',
  reviewedByUsername: null,
  reviewedAt: null,
  createdAt: '2026-07-25T10:00:00+00:00',
  updatedAt: '2026-07-25T10:00:00+00:00'
}

createServer((request, response) => {
  const url = new URL(request.url ?? '/', 'http://127.0.0.1:3001')
  const authorized = request.headers.authorization === 'Bearer adult-access-token'

  if (request.method === 'OPTIONS') {
    return cors(response, 204)
  }

  if (url.pathname === '/health') {
    return json(response, 200, { ok: true })
  }

  if (url.pathname === '/api/auth/refresh' && request.method === 'POST') {
    if (request.headers.cookie?.includes('refresh_token=temporary-session')) return apiError(response, 503, 'Temporarily unavailable.')
    if (request.headers.cookie?.includes('refresh_token=timeout-session')) {
      setTimeout(() => apiError(response, 503, 'Too late.'), 5000)
      return
    }
    if (/refresh_token=(?:valid|rotated)-session/.test(request.headers.cookie ?? '')) {
      response.setHeader('Set-Cookie', 'refresh_token=rotated-session; Path=/; HttpOnly; SameSite=Strict')
      return json(response, 200, { token: 'adult-access-token' })
    }

    return apiError(response, 401, 'No refresh session.')
  }

  if (url.pathname === '/api/auth/logout' && request.method === 'POST') {
    response.setHeader('Set-Cookie', 'refresh_token=; Path=/; Max-Age=0; HttpOnly; SameSite=Strict')
    return json(response, 200, { loggedOut: true })
  }

  if (url.pathname === '/api/me') {
    return authorized ? json(response, 200, adultUser) : apiError(response, 401, 'Unauthorized.')
  }

  if (url.pathname === '/api/me/avatar' && request.method === 'POST') {
    return authorized ? json(response, 200, { ...adultUser, avatarPath: '/uploads/avatars/jane.png' }) : apiError(response, 401, 'Unauthorized.')
  }

  if (url.pathname === '/api/me/password' && request.method === 'PATCH') {
    return authorized ? json(response, 200, { changed: true }) : apiError(response, 401, 'Unauthorized.')
  }

  if (['/api/users/jane_doe', '/api/users/pagination_user'].includes(url.pathname)) {
    return json(response, 200, {
      id: adultUser.id,
      username: url.pathname.split('/').at(-1),
      bio: 'Cocktail enthusiast focused on clear, practical recipes.',
      avatarPath: null,
      createdAt: adultUser.createdAt
    })
  }

  if (url.pathname === '/api/categories/classics') {
    return json(response, 200, classics)
  }

  if (url.pathname === '/api/reports' && request.method === 'POST') {
    return json(response, 201, report)
  }

  if (url.pathname === '/api/admin/users') {
    return authorized ? paginated(response, [adminUser]) : apiError(response, 403, 'Forbidden.')
  }

  if (url.pathname === '/api/admin/users/1' && request.method === 'PATCH') {
    return authorized ? json(response, 200, adminUser) : apiError(response, 403, 'Forbidden.')
  }

  if (url.pathname === '/api/admin/recipes') {
    return authorized ? paginated(response, [adminRecipe]) : apiError(response, 403, 'Forbidden.')
  }

  if (url.pathname === '/api/admin/recipes/negroni' && request.method === 'PATCH') {
    return authorized ? json(response, 200, adminRecipe) : apiError(response, 403, 'Forbidden.')
  }

  if (url.pathname === '/api/admin/categories' || url.pathname === '/api/admin/ingredients') {
    const items = url.pathname.endsWith('categories') ? [classics] : [gin]
    return authorized ? paginated(response, items) : apiError(response, 403, 'Forbidden.')
  }

  if (url.pathname === '/api/admin/reports') {
    return authorized ? paginated(response, [report]) : apiError(response, 403, 'Forbidden.')
  }

  if (url.pathname === '/api/admin/reports/1' && request.method === 'PATCH') {
    return authorized ? json(response, 200, { ...report, status: 'resolved' }) : apiError(response, 403, 'Forbidden.')
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

  if (url.pathname === '/api/recipes/negroni/favorite' && request.method === 'POST') {
    return authorized
      ? json(response, 200, { recipeSlug: 'negroni', favoriteCount: 5, favorited: true, changed: true })
      : apiError(response, 401, 'Unauthorized.')
  }

  if (url.pathname === '/api/recipes/negroni/comments' && request.method === 'POST') {
    return authorized ? json(response, 201, {
      id: 2,
      recipeSlug: 'negroni',
      authorUsername: 'jane_doe',
      parentId: null,
      parentContext: null,
      message: 'New comment',
      moderationStatus: 'visible',
      replyCount: 0,
      depth: 1,
      canReply: true,
      deleted: false,
      createdAt: '2026-07-25T10:00:00+00:00',
      updatedAt: '2026-07-25T10:00:00+00:00'
    }) : apiError(response, 401, 'Unauthorized.')
  }

  if (url.pathname === '/api/comments/1' && request.method === 'PATCH') {
    return authorized ? json(response, 200, { ...commentPayload(), message: 'Updated comment' }) : apiError(response, 401, 'Unauthorized.')
  }

  if (url.pathname === '/api/comments/1' && request.method === 'DELETE') {
    return authorized ? json(response, 200, { ...commentPayload(), deleted: true, message: null, moderationStatus: 'removed' }) : apiError(response, 401, 'Unauthorized.')
  }

  if (url.pathname === '/api/recipes/negroni/comments') {
    return json(response, 200, {
      items: authorized
        ? [{
            id: 1,
            recipeSlug: 'negroni',
            authorUsername: 'jane_doe',
            parentId: null,
            parentContext: null,
            message: 'Authorized note',
            moderationStatus: 'visible',
            replyCount: 0,
            depth: 1,
            canReply: true,
            deleted: false,
            createdAt: '2026-07-25T10:00:00+00:00',
            updatedAt: '2026-07-25T10:00:00+00:00'
          }]
        : [],
      page: 1,
      pageSize: 20,
      totalItems: authorized ? 1 : 0,
      totalPages: authorized ? 1 : 0
    })
  }

  if (url.pathname === '/api/recipes/negroni') {
    return authorized ? json(response, 200, negroni) : apiError(response, 404, 'Not found.')
  }

  if (url.pathname === '/api/recipes/citrus-spritz') {
    return json(response, 200, zeroProofRecipe)
  }

  if (url.pathname === '/api/recipes') {
    const large = url.searchParams.get('q') === 'pagination' || url.searchParams.get('author') === 'pagination_user'
    const items = large
      ? Array.from({ length: 65 }, (_, index) => ({ ...zeroProofRecipe, id: index + 10, slug: `pagination-${index + 1}`, title: `Pagination recipe ${index + 1}` }))
      : url.searchParams.get('q') === 'empty-pagination' || url.searchParams.get('category')?.startsWith('category-') ? [] : authorized ? [negroni, zeroProofRecipe] : [zeroProofRecipe]
    const page = Number(url.searchParams.get('page') || 1)
    const member = items.slice((page - 1) * 30, page * 30)
    if (request.headers.accept !== 'application/ld+json') return json(response, 200, member)
    const last = Math.max(1, Math.ceil(items.length / 30))
    const link = (pageNumber) => {
      const query = new URLSearchParams(url.searchParams)
      query.set('page', String(pageNumber))
      return `${url.pathname}?${query}`
    }
    return json(response, 200, {
      member, totalItems: items.length,
      ...(last > 1 || page > 1 ? { view: {
        first: link(1), last: link(last),
        ...(page > 1 ? { previous: link(page - 1) } : {}),
        ...(page < last ? { next: link(page + 1) } : {})
      } } : {})
    })
  }

  if (url.pathname === '/api/ingredients/gin') return json(response, 200, gin)
  if (url.pathname === '/api/ingredients/ingredient-65') return json(response, 200, { ...gin, slug: 'ingredient-65', name: 'Ingredient 65' })

  if (url.pathname === '/api/categories' || url.pathname === '/api/ingredients') {
    const categories = url.pathname.endsWith('categories')
    const items = [categories ? classics : gin, ...Array.from({ length: 64 }, (_, index) => categories
      ? { ...classics, id: index + 2, slug: `category-${index + 2}`, name: `Category ${index + 2}` }
      : { ...gin, id: index + 2, slug: `ingredient-${index + 2}`, name: `Ingredient ${index + 2}` })]
    return json(response, 200, url.searchParams.get('pagination') === 'false' ? items : items.slice(0, 30))
  }

  return apiError(response, 404, `Unhandled mock endpoint: ${url.pathname}`)
}).listen(3001, '127.0.0.1')

function commentPayload() {
  return {
    id: 1,
    recipeSlug: 'negroni',
    authorUsername: 'jane_doe',
    parentId: null,
    parentContext: null,
    message: 'Authorized note',
    moderationStatus: 'visible',
    replyCount: 0,
    depth: 1,
    canReply: true,
    deleted: false,
    createdAt: '2026-07-25T10:00:00+00:00',
    updatedAt: '2026-07-25T10:00:00+00:00'
  }
}

function json(response, status, body) {
  response.writeHead(status, {
    'Access-Control-Allow-Credentials': 'true',
    'Access-Control-Allow-Headers': 'Authorization, Content-Type, X-CSRF-Protection',
    'Access-Control-Allow-Methods': 'DELETE, GET, PATCH, POST, PUT',
    'Access-Control-Allow-Origin': 'http://127.0.0.1:3000',
    'Content-Type': 'application/json'
  })
  response.end(JSON.stringify(body))
}

function cors(response, status) {
  response.writeHead(status, {
    'Access-Control-Allow-Credentials': 'true',
    'Access-Control-Allow-Headers': 'Authorization, Content-Type, X-CSRF-Protection',
    'Access-Control-Allow-Methods': 'DELETE, GET, PATCH, POST, PUT',
    'Access-Control-Allow-Origin': 'http://127.0.0.1:3000'
  })
  response.end()
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
