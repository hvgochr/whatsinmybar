import type {
  AdminRecipe,
  AdminUser,
  ApiCollection,
  ApiErrorBody,
  ApiViolation,
  AuthTokens,
  Category,
  Comment,
  CommentPayload,
  FavoriteState,
  Ingredient,
  ItemList,
  LoginPayload,
  ModerationStatus,
  PasswordChangePayload,
  PaginatedList,
  PaginationParams,
  PublicProfile,
  RecipeAggregatePayload,
  RecipeImageState,
  RecipeResource,
  RecipeSearchParams,
  RecipeWorkflow,
  RegisterPayload,
  Report,
  ReportPayload,
  UpdateMePayload,
  User
} from '../types/api'

type FetchLike = <T>(request: string, options?: Record<string, unknown>) => Promise<T>

type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

type QueryValue = string | number | boolean | null | undefined

export interface ApiRequestOptions {
  publicFallback?: boolean
  auth?: boolean
  body?: unknown
  headers?: HeadersInit
  method?: HttpMethod
  query?: Record<string, QueryValue>
  [key: string]: unknown
}

export interface ApiClientConfig {
  baseURL: string
  fetch: FetchLike
  getAccessToken: () => string | null
  setAccessToken: (token: string | null) => void
  clearTokens: () => void
  setCurrentUser?: (user: User) => void
  onRefreshUnavailable?: (error: ApiRequestError) => void
  getSessionRevision?: () => number
}

export class ApiRequestError extends Error {
  readonly code: string
  readonly payload: ApiErrorBody | null
  readonly status: number
  readonly violations: ApiViolation[]
  readonly retryAfterMs: number | null

  constructor(message: string, status: number, code: string, payload: ApiErrorBody | null = null, violations: ApiViolation[] = [], retryAfterMs: number | null = null) {
    super(message)
    this.name = 'ApiRequestError'
    this.status = status
    this.code = code
    this.payload = payload
    this.violations = violations
    this.retryAfterMs = retryAfterMs
  }
}

export interface ApiClient {
  account: {
    avatar: (file: Blob) => Promise<User>
    changePassword: (payload: PasswordChangePayload) => Promise<{ changed: boolean }>
    me: () => Promise<User>
    ownedRecipes: (params?: PaginationParams) => Promise<PaginatedList<RecipeResource>>
    savedRecipes: (params?: PaginationParams) => Promise<PaginatedList<RecipeResource>>
    update: (payload: UpdateMePayload) => Promise<User>
  }
  admin: {
    categories: {
      create: (payload: Partial<Category>) => Promise<Category>
      list: (params?: PaginationParams) => Promise<PaginatedList<Category>>
      update: (slug: string, payload: Partial<Category>) => Promise<Category>
    }
    ingredients: {
      create: (payload: Partial<Ingredient>) => Promise<Ingredient>
      list: (params?: PaginationParams) => Promise<PaginatedList<Ingredient>>
      update: (slug: string, payload: Partial<Ingredient>) => Promise<Ingredient>
    }
    recipes: {
      list: (params?: PaginationParams) => Promise<PaginatedList<AdminRecipe>>
      update: (slug: string, payload: Partial<AdminRecipe>) => Promise<AdminRecipe>
    }
    reports: {
      list: (params?: PaginationParams) => Promise<PaginatedList<Report>>
      update: (id: number, payload: Partial<Report> & { moderationStatus?: ModerationStatus }) => Promise<Report>
    }
    users: {
      list: (params?: PaginationParams) => Promise<PaginatedList<AdminUser>>
      update: (id: number, payload: Partial<AdminUser>) => Promise<AdminUser>
    }
  }
  auth: {
    login: (payload: LoginPayload) => Promise<AuthTokens>
    logout: () => Promise<void>
    refresh: () => Promise<AuthTokens>
    register: (payload: RegisterPayload) => Promise<User>
  }
  categories: {
    get: (slug: string) => Promise<Category>
    list: () => Promise<ApiCollection<Category>>
  }
  comments: {
    create: (recipeSlug: string, payload: CommentPayload) => Promise<Comment>
    delete: (id: number) => Promise<Comment>
    list: (recipeSlug: string, params?: PaginationParams) => Promise<PaginatedList<Comment>>
    update: (id: number, payload: Partial<CommentPayload> & { moderationStatus?: string }) => Promise<Comment>
  }
  favorites: {
    add: (recipeSlug: string) => Promise<FavoriteState>
    remove: (recipeSlug: string) => Promise<FavoriteState>
  }
  ingredients: {
    get: (slug: string) => Promise<Ingredient>
    list: () => Promise<ApiCollection<Ingredient>>
  }
  profiles: {
    get: (username: string) => Promise<PublicProfile>
  }
  recipes: {
    archive: (slug: string) => Promise<RecipeWorkflow>
    create: (payload: RecipeAggregatePayload) => Promise<RecipeResource>
    delete: (slug: string) => Promise<RecipeResource>
    get: (slug: string) => Promise<RecipeResource>
    imageFile: (path: string, signal?: AbortSignal) => Promise<Blob>
    image: (slug: string, file: Blob) => Promise<RecipeImageState>
    list: (params?: RecipeSearchParams) => Promise<ApiCollection<RecipeResource>>
    publish: (slug: string) => Promise<RecipeWorkflow>
    removeImage: (slug: string) => Promise<RecipeImageState>
    update: (slug: string, payload: RecipeAggregatePayload) => Promise<RecipeResource>
  }
  reports: {
    create: (payload: ReportPayload) => Promise<Report>
  }
  request: <T>(path: string, options?: ApiRequestOptions) => Promise<T>
  setTokens: (tokens: AuthTokens) => void
  clearTokens: () => void
}

export function createApiClient(config: ApiClientConfig): ApiClient {
  let refreshPromise: Promise<AuthTokens> | null = null
  let refreshGeneration = -1
  let generation = 0
  let refreshFailure: { error: ApiRequestError, until: number } | null = null

  const clearTokens = (): void => {
    generation++
    refreshFailure = null
    config.clearTokens()
  }
  const setTokens = (tokens: AuthTokens): void => {
    generation++
    refreshFailure = null
    config.setAccessToken(tokens.token)
  }
  const changedError = () => new ApiRequestError('Session changed. Please retry.', 0, 'session_changed')

  const refreshTokens = async (): Promise<AuthTokens> => {
    if (refreshFailure && Date.now() < refreshFailure.until) throw refreshFailure.error
    if (refreshPromise && refreshGeneration === generation) return refreshPromise
    const started = generation
    refreshGeneration = started
    const pending = request<AuthTokens>('/auth/refresh', {
      auth: false,
      headers: csrfProtectionHeaders(),
      method: 'POST',
      timeout: 3000
    })
      .then(async (tokens) => {
        if (started !== generation) throw changedError()
        config.setAccessToken(tokens.token)
        if (config.setCurrentUser) {
          // The shared cookie may now identify a different account (another tab).
          // Verify the viewer before completing automatic refresh or retrying work.
          const user = await request<User>('/me', {}, false)
          if (started !== generation) throw changedError()
          config.setCurrentUser(user)
        }
        refreshFailure = null
        return tokens
      })
      .catch((error: unknown) => {
        const normalized = normalizeApiError(error)
        if (started === generation) {
          if (normalized.status === 401) clearTokens()
          else config.onRefreshUnavailable?.(normalized)
          // Bound repeated attempts during an outage; explicit later retry is possible.
          refreshFailure = { error: normalized, until: Date.now() + (normalized.retryAfterMs ?? (normalized.status === 429 ? 30_000 : 5000)) }
        }
        throw normalized
      })
      .finally(() => {
        if (refreshPromise === pending) refreshPromise = null
      })

    refreshPromise = pending
    return pending
  }

  const request = async <T>(path: string, options: ApiRequestOptions = {}, canRefresh = true): Promise<T> => {
    const started = generation
    const revision = config.getSessionRevision?.()
    const sentToken = config.getAccessToken()
    const assertSameSession = () => {
      if (started !== generation || revision !== config.getSessionRevision?.()) throw changedError()
    }
    try {
      const result = await config.fetch<T>(path, fetchOptions(config, options))
      if (options.auth !== false) assertSameSession()
      return result
    } catch (error: unknown) {
      const normalizedError = normalizeApiError(error)
      if (options.auth !== false) assertSameSession()

      if (canRefresh && options.auth !== false && normalizedError.status === 401) {
        try {
          // A new token is not a verified viewer until the shared refresh's /me
          // completes. This also covers a slower 401 from another request.
          if (refreshPromise && refreshGeneration === generation) await refreshPromise
          else if (!config.getAccessToken() || config.getAccessToken() === sentToken) await refreshTokens()
        } catch (refreshError) {
          const failure = normalizeApiError(refreshError)
          if (options.publicFallback && (failure.status === 401 || isTemporaryError(failure))) {
            return request<T>(path, { ...options, auth: false }, false)
          }
          throw failure
        }
        // Keep the original request's identity boundary across every retry.
        // Refresh may have successfully switched A to B without a notification.
        assertSameSession()
        return request<T>(path, options, false)
      }
      if (!canRefresh && options.auth !== false && normalizedError.status === 401) clearTokens()
      throw normalizedError
    }
  }

  const upload = <T>(path: string, field: string, file: Blob): Promise<T> => {
    const body = new FormData()
    body.append(field, file)

    return request<T>(path, {
      body,
      method: 'POST'
    })
  }

  return {
    account: {
      avatar: (file) => upload<User>('/me/avatar', 'avatar', file),
      changePassword: async (payload) => {
        const result = await request<{ changed: boolean }>('/me/password', { body: payload, method: 'PATCH' })
        clearTokens()
        return result
      },
      me: () => request<User>('/me'),
      ownedRecipes: (params = {}) => request<PaginatedList<RecipeResource>>('/me/recipes', { query: paginationQuery(params) }),
      savedRecipes: (params = {}) => request<PaginatedList<RecipeResource>>('/me/saved-recipes', { query: paginationQuery(params) }),
      update: (payload) => request<User>('/me', { body: payload, method: 'PATCH' })
    },
    admin: {
      categories: {
        create: (payload) => request<Category>('/admin/categories', { body: payload, method: 'POST' }),
        list: (params = {}) => request<PaginatedList<Category>>('/admin/categories', { query: paginationQuery(params) }),
        update: (slug, payload) => request<Category>(`/admin/categories/${encodeURIComponent(slug)}`, { body: payload, method: 'PATCH' })
      },
      ingredients: {
        create: (payload) => request<Ingredient>('/admin/ingredients', { body: payload, method: 'POST' }),
        list: (params = {}) => request<PaginatedList<Ingredient>>('/admin/ingredients', { query: paginationQuery(params) }),
        update: (slug, payload) => request<Ingredient>(`/admin/ingredients/${encodeURIComponent(slug)}`, { body: payload, method: 'PATCH' })
      },
      recipes: {
        list: (params = {}) => request<PaginatedList<AdminRecipe>>('/admin/recipes', { query: paginationQuery(params) }),
        update: (slug, payload) => request<AdminRecipe>(`/admin/recipes/${encodeURIComponent(slug)}`, { body: payload, method: 'PATCH' })
      },
      reports: {
        list: (params = {}) => request<PaginatedList<Report>>('/admin/reports', { query: paginationQuery(params) }),
        update: (id, payload) => request<Report>(`/admin/reports/${id}`, { body: payload, method: 'PATCH' })
      },
      users: {
        list: (params = {}) => request<PaginatedList<AdminUser>>('/admin/users', { query: paginationQuery(params) }),
        update: (id, payload) => request<AdminUser>(`/admin/users/${id}`, { body: payload, method: 'PATCH' })
      }
    },
    auth: {
      login: async (payload) => {
        clearTokens()
        const started = generation
        const tokens = await request<AuthTokens>('/auth/login', { auth: false, body: payload, method: 'POST' })
        if (started !== generation) throw changedError()
        setTokens(tokens)
        return tokens
      },
      logout: async () => {
        await request('/auth/logout', {
          auth: false,
          headers: csrfProtectionHeaders(),
          method: 'POST'
        })
        clearTokens()
      },
      refresh: refreshTokens,
      register: (payload) => request<User>('/auth/register', { auth: false, body: payload, method: 'POST' })
    },
    categories: {
      get: (slug) => request<Category>(`/categories/${encodeURIComponent(slug)}`, { auth: false }),
      list: () => request<ApiCollection<Category>>('/categories', { auth: false, query: { pagination: false } })
    },
    comments: {
      create: (recipeSlug, payload) => request<Comment>(`/recipes/${encodeURIComponent(recipeSlug)}/comments`, { body: payload, method: 'POST' }),
      delete: (id) => request<Comment>(`/comments/${id}`, { method: 'DELETE' }),
      list: (recipeSlug, params = {}) => request<PaginatedList<Comment>>(`/recipes/${encodeURIComponent(recipeSlug)}/comments`, { publicFallback: true, query: paginationQuery(params) }),
      update: (id, payload) => request<Comment>(`/comments/${id}`, { body: payload, method: 'PATCH' })
    },
    favorites: {
      add: (recipeSlug) => request<FavoriteState>(`/recipes/${encodeURIComponent(recipeSlug)}/favorite`, { method: 'POST' }),
      remove: (recipeSlug) => request<FavoriteState>(`/recipes/${encodeURIComponent(recipeSlug)}/favorite`, { method: 'DELETE' })
    },
    ingredients: {
      get: (slug) => request<Ingredient>(`/ingredients/${encodeURIComponent(slug)}`, { auth: false }),
      list: () => request<ApiCollection<Ingredient>>('/ingredients', { auth: false, query: { pagination: false } })
    },
    profiles: {
      get: (username) => request<PublicProfile>(`/users/${encodeURIComponent(username)}`, { auth: false })
    },
    recipes: {
      archive: (slug) => request<RecipeWorkflow>(`/recipes/${encodeURIComponent(slug)}/archive`, { method: 'POST' }),
      create: (payload) => request<RecipeResource>('/recipes/aggregate', { body: payload, method: 'POST' }),
      delete: (slug) => request<RecipeResource>(`/recipes/${encodeURIComponent(slug)}`, { method: 'DELETE' }),
      get: (slug) => request<RecipeResource>(`/recipes/${encodeURIComponent(slug)}`, { publicFallback: true }),
      imageFile: (path, signal) => {
        const filename = /^\/uploads\/recipes\/([a-f0-9]{32}\.(?:jpg|png|webp))$/.exec(path)?.[1]
        if (!filename) {
          return Promise.reject(new Error('Invalid recipe image path.'))
        }
        return request<Blob>(`/recipe-images/${filename}`, { publicFallback: true, responseType: 'blob', cache: 'no-store', signal })
      },
      image: (slug, file) => upload<RecipeImageState>(`/recipes/${encodeURIComponent(slug)}/image`, 'image', file),
      list: (params = {}) => request<ApiCollection<RecipeResource>>('/recipes', { publicFallback: true, headers: { Accept: 'application/ld+json' }, query: recipeSearchQuery(params) }),
      publish: (slug) => request<RecipeWorkflow>(`/recipes/${encodeURIComponent(slug)}/publish`, { method: 'POST' }),
      removeImage: (slug) => request<RecipeImageState>(`/recipes/${encodeURIComponent(slug)}/image`, { method: 'DELETE' }),
      update: (slug, payload) => request<RecipeResource>(`/recipes/${encodeURIComponent(slug)}/aggregate`, { body: payload, method: 'PUT' })
    },
    reports: {
      create: (payload) => request<Report>('/reports', { body: payload, method: 'POST' })
    },
    request,
    setTokens,
    clearTokens
  }
}

export function normalizeApiError(error: unknown): ApiRequestError {
  if (error instanceof ApiRequestError) {
    return error
  }

  const fetchError = (error ?? {}) as {
    name?: string
    cause?: { name?: string }
    data?: ApiErrorBody
    message?: string
    response?: {
      headers?: Headers
      status?: number
      statusText?: string
      _data?: ApiErrorBody
    }
    status?: number
    statusCode?: number
    statusMessage?: string
  }
  const payload = fetchError.data ?? fetchError.response?._data ?? null
  const apiError = payload?.error
  const status = apiError?.status ?? fetchError.statusCode ?? fetchError.status ?? fetchError.response?.status ?? 0
  const timedOut = fetchError.name === 'TimeoutError' || fetchError.cause?.name === 'TimeoutError'
  const code = apiError?.code ?? (timedOut ? 'timeout' : codeFromStatus(status))
  const message = apiError?.message ?? payload?.message ?? fetchError.statusMessage ?? fetchError.message ?? 'Request failed.'
  const violations = apiError?.violations ?? payload?.errors ?? []

  const retryAfter = fetchError.response?.headers?.get('Retry-After')
  const delay = retryAfter ? (/^\d+$/.test(retryAfter) ? Number(retryAfter) * 1000 : Date.parse(retryAfter) - Date.now()) : NaN
  return new ApiRequestError(message, status, code, payload, violations, Number.isFinite(delay) ? Math.max(1000, delay) : null)
}

function paginationQuery(params: PaginationParams): Record<string, QueryValue> {
  return {
    page: params.page,
    pageSize: params.pageSize
  }
}

function fetchOptions(config: ApiClientConfig, options: ApiRequestOptions): Record<string, unknown> {
  const { auth = true, publicFallback: _publicFallback, headers, query, ...fetchOptions } = options
  const resolvedHeaders = new Headers(headers)
  const accessToken = config.getAccessToken()
  const timeout = typeof options.timeout === 'number' ? options.timeout : options.body instanceof FormData ? 30_000 : 8000
  // ofetch 1.x ignores timeout when the caller supplies a signal (e.g. images).
  const signal = options.signal instanceof AbortSignal ? AbortSignal.any([options.signal, AbortSignal.timeout(timeout)]) : undefined

  if (auth && accessToken) {
    resolvedHeaders.set('Authorization', `Bearer ${accessToken}`)
  }

  return {
    timeout,
    retry: 0,
    cache: 'no-store',
    ...fetchOptions,
    ...(signal ? { signal } : {}),
    baseURL: config.baseURL,
    credentials: 'include',
    headers: resolvedHeaders,
    query: query ? cleanQuery(query) : undefined
  }
}

function csrfProtectionHeaders(): HeadersInit {
  return { 'X-CSRF-Protection': '1' }
}

function recipeSearchQuery(params: RecipeSearchParams): Record<string, string | number | boolean> {
  return cleanQuery({
    ...params,
    alcohol: alcoholQueryValue(params.alcohol)
  })
}

function alcoholQueryValue(value: RecipeSearchParams['alcohol']): boolean | undefined {
  if (value === 'with') {
    return true
  }

  if (value === 'without') {
    return false
  }

  return undefined
}

function cleanQuery<T extends object>(query: T): Record<string, string | number | boolean> {
  return Object.fromEntries(
    Object.entries(query as Record<string, QueryValue>).filter(([, value]) => value !== null && value !== undefined && value !== '')
  ) as Record<string, string | number | boolean>
}

function codeFromStatus(status: number): string {
  if (status === 0) {
    return 'network_error'
  }

  return `http_${status}`
}

export function isTemporaryError(error: ApiRequestError): boolean {
  return (error.status === 0 && error.code !== 'session_changed') || error.status === 408 || error.status === 429 || error.status >= 500
}
