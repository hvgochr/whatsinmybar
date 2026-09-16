import { createError, setResponseHeader } from 'h3'
import type { Category, RecipeResource } from '../../app/types/api'
import { renderSitemap, sitemapHasNext, sitemapItems } from '../../app/utils/seo'

interface SitemapCollection<T> {
  'hydra:member'?: T[]
  'hydra:view'?: { 'hydra:next'?: string }
  member?: T[]
  view?: { next?: string }
}

const maximumRecipePages = 1000

export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig(event)
  const apiBaseUrl = config.apiBaseUrl.replace(/\/$/, '')

  try {
    const categories = await $fetch<Category[]>(`${apiBaseUrl}/categories`, {
      query: { pagination: false }
    })
    const recipes: RecipeResource[] = []

    for (let page = 1; page <= maximumRecipePages; page++) {
      const collection = await $fetch<SitemapCollection<RecipeResource>>(`${apiBaseUrl}/recipes`, {
        headers: { Accept: 'application/ld+json' },
        query: { page }
      })
      recipes.push(...sitemapItems(collection))
      if (!sitemapHasNext(collection)) break
      if (page === maximumRecipePages) throw new Error('Recipe sitemap page limit exceeded.')
    }

    const paths = ['/', '/about', '/categories', '/recipes']
    for (const category of categories) paths.push(`/categories/${encodeURIComponent(category.slug)}`)
    for (const recipe of recipes) {
      paths.push(`/recipes/${encodeURIComponent(recipe.slug)}`)
      if (recipe.authorUsername) paths.push(`/users/${encodeURIComponent(recipe.authorUsername)}`)
    }

    setResponseHeader(event, 'Content-Type', 'application/xml; charset=UTF-8')
    setResponseHeader(event, 'Cache-Control', 'public, max-age=3600')
    return renderSitemap(config.public.siteUrl, paths)
  } catch (error) {
    throw createError({
      cause: error,
      statusCode: 503,
      statusMessage: 'Sitemap temporarily unavailable'
    })
  }
})
