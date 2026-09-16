import type { Category, RecipeResource } from '../../app/types/api'
import { sitemapHasNext, sitemapItems } from '../../app/utils/seo'

interface SitemapCollection<T> {
  'hydra:member'?: T[]
  'hydra:view'?: { 'hydra:next'?: string }
  member?: T[]
  view?: { next?: string }
}

export const sitemapApiTimeoutMs = 5000

const maximumRecipePages = 1000

export async function collectPublicSitemapPaths(apiBaseUrl: string, fetcher: typeof $fetch = $fetch): Promise<string[]> {
  const requestOptions = { retry: 0, timeout: sitemapApiTimeoutMs }
  const categories = await fetcher<Category[]>(`${apiBaseUrl}/categories`, {
    ...requestOptions,
    query: { pagination: false }
  })
  const recipes: RecipeResource[] = []

  for (let page = 1; page <= maximumRecipePages; page++) {
    const collection = await fetcher<SitemapCollection<RecipeResource>>(`${apiBaseUrl}/recipes`, {
      ...requestOptions,
      headers: { Accept: 'application/ld+json' },
      query: { page }
    })
    recipes.push(...sitemapItems(collection))
    if (!sitemapHasNext(collection)) break
    if (page === maximumRecipePages) throw new Error('Recipe sitemap page limit exceeded.')
  }

  const paths = ['/', '/categories', '/recipes']
  for (const category of categories) paths.push(`/categories/${encodeURIComponent(category.slug)}`)
  for (const recipe of recipes) {
    paths.push(`/recipes/${encodeURIComponent(recipe.slug)}`)
    if (recipe.authorUsername) paths.push(`/users/${encodeURIComponent(recipe.authorUsername)}`)
  }

  return paths
}
