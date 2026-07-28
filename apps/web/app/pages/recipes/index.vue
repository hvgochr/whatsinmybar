<script setup lang="ts">
import EmptyState from '../../components/common/EmptyState.vue'
import PublicPageHeader from '../../components/common/PublicPageHeader.vue'
import RecipeCard from '../../components/recipes/RecipeCard.vue'
import UiButton from '../../components/ui/button/Button.vue'
import UiInput from '../../components/ui/input/Input.vue'
import type { RecipeSearchParams } from '../../types/api'
import { collectionItems, collectionTotal } from '../../utils/api-collections'
import { firstQueryValue, optionalQueryValue } from '../../utils/route-query'

const api = useApi()
const route = useRoute()
const runtimeConfig = useRuntimeConfig()

const params = computed<RecipeSearchParams>(() => ({
  alcohol: alcoholValue(firstQueryValue(route.query, 'alcohol')),
  author: firstQueryValue(route.query, 'author'),
  category: firstQueryValue(route.query, 'category'),
  ingredient: firstQueryValue(route.query, 'ingredient'),
  minFavorites: numberValue(firstQueryValue(route.query, 'minFavorites')),
  publishedAfter: firstQueryValue(route.query, 'publishedAfter'),
  publishedBefore: firstQueryValue(route.query, 'publishedBefore'),
  q: firstQueryValue(route.query, 'q'),
  sort: sortValue(firstQueryValue(route.query, 'sort')) ?? 'newest'
}))

const [{ data: recipesCollection, pending: recipesPending, error: recipesError }, { data: categoriesCollection }, { data: ingredientsCollection }] = await Promise.all([
  useAsyncData(`recipes:${route.fullPath}`, () => api.recipes.list(params.value), { watch: [() => route.fullPath] }),
  useAsyncData('recipes:filters:categories', () => api.categories.list()),
  useAsyncData('recipes:filters:ingredients', () => api.ingredients.list())
])

const recipes = computed(() => collectionItems(recipesCollection.value))
const categories = computed(() => collectionItems(categoriesCollection.value))
const ingredients = computed(() => collectionItems(ingredientsCollection.value))
const totalRecipes = computed(() => collectionTotal(recipesCollection.value))

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
    query: {
      alcohol: optionalQueryValue(form.get('alcohol')),
      author: optionalQueryValue(form.get('author')),
      category: optionalQueryValue(form.get('category')),
      ingredient: optionalQueryValue(form.get('ingredient')),
      minFavorites: optionalQueryValue(form.get('minFavorites')),
      publishedAfter: optionalQueryValue(form.get('publishedAfter')),
      publishedBefore: optionalQueryValue(form.get('publishedBefore')),
      q: optionalQueryValue(form.get('q')),
      sort: optionalQueryValue(form.get('sort'))
    }
  })
}

function alcoholValue(value: string | undefined): RecipeSearchParams['alcohol'] {
  return value === 'with' || value === 'without' ? value : undefined
}

function sortValue(value: string | undefined): RecipeSearchParams['sort'] {
  return value === 'popular' || value === 'newest' || value === 'oldest' ? value : undefined
}

function numberValue(value: string | undefined): number | undefined {
  const number = Number(value)

  return Number.isFinite(number) && number > 0 ? number : undefined
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

    <form class="mb-8 grid gap-3 rounded-lg border border-border bg-card p-4 shadow-sm md:grid-cols-[minmax(180px,1.4fr)_repeat(3,minmax(130px,0.75fr))] lg:grid-cols-[minmax(220px,1.4fr)_repeat(7,minmax(110px,0.75fr))]" @submit.prevent="applyFilters">
      <UiInput aria-label="Search recipes" :default-value="params.q" name="q" type="search" />

      <select class="min-h-12 rounded-lg border border-input bg-background px-3.5 py-3 text-foreground" name="category" :value="params.category">
        <option value="">
          Any category
        </option>
        <option v-for="category in categories" :key="category.slug" :value="category.slug">
          {{ category.name }}
        </option>
      </select>

      <select class="min-h-12 rounded-lg border border-input bg-background px-3.5 py-3 text-foreground" name="ingredient" :value="params.ingredient">
        <option value="">
          Any ingredient
        </option>
        <option v-for="ingredient in ingredients" :key="ingredient.slug" :value="ingredient.slug">
          {{ ingredient.name }}
        </option>
      </select>

      <select class="min-h-12 rounded-lg border border-input bg-background px-3.5 py-3 text-foreground" name="alcohol" :value="params.alcohol">
        <option value="">
          Alcohol or zero-proof
        </option>
        <option value="with">
          With alcohol
        </option>
        <option value="without">
          Zero-proof
        </option>
      </select>

      <UiInput aria-label="Author username" :default-value="params.author" name="author" type="search" />
      <UiInput aria-label="Minimum favorites" :default-value="params.minFavorites" min="1" name="minFavorites" type="number" />
      <UiInput aria-label="Published after" :default-value="params.publishedAfter" name="publishedAfter" type="date" />
      <UiInput aria-label="Published before" :default-value="params.publishedBefore" name="publishedBefore" type="date" />

      <select class="min-h-12 rounded-lg border border-input bg-background px-3.5 py-3 text-foreground" name="sort" :value="params.sort">
        <option value="newest">
          Newest
        </option>
        <option value="popular">
          Most saved
        </option>
        <option value="oldest">
          Oldest
        </option>
      </select>

      <UiButton class="md:col-span-4 lg:col-span-1" type="submit">
        Search
      </UiButton>
    </form>

    <p class="mb-4 text-sm font-bold text-muted-foreground">
      {{ totalRecipes }} recipe{{ totalRecipes === 1 ? '' : 's' }}
    </p>

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
  </main>
</template>
