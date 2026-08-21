export type ApiId = number

export type ApiDateTime = string

export type RecipeStatus = 'draft' | 'published' | 'archived'

export type ModerationStatus = 'visible' | 'hidden' | 'pending_review' | 'removed'

export type ReportStatus = 'open' | 'reviewing' | 'resolved' | 'rejected'

export type ReportTargetType = 'recipe' | 'comment' | 'user'

export type ReportReason = 'spam' | 'abuse' | 'illegal_content' | 'wrong_alcohol_classification' | 'copyright' | 'other'

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
  'hydra:view'?: ApiCollectionView
  member?: T[]
  totalItems?: number
  view?: ApiCollectionView
  items?: T[]
  [key: string]: unknown
}

export interface ApiCollectionView {
  '@id'?: string
  '@type'?: string
  'hydra:first'?: string
  'hydra:last'?: string
  'hydra:next'?: string
  'hydra:previous'?: string
  first?: string
  last?: string
  next?: string
  previous?: string
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

export interface AdminUser extends User {
  deleted: boolean
  deletedAt: ApiDateTime | null
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
}

export interface RegisterPayload {
  email: string
  username: string
  password: string
  birthDate: string
  bio?: string | null
}

export interface LoginPayload {
  email: string
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
  createdAt?: ApiDateTime
  updatedAt?: ApiDateTime
}

export interface Ingredient {
  id?: ApiId
  name: string
  slug: string
  containsAlcohol: boolean
  createdAt?: ApiDateTime
  updatedAt?: ApiDateTime
}

export interface RecipeStep {
  id?: ApiId
  position: number
  instruction: string
}

export interface RecipeIngredient {
  id?: ApiId
  ingredient: Ingredient | string
  quantity: string | null
  unit: IngredientUnit
  position: number
  note?: string | null
}

export interface RecipeIngredientPayload {
  recipe?: string
  ingredient: string
  quantity: string | number
  unit: IngredientUnit
  position: number
  note?: string | null
}

export interface RecipeStepPayload {
  recipe?: string
  position: number
  instruction: string
}

export interface RecipePayload {
  title: string
  description?: string | null
  difficulty?: string | null
  preparationTimeMinutes?: number | null
  servings?: number | null
  status?: RecipeStatus
  steps?: RecipeStepPayload[]
  ingredients?: RecipeIngredientPayload[]
  categories?: string[]
}

export interface RecipeResource {
  id?: ApiId
  authorUsername?: string | null
  categories?: Array<Category | string>
  title: string
  slug: string
  description: string | null
  difficulty: string | null
  preparationTimeMinutes: number | null
  servings: number | null
  containsAlcohol: boolean
  containsAlcoholComputed?: boolean
  containsAlcoholOverride?: boolean | null
  imagePath: string | null
  recipeIngredients?: RecipeIngredient[]
  steps?: RecipeStep[]
  status: RecipeStatus
  moderationStatus: ModerationStatus
  favoriteCount: number
  favorited: boolean
  publishedAt: ApiDateTime | null
  createdAt?: ApiDateTime
  updatedAt?: ApiDateTime
  [key: string]: unknown
}

export interface AdminRecipe {
  id: ApiId
  title: string
  slug: string
  authorUsername: string | null
  status: RecipeStatus
  moderationStatus: ModerationStatus
  containsAlcohol: boolean
  containsAlcoholOverride?: boolean | null
  favoriteCount: number
  deleted: boolean
  deletedAt: ApiDateTime | null
  publishedAt: ApiDateTime | null
  createdAt?: ApiDateTime
  updatedAt?: ApiDateTime
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

export interface RecipeImageState {
  recipeSlug: string
  imagePath: string | null
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
  reason: ReportReason
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
  reason: ReportReason
  message?: string | null
}

export interface ItemList<T> {
  items: T[]
}

export interface PaginatedList<T> extends ItemList<T> {
  page: number
  pageSize: number
  totalItems: number
  totalPages: number
}

export interface PaginationParams {
  page?: number
  pageSize?: number
}
