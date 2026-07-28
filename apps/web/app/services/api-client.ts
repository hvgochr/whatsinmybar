import type {
  AdminList,
  ApiCollection,
  ApiErrorBody,
  ApiViolation,
  AuthTokens,
  Category,
  Comment,
  CommentPayload,
  FavoriteState,
  Ingredient,
  LoginPayload,
  PasswordChangePayload,
  PublicProfile,
  RecipePayload,
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

type HttpMethod = 'GET' | 'POST' | 'PATCH' | 'DELETE'

type QueryValue = string | number | boolean | null | undefined

export interface ApiRequestOptions {
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
  getRefreshToken: () => string | null
  setRefreshToken: (token: string | null) => void
  clearTokens: () => void
}

export class ApiRequestError extends Error {
  readonly code: string
  readonly payload: ApiErrorBody | null
  readonly status: number
  readonly violations: ApiViolation[]

  constructor(message: string, status: number, code: string, payload: ApiErrorBody | null = null, violations: ApiViolation[] = []) {
    super(message)
    this.name = 'ApiRequestError'
    this.status = status
    this.code = code
    this.payload = payload
    this.violations = violations
  }
}

export interface ApiClient {
  account: {
    avatar: (file: Blob) => Promise<User>
    changePassword: (payload: PasswordChangePayload) => Promise<{ changed: boolean }>
    me: () => Promise<User>
    update: (payload: UpdateMePayload) => Promise<User>
  }
  admin: {
    categories: {
      create: (payload: Partial<Category>) => Promise<Category>
      list: () => Promise<AdminList<Category>>
      update: (slug: string, payload: Partial<Category>) => Promise<Category>
    }
    ingredients: {
      create: (payload: Partial<Ingredient>) => Promise<Ingredient>
      list: () => Promise<AdminList<Ingredient>>
      update: (slug: string, payload: Partial<Ingredient>) => Promise<Ingredient>
    }
    recipes: {
      list: () => Promise<AdminList<RecipeResource>>
      update: (slug: string, payload: Partial<RecipeWorkflow>) => Promise<RecipeWorkflow>
    }
    reports: {
      list: () => Promise<AdminList<Report>>
      update: (id: number, payload: Partial<Report>) => Promise<Report>
    }
    users: {
      list: () => Promise<AdminList<User>>
      update: (id: number, payload: Partial<User> & { deleted?: boolean }) => Promise<User>
    }
  }
  auth: {
    login: (payload: LoginPayload) => Promise<AuthTokens>
    refresh: (refreshToken: string) => Promise<AuthTokens>
    register: (payload: RegisterPayload) => Promise<User>
  }
  categories: {
    get: (slug: string) => Promise<Category>
    list: () => Promise<ApiCollection<Category>>
  }
  comments: {
    create: (recipeSlug: string, payload: CommentPayload) => Promise<Comment>
    delete: (id: number) => Promise<Comment>
    list: (recipeSlug: string) => Promise<AdminList<Comment>>
    update: (id: number, payload: Partial<CommentPayload> & { moderationStatus?: string }) => Promise<Comment>
  }
  favorites: {
    add: (recipeSlug: string) => Promise<FavoriteState>
    remove: (recipeSlug: string) => Promise<FavoriteState>
  }
  ingredients: {
    list: () => Promise<ApiCollection<Ingredient>>
  }
  profiles: {
    get: (username: string) => Promise<PublicProfile>
  }
  recipes: {
    archive: (slug: string) => Promise<RecipeWorkflow>
    create: (payload: RecipePayload) => Promise<RecipeResource>
    delete: (slug: string) => Promise<RecipeResource>
    get: (slug: string) => Promise<RecipeResource>
    image: (slug: string, file: Blob) => Promise<RecipeResource>
    list: (params?: RecipeSearchParams) => Promise<ApiCollection<RecipeResource>>
    publish: (slug: string) => Promise<RecipeWorkflow>
    removeImage: (slug: string) => Promise<RecipeResource>
    update: (slug: string, payload: Partial<RecipePayload>) => Promise<RecipeResource>
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

  const setTokens = (tokens: AuthTokens): void => {
    config.setAccessToken(tokens.token)
    config.setRefreshToken(tokens.refresh_token)
  }

  const refreshTokens = async (): Promise<AuthTokens> => {
    const refreshToken = config.getRefreshToken()

    if (!refreshToken) {
      throw new ApiRequestError('Authentication required.', 401, 'unauthorized')
    }

    refreshPromise ??= request<AuthTokens>('/auth/refresh', {
      auth: false,
      body: { refresh_token: refreshToken },
      method: 'POST'
    })
      .then((tokens) => {
        setTokens(tokens)
        return tokens
      })
      .catch((error: unknown) => {
        config.clearTokens()
        throw normalizeApiError(error)
      })
      .finally(() => {
        refreshPromise = null
      })

    return refreshPromise
  }

  const request = async <T>(path: string, options: ApiRequestOptions = {}, canRefresh = true): Promise<T> => {
    try {
      return await config.fetch<T>(path, fetchOptions(config, options))
    } catch (error: unknown) {
      const normalizedError = normalizeApiError(error)

      if (
        canRefresh
        && options.auth !== false
        && normalizedError.status === 401
        && !path.endsWith('/auth/refresh')
        && config.getRefreshToken()
      ) {
        await refreshTokens()

        return request<T>(path, options, false)
      }

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
      changePassword: (payload) => request<{ changed: boolean }>('/me/password', { body: payload, method: 'PATCH' }),
      me: () => request<User>('/me'),
      update: (payload) => request<User>('/me', { body: payload, method: 'PATCH' })
    },
    admin: {
      categories: {
        create: (payload) => request<Category>('/admin/categories', { body: payload, method: 'POST' }),
        list: () => request<AdminList<Category>>('/admin/categories'),
        update: (slug, payload) => request<Category>(`/admin/categories/${encodeURIComponent(slug)}`, { body: payload, method: 'PATCH' })
      },
      ingredients: {
        create: (payload) => request<Ingredient>('/admin/ingredients', { body: payload, method: 'POST' }),
        list: () => request<AdminList<Ingredient>>('/admin/ingredients'),
        update: (slug, payload) => request<Ingredient>(`/admin/ingredients/${encodeURIComponent(slug)}`, { body: payload, method: 'PATCH' })
      },
      recipes: {
        list: () => request<AdminList<RecipeResource>>('/admin/recipes'),
        update: (slug, payload) => request<RecipeWorkflow>(`/admin/recipes/${encodeURIComponent(slug)}`, { body: payload, method: 'PATCH' })
      },
      reports: {
        list: () => request<AdminList<Report>>('/admin/reports'),
        update: (id, payload) => request<Report>(`/admin/reports/${id}`, { body: payload, method: 'PATCH' })
      },
      users: {
        list: () => request<AdminList<User>>('/admin/users'),
        update: (id, payload) => request<User>(`/admin/users/${id}`, { body: payload, method: 'PATCH' })
      }
    },
    auth: {
      login: (payload) => request<AuthTokens>('/auth/login', { auth: false, body: payload, method: 'POST' }),
      refresh: (refreshToken) => request<AuthTokens>('/auth/refresh', {
        auth: false,
        body: { refresh_token: refreshToken },
        method: 'POST'
      }),
      register: (payload) => request<User>('/auth/register', { auth: false, body: payload, method: 'POST' })
    },
    categories: {
      get: (slug) => request<Category>(`/categories/${encodeURIComponent(slug)}`, { auth: false }),
      list: () => request<ApiCollection<Category>>('/categories', { auth: false, query: { pagination: false } })
    },
    comments: {
      create: (recipeSlug, payload) => request<Comment>(`/recipes/${encodeURIComponent(recipeSlug)}/comments`, { body: payload, method: 'POST' }),
      delete: (id) => request<Comment>(`/comments/${id}`, { method: 'DELETE' }),
      list: (recipeSlug) => request<AdminList<Comment>>(`/recipes/${encodeURIComponent(recipeSlug)}/comments`, { auth: false }),
      update: (id, payload) => request<Comment>(`/comments/${id}`, { body: payload, method: 'PATCH' })
    },
    favorites: {
      add: (recipeSlug) => request<FavoriteState>(`/recipes/${encodeURIComponent(recipeSlug)}/favorite`, { method: 'POST' }),
      remove: (recipeSlug) => request<FavoriteState>(`/recipes/${encodeURIComponent(recipeSlug)}/favorite`, { method: 'DELETE' })
    },
    ingredients: {
      list: () => request<ApiCollection<Ingredient>>('/ingredients', { auth: false, query: { pagination: false } })
    },
    profiles: {
      get: (username) => request<PublicProfile>(`/users/${encodeURIComponent(username)}`, { auth: false })
    },
    recipes: {
      archive: (slug) => request<RecipeWorkflow>(`/recipes/${encodeURIComponent(slug)}/archive`, { method: 'POST' }),
      create: (payload) => request<RecipeResource>('/recipes', { body: payload, method: 'POST' }),
      delete: (slug) => request<RecipeResource>(`/recipes/${encodeURIComponent(slug)}`, { method: 'DELETE' }),
      get: (slug) => request<RecipeResource>(`/recipes/${encodeURIComponent(slug)}`),
      image: (slug, file) => upload<RecipeResource>(`/recipes/${encodeURIComponent(slug)}/image`, 'image', file),
      list: (params = {}) => request<ApiCollection<RecipeResource>>('/recipes', { query: cleanQuery(params) }),
      publish: (slug) => request<RecipeWorkflow>(`/recipes/${encodeURIComponent(slug)}/publish`, { method: 'POST' }),
      removeImage: (slug) => request<RecipeResource>(`/recipes/${encodeURIComponent(slug)}/image`, { method: 'DELETE' }),
      update: (slug, payload) => request<RecipeResource>(`/recipes/${encodeURIComponent(slug)}`, { body: payload, method: 'PATCH' })
    },
    reports: {
      create: (payload) => request<Report>('/reports', { body: payload, method: 'POST' })
    },
    request,
    setTokens,
    clearTokens: config.clearTokens
  }
}

export function normalizeApiError(error: unknown): ApiRequestError {
  if (error instanceof ApiRequestError) {
    return error
  }

  const fetchError = error as {
    data?: ApiErrorBody
    message?: string
    response?: {
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
  const code = apiError?.code ?? codeFromStatus(status)
  const message = apiError?.message ?? payload?.message ?? fetchError.statusMessage ?? fetchError.message ?? 'API request failed.'
  const violations = apiError?.violations ?? payload?.errors ?? []

  return new ApiRequestError(message, status, code, payload, violations)
}

function fetchOptions(config: ApiClientConfig, options: ApiRequestOptions): Record<string, unknown> {
  const { auth = true, headers, query, ...fetchOptions } = options
  const resolvedHeaders = new Headers(headers)
  const accessToken = config.getAccessToken()

  if (auth && accessToken) {
    resolvedHeaders.set('Authorization', `Bearer ${accessToken}`)
  }

  return {
    ...fetchOptions,
    baseURL: config.baseURL,
    headers: resolvedHeaders,
    query: query ? cleanQuery(query) : undefined
  }
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
