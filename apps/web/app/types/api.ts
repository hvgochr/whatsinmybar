export type ApiId = number

export type ApiDateTime = string

export type RecipeStatus = 'draft' | 'published' | 'archived'

export type ModerationStatus = 'visible' | 'hidden' | 'pending_review'

export type ReportStatus = 'open' | 'reviewed' | 'dismissed' | 'action_taken'

export type ReportTargetType = 'recipe' | 'comment' | 'user'

export type IngredientUnit =
  | 'ml'
  | 'cl'
  | 'l'
  | 'oz'
  | 'dash'
  | 'bar_spoon'
  | 'tsp'
  | 'tbsp'
  | 'drop'
  | 'piece'
  | 'slice'
  | 'wedge'
  | 'leaf'
  | 'sprig'
  | 'pinch'
  | 'to_taste'

export interface ApiCollection<T> {
  'hydra:member'?: T[]
  'hydra:totalItems'?: number
  member?: T[]
  totalItems?: number
  items?: T[]
  [key: string]: unknown
}

export interface ApiViolation {
  property: string
  message: string
}

export interface ApiErrorBody {
  error?: {
    status?: number
    code?: string
    message?: string
    violations?: ApiViolation[]
  }
  errors?: ApiViolation[]
  message?: string
}

export interface User {
  id: ApiId
  email: string
  username: string
  birthDate: string
  bio: string | null
  avatarPath: string | null
  roles: string[]
  createdAt: ApiDateTime
  updatedAt: ApiDateTime
}

export interface PublicProfile {
  id: ApiId
  username: string
  bio: string | null
  avatarPath: string | null
  createdAt: ApiDateTime
}

export interface AuthTokens {
  token: string
  refresh_token: string
}

export interface RegisterPayload {
  email: string
  username: string
  password: string
  birthDate: string
  bio?: string | null
}

export interface LoginPayload {
  username: string
  password: string
}

export interface UpdateMePayload {
  username?: string
  birthDate?: string
  bio?: string | null
}

export interface PasswordChangePayload {
  currentPassword: string
  newPassword: string
}

export interface Category {
  id?: ApiId
  name: string
  slug: string
  description: string | null
}

export interface Ingredient {
  id?: ApiId
  name: string
  slug: string
  containsAlcohol: boolean
}

export interface RecipeIngredientPayload {
  ingredient: string
  quantity: string | number
  unit: IngredientUnit
  position: number
}

export interface RecipeStepPayload {
  position: number
  instruction: string
}

export interface RecipePayload {
  title: string
  description?: string | null
  difficulty?: string | null
  preparationTimeMinutes?: number | null
  servings?: number | null
  containsAlcoholOverride?: boolean | null
  steps?: RecipeStepPayload[]
  ingredients?: RecipeIngredientPayload[]
  categories?: string[]
}

export interface RecipeResource {
  id?: ApiId
  title: string
  slug: string
  description: string | null
  difficulty: string | null
  preparationTimeMinutes: number | null
  servings: number | null
  containsAlcohol: boolean
  imagePath: string | null
  status: RecipeStatus
  moderationStatus: ModerationStatus
  favoriteCount: number
  publishedAt: ApiDateTime | null
  createdAt?: ApiDateTime
  updatedAt?: ApiDateTime
  [key: string]: unknown
}

export interface RecipeWorkflow {
  id: ApiId
  title: string
  slug: string
  status: RecipeStatus
  moderationStatus: ModerationStatus
  publishedAt: ApiDateTime | null
  deleted: boolean
  deletedAt: ApiDateTime | null
  updatedAt: ApiDateTime
}

export interface RecipeSearchParams {
  q?: string
  category?: string
  ingredient?: string
  alcohol?: 'with' | 'without'
  author?: string
  minFavorites?: number
  publishedAfter?: string
  publishedBefore?: string
  sort?: 'popular' | 'newest' | 'oldest'
  page?: number
}

export interface FavoriteState {
  recipeSlug: string
  favoriteCount: number
  favorited: boolean
  changed: boolean
}

export interface Comment {
  id: ApiId
  recipeSlug: string
  authorUsername: string
  parentId: ApiId | null
  message: string | null
  moderationStatus: ModerationStatus
  replyCount: number
  deleted: boolean
  createdAt: ApiDateTime
  updatedAt: ApiDateTime
}

export interface CommentPayload {
  message: string
  parentId?: ApiId | null
}

export interface Report {
  id: ApiId
  reporterUsername: string
  targetType: ReportTargetType
  targetId: ApiId
  reason: string
  message: string | null
  status: ReportStatus
  reviewedByUsername: string | null
  reviewedAt: ApiDateTime | null
  createdAt: ApiDateTime
  updatedAt: ApiDateTime
}

export interface ReportPayload {
  targetType: ReportTargetType
  targetId: ApiId
  reason: string
  message?: string | null
}

export interface AdminList<T> {
  items: T[]
}
