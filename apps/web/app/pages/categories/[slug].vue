<script setup lang="ts">
import EmptyState from '../../components/common/EmptyState.vue'
import PaginationNav from '../../components/common/PaginationNav.vue'
import PublicPageHeader from '../../components/common/PublicPageHeader.vue'
import RecipeCard from '../../components/recipes/RecipeCard.vue'
import RecipeSearchPanel from '../../components/recipes/RecipeSearchPanel.vue'
import { collectionItems, collectionLastPage, collectionTotal } from '../../utils/api-collections'
import { pageLocation, paginationState } from '../../utils/pagination'
import { publicDescription, publicUrl } from '../../utils/public-content'
import { activeRecipeFilters, cleanRecipeSearchQuery, recipeSearchStateFromQuery } from '../../utils/recipe-search'
import type { RecipeSearchState } from '../../utils/recipe-search'

const api = useApi()
const route = useRoute()
const runtimeConfig = useRuntimeConfig()
const slug = computed(() => String(route.params.slug))
const searchState = computed(() => ({ ...recipeSearchStateFromQuery(route.query), category: slug.value }))

const { data: category, error: categoryError } = await useAsyncData(`category:${slug.value}`, () => api.categories.get(slug.value))

if (categoryError.value) {
  throw createError({
    statusCode: errorStatus(categoryError.value),
    statusMessage: 'Category not found'
  })
}

const [{ data: recipesCollection, pending, error }, { data: ingredientsCollection }] = await Promise.all([
  useAsyncData(`category:${slug.value}:recipes:${route.fullPath}`, () => api.recipes.list({
    ...searchState.value,
    category: slug.value
  }), { watch: [() => route.fullPath] }),
  useAsyncData('recipes:filters:ingredients', () => api.ingredients.list())
])

const recipes = computed(() => collectionItems(recipesCollection.value))
const ingredients = computed(() => collectionItems(ingredientsCollection.value))
const totalRecipes = computed(() => collectionTotal(recipesCollection.value))
const activeFilters = computed(() => activeRecipeFilters(searchState.value)
  .filter(filter => filter.key !== 'category')
  .map(filter => ({ ...filter, to: filterRemovalTo(filter.key), value: filter.key === 'ingredient' ? ingredients.value.find(item => item.slug === filter.value)?.name ?? filter.value : filter.value })))
const pagination = computed(() => paginationState({
  currentPage: searchState.value.page,
  itemsOnPage: recipes.value.length,
  totalPages: collectionLastPage(recipesCollection.value),
  totalItems: totalRecipes.value
}))
const previousPageTo = computed(() => categoryPageTo(pagination.value.previousPage))
const nextPageTo = computed(() => categoryPageTo(pagination.value.nextPage))
const description = computed(() => publicDescription(category.value?.description, 'Cocktail recipes grouped by style and occasion.'))
const canonicalUrl = computed(() => publicUrl(runtimeConfig.public.siteUrl, `/categories/${slug.value}`))

useSeoMeta({
  title: () => `${category.value?.name ?? 'Category'} cocktail recipes | What's In My Bar`,
  description: () => description.value,
  ogDescription: () => description.value,
  ogTitle: () => `${category.value?.name ?? 'Category'} cocktail recipes | What's In My Bar`,
  ogType: 'website',
  ogUrl: () => canonicalUrl.value
})

useHead({
  link: [
    { href: canonicalUrl.value, rel: 'canonical' }
  ]
})

function errorStatus(error: unknown): number {
  const status = typeof error === 'object' && error !== null && 'status' in error
    ? Number((error as { status?: unknown }).status)
    : 500

  return status === 404 ? 404 : 500
}

function applyFilters(state: RecipeSearchState) {
  return navigateTo({
    path: `/categories/${slug.value}`,
    query: categoryQuery(state)
  })
}

function filterRemovalTo(key: string) {
  const query = Object.fromEntries(Object.entries(categoryQuery(searchState.value)).filter(([queryKey]) => queryKey !== key && queryKey !== 'page'))
  return { path: `/categories/${slug.value}`, query }
}

function categoryPageTo(page: number) {
  return { ...pageLocation(route.path, route.query, page), hash: route.hash }
}

function categoryQuery(state: RecipeSearchState) {
  const { category: _category, ...query } = cleanRecipeSearchQuery(state)
  return query
}
</script>

<template>
  <main class="page-main">
    <PublicPageHeader
      v-if="category"
      action-label="All categories"
      action-to="/categories"
      :description="description"
      eyebrow="Category"
      :title="category.name"
    />

    <RecipeSearchPanel
      :active-filters="activeFilters"
      :categories="[]"
      :clear-to="`/categories/${slug}`"
      :fixed-category="slug"
      :ingredients="ingredients"
      :pending="pending"
      :result-count="totalRecipes"
      :state="searchState"
      @apply="applyFilters"
    />

    <div v-if="pending" class="grid gap-x-5 gap-y-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" aria-label="Loading category recipes">
      <div v-for="index in 4" :key="index" class="space-y-3"><div class="aspect-[4/5] animate-pulse rounded-md bg-muted" /><div class="h-5 w-2/3 animate-pulse rounded bg-muted" /></div>
    </div>

    <EmptyState
      v-else-if="error"
      action-label="Browse recipes"
      action-to="/recipes"
      description="Recipes in this category are unavailable right now."
      title="Recipes could not be loaded"
    />

    <EmptyState
      v-else-if="recipes.length === 0"
      action-label="Browse all recipes"
      action-to="/recipes"
      description="No published recipes are currently attached to this category."
      title="No recipes in this category yet"
    />

    <section v-else class="grid gap-x-5 gap-y-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" aria-label="Category recipes">
      <RecipeCard v-for="recipe in recipes" :key="recipe.slug" :recipe="recipe" />
    </section>

    <PaginationNav
      v-if="!pending && !error"
      aria-label="Category recipe pagination"
      :next-to="nextPageTo"
      :pagination="pagination"
      :previous-to="previousPageTo"
    />
  </main>
</template>
