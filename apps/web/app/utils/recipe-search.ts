import type { LocationQuery } from 'vue-router'
import type { RecipeSearchParams } from '../types/api'
import { pageFromQuery } from './pagination'
import { firstQueryValue, optionalQueryValue } from './route-query'

export interface RecipeSearchState extends RecipeSearchParams {
  page: number
}

export type RecipeSearchSort = NonNullable<RecipeSearchParams['sort']>

const defaultSort: RecipeSearchSort = 'newest'

export function recipeSearchStateFromQuery(query: LocationQuery): RecipeSearchState {
  return {
    alcohol: alcoholValue(firstQueryValue(query, 'alcohol')),
    author: textValue(firstQueryValue(query, 'author')),
    category: textValue(firstQueryValue(query, 'category')),
    ingredient: textValue(firstQueryValue(query, 'ingredient')),
    minFavorites: positiveNumberValue(firstQueryValue(query, 'minFavorites')),
    page: pageFromQuery(query),
    publishedAfter: textValue(firstQueryValue(query, 'publishedAfter')),
    publishedBefore: textValue(firstQueryValue(query, 'publishedBefore')),
    q: textValue(firstQueryValue(query, 'q')),
    sort: sortValue(firstQueryValue(query, 'sort')) ?? defaultSort
  }
}

export function recipeSearchQueryFromForm(form: FormData): Record<string, string | undefined> {
  return cleanRecipeSearchQuery({
    alcohol: optionalQueryValue(form.get('alcohol')),
    author: optionalQueryValue(form.get('author')),
    category: optionalQueryValue(form.get('category')),
    ingredient: optionalQueryValue(form.get('ingredient')),
    minFavorites: optionalQueryValue(form.get('minFavorites')),
    publishedAfter: optionalQueryValue(form.get('publishedAfter')),
    publishedBefore: optionalQueryValue(form.get('publishedBefore')),
    q: optionalQueryValue(form.get('q')),
    sort: optionalQueryValue(form.get('sort'))
  })
}

export function cleanRecipeSearchQuery(query: Record<string, string | number | undefined>): Record<string, string | undefined> {
  const cleanQuery: Record<string, string | undefined> = {}

  for (const [key, value] of Object.entries(query)) {
    if (undefined === value || '' === value || ('sort' === key && value === defaultSort) || ('page' === key && Number(value) <= 1)) {
      continue
    }

    cleanQuery[key] = String(value)
  }

  return cleanQuery
}

export function activeRecipeFilters(state: RecipeSearchState): Array<{ label: string, value: string }> {
  return [
    state.q ? { label: 'Search', value: state.q } : null,
    state.category ? { label: 'Category', value: state.category } : null,
    state.ingredient ? { label: 'Ingredient', value: state.ingredient } : null,
    state.alcohol ? { label: 'Alcohol', value: state.alcohol === 'with' ? 'With alcohol' : 'Zero-proof' } : null,
    state.author ? { label: 'Author', value: state.author } : null,
    state.minFavorites ? { label: 'Minimum saves', value: String(state.minFavorites) } : null,
    state.publishedAfter ? { label: 'After', value: state.publishedAfter } : null,
    state.publishedBefore ? { label: 'Before', value: state.publishedBefore } : null
  ].filter((filter): filter is { label: string, value: string } => Boolean(filter))
}

function alcoholValue(value: string | undefined): RecipeSearchParams['alcohol'] {
  return value === 'with' || value === 'without' ? value : undefined
}

function sortValue(value: string | undefined): RecipeSearchParams['sort'] {
  return value === 'popular' || value === 'newest' || value === 'oldest' ? value : undefined
}

function positiveNumberValue(value: string | undefined): number | undefined {
  const number = Number(value)

  return Number.isInteger(number) && number > 0 ? number : undefined
}

function textValue(value: string | undefined): string | undefined {
  const normalizedValue = value?.trim()

  return normalizedValue || undefined
}
