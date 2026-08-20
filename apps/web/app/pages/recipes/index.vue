<script setup lang="ts">
import EmptyState from '../../components/common/EmptyState.vue'
import PaginationNav from '../../components/common/PaginationNav.vue'
import PublicPageHeader from '../../components/common/PublicPageHeader.vue'
import RecipeCard from '../../components/recipes/RecipeCard.vue'
import RecipeSearchPanel from '../../components/recipes/RecipeSearchPanel.vue'
import { collectionItems, collectionLastPage, collectionTotal } from '../../utils/api-collections'
import { paginationState } from '../../utils/pagination'
import {
  activeRecipeFilters,
  cleanRecipeSearchQuery,
  recipeSearchQueryFromForm,
  recipeSearchStateFromQuery
} from '../../utils/recipe-search'

const api = useApi()
const route = useRoute()
const runtimeConfig = useRuntimeConfig()

const searchState = computed(() => recipeSearchStateFromQuery(route.query))

const [{ data: recipesCollection, pending: recipesPending, error: recipesError }, { data: categoriesCollection }, { data: ingredientsCollection }] = await Promise.all([
  useAsyncData(`recipes:${route.fullPath}`, () => api.recipes.list(searchState.value), { watch: [() => route.fullPath] }),
  useAsyncData('recipes:filters:categories', () => api.categories.list()),
  useAsyncData('recipes:filters:ingredients', () => api.ingredients.list())
])

const recipes = computed(() => collectionItems(recipesCollection.value))
const categories = computed(() => collectionItems(categoriesCollection.value))
const ingredients = computed(() => collectionItems(ingredientsCollection.value))
const activeFilters = computed(() => {
  return activeRecipeFilters(searchState.value).map((filter) => {
    if (filter.label === 'Category') {
      return { ...filter, value: categories.value.find(category => category.slug === filter.value)?.name ?? filter.value }
    }

    if (filter.label === 'Ingredient') {
      return { ...filter, value: ingredients.value.find(ingredient => ingredient.slug === filter.value)?.name ?? filter.value }
    }

    return filter
  })
})
const totalRecipes = computed(() => collectionTotal(recipesCollection.value))
const pagination = computed(() => paginationState({
  currentPage: searchState.value.page,
  itemsOnPage: recipes.value.length,
  totalPages: collectionLastPage(recipesCollection.value),
  totalItems: totalRecipes.value
}))
const previousPageTo = computed(() => recipePageTo(pagination.value.previousPage))
const nextPageTo = computed(() => recipePageTo(pagination.value.nextPage))

useSeoMeta({
  title: 'Cocktail recipes | What\'s In My Bar',
  description: 'Search community cocktail recipes by category, ingredient, alcohol preference, author, popularity, and publication date.',
  ogDescription: 'Search community cocktail recipes by category, ingredient, alcohol preference, author, popularity, and publication date.',
  ogTitle: 'Cocktail recipes | What\'s In My Bar',
  ogType: 'website',
  ogUrl: new URL('/recipes', runtimeConfig.public.siteUrl).toString()
})

useHead({
  link: [
    { href: new URL('/recipes', runtimeConfig.public.siteUrl).toString(), rel: 'canonical' }
  ]
})

function applyFilters(event: Event) {
  const form = new FormData(event.currentTarget as HTMLFormElement)

  return navigateTo({
    path: '/recipes',
    query: recipeSearchQueryFromForm(form)
  })
}

function recipePageTo(page: number) {
  return {
    path: '/recipes',
    query: cleanRecipeSearchQuery({
      ...searchState.value,
      page
    })
  }
}
</script>

<template>
  <main class="page-shell">
    <PublicPageHeader
      action-label="Share a recipe"
      action-to="/recipes/new"
      description="Search the community shelf by flavor, ingredient, category, author, alcohol preference, and popularity."
      eyebrow="Recipes"
      title="Find your next cocktail"
    />

    <RecipeSearchPanel
      :active-filters="activeFilters"
      :categories="categories"
      :ingredients="ingredients"
      :pending="recipesPending"
      :state="searchState"
      @submit="applyFilters"
    />

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <p class="text-sm font-bold text-muted-foreground">
        {{ totalRecipes }} recipe{{ totalRecipes === 1 ? '' : 's' }}
      </p>
      <p v-if="totalRecipes > 0" class="text-sm font-bold text-muted-foreground">
        Page {{ pagination.currentPage }}<span v-if="pagination.totalPages"> of {{ pagination.totalPages }}</span>
      </p>
    </div>

    <div v-if="recipesPending" class="loading-panel">
      Loading recipes...
    </div>

    <EmptyState
      v-else-if="recipesError"
      action-label="Reset filters"
      action-to="/recipes"
      description="The recipe shelf is unavailable right now."
      title="Recipes could not be loaded"
    />

    <EmptyState
      v-else-if="recipes.length === 0"
      action-label="Reset filters"
      action-to="/recipes"
      description="Try a broader search, another ingredient, or a different category."
      title="No recipes match these filters"
    />

    <section v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3" aria-label="Recipe results">
      <RecipeCard v-for="recipe in recipes" :key="recipe.slug" :recipe="recipe" />
    </section>

    <PaginationNav
      v-if="!recipesPending && !recipesError"
      aria-label="Recipe results pagination"
      :next-to="nextPageTo"
      :pagination="pagination"
      :previous-to="previousPageTo"
    />
  </main>
</template>
