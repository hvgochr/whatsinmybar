import type { Category, Ingredient, IngredientUnit, RecipeIngredient, RecipeResource } from '../types/api'

const unitLabels: Record<IngredientUnit, string> = {
  bar_spoon: 'bar spoon',
  cl: 'cl',
  dash: 'dash',
  drop: 'drop',
  l: 'l',
  leaf: 'leaf',
  ml: 'ml',
  oz: 'oz',
  piece: 'piece',
  pinch: 'pinch',
  slice: 'slice',
  sprig: 'sprig',
  tbsp: 'tbsp',
  to_taste: 'to taste',
  tsp: 'tsp',
  wedge: 'wedge'
}

export function categoryName(category: Category | string): string {
  return typeof category === 'string' ? category.split('/').at(-1) ?? category : category.name
}

export function categorySlug(category: Category | string): string {
  return typeof category === 'string' ? category.split('/').at(-1) ?? category : category.slug
}

export function ingredientName(recipeIngredient: RecipeIngredient): string {
  return typeof recipeIngredient.ingredient === 'string'
    ? recipeIngredient.ingredient.split('/').at(-1) ?? recipeIngredient.ingredient
    : recipeIngredient.ingredient.name
}

export function ingredientSlug(ingredient: Ingredient | string): string {
  return typeof ingredient === 'string' ? ingredient.split('/').at(-1) ?? ingredient : ingredient.slug
}

export function formatIngredientAmount(recipeIngredient: RecipeIngredient): string {
  const amount = [trimQuantity(recipeIngredient.quantity), unitLabels[recipeIngredient.unit]]
    .filter(Boolean)
    .join(' ')

  return [amount, ingredientName(recipeIngredient), recipeIngredient.note]
    .filter(Boolean)
    .join(' ')
}

export function formatRecipeMeta(recipe: RecipeResource): string {
  return [
    recipe.difficulty,
    recipe.preparationTimeMinutes ? `${recipe.preparationTimeMinutes} min` : null,
    recipe.servings ? `${recipe.servings} serving${recipe.servings > 1 ? 's' : ''}` : null
  ].filter(Boolean).join(' · ')
}

export function formatPublicDate(date: string | null | undefined): string {
  if (!date) {
    return ''
  }

  return new Intl.DateTimeFormat('en', {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  }).format(new Date(date))
}

export function publicDescription(value: string | null | undefined, fallback: string, maxLength = 155): string {
  const text = value?.trim() || fallback

  return text.length > maxLength ? `${text.slice(0, maxLength - 3).trim()}...` : text
}

export function publicUrl(siteUrl: string, path: string): string {
  return new URL(path, siteUrl.endsWith('/') ? siteUrl : `${siteUrl}/`).toString()
}

export function imageUrl(path: string | null | undefined, apiBaseUrl: string): string | undefined {
  if (!path) {
    return undefined
  }

  if (/^https?:\/\//.test(path)) {
    return path
  }

  if (path.startsWith('/uploads/recipes/')) {
    return `${apiBaseUrl.replace(/\/$/, '')}/recipe-images/${encodeURIComponent(path.slice('/uploads/recipes/'.length))}`
  }

  if (path.startsWith('/uploads/')) {
    if (!/^https?:\/\//.test(apiBaseUrl)) {
      return path
    }

    return new URL(path, apiBaseUrl.endsWith('/') ? apiBaseUrl : `${apiBaseUrl}/`).toString()
  }

  return path
}

function trimQuantity(quantity: string | null): string {
  if (!quantity) {
    return ''
  }

  return quantity.replace(/\.00$/, '').replace(/(\.\d)0$/, '$1')
}
