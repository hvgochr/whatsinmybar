<script setup lang="ts">
import EmptyState from '../../../components/common/EmptyState.vue'
import RecipeCard from '../../../components/recipes/RecipeCard.vue'
import RecipeImage from '../../../components/recipes/RecipeImage.vue'
import FavoriteButton from '../../../components/social/FavoriteButton.vue'
import RecipeComments from '../../../components/social/RecipeComments.vue'
import ReportAction from '../../../components/social/ReportAction.vue'
import UiButton from '../../../components/ui/button/Button.vue'
import type { RecipeResource } from '../../../types/api'
import { collectionItems } from '../../../utils/api-collections'
import {
  categoryName,
  categorySlug,
  formatIngredientAmount,
  formatPublicDate,
  formatRecipeMeta,
  imageUrl,
  publicDescription,
  publicUrl
} from '../../../utils/public-content'

const api = useApi()
const auth = useAuth()
const route = useRoute()
const runtimeConfig = useRuntimeConfig()
const slug = computed(() => String(route.params.slug))

const { data: recipe, error: recipeError } = await useAsyncData(`recipe:${slug.value}`, () => api.recipes.get(slug.value))

if (recipeError.value && errorStatus(recipeError.value) !== 403) {
  throw createError({
    statusCode: errorStatus(recipeError.value),
    statusMessage: 'Recipe not found'
  })
}

const [{ data: commentsData }, { data: relatedRecipesData }] = await Promise.all([
  useAsyncData(`recipe:${slug.value}:comments`, () => api.comments.list(slug.value)),
  useAsyncData(`recipe:${slug.value}:related`, async () => {
    const firstCategory = recipe.value?.categories?.[0]

    if (!firstCategory) {
      return null
    }

    return api.recipes.list({
      category: categorySlug(firstCategory),
      sort: 'popular'
    })
  })
])

const comments = computed(() => commentsData.value?.items ?? [])
const relatedRecipes = computed(() => collectionItems(relatedRecipesData.value).filter(relatedRecipe => relatedRecipe.slug !== recipe.value?.slug).slice(0, 3))
const sortedIngredients = computed(() => [...(recipe.value?.recipeIngredients ?? [])].sort((a, b) => a.position - b.position))
const sortedSteps = computed(() => [...(recipe.value?.steps ?? [])].sort((a, b) => a.position - b.position))
const pageDescription = computed(() => publicDescription(recipe.value?.description, 'A community cocktail recipe on What\'s In My Bar.'))
const canonicalUrl = computed(() => publicUrl(runtimeConfig.public.siteUrl, `/recipes/${slug.value}`))
const ogImage = computed(() => imageUrl(recipe.value?.imagePath, runtimeConfig.public.apiBaseUrl))
const canEditRecipe = computed(() => {
  const user = auth.currentUser.value

  return Boolean(
    user
    && recipe.value
    && (recipe.value.authorUsername === user.username || user.roles.includes('ROLE_ADMIN'))
  )
})

function updateFavorite(state: { count: number, favorited: boolean }) {
  if (!recipe.value) {
    return
  }

  recipe.value.favoriteCount = state.count
  recipe.value.favorited = state.favorited
}

useSeoMeta({
  title: () => `${recipe.value?.title ?? 'Recipe'} | What's In My Bar`,
  description: () => pageDescription.value,
  ogDescription: () => pageDescription.value,
  ogImage: () => ogImage.value,
  ogTitle: () => `${recipe.value?.title ?? 'Recipe'} | What's In My Bar`,
  ogType: 'article',
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

  return status === 403 || status === 404 ? status : 500
}
</script>

<template>
  <main v-if="recipe" class="page-main">
    <article>
      <header class="mx-auto max-w-5xl pb-8 pt-2 text-center sm:pb-10 sm:pt-6">
        <p class="text-sm font-medium text-muted-foreground">{{ recipe.containsAlcohol ? 'Contains alcohol' : 'Zero-proof' }}</p>
        <h1 class="mt-2 text-4xl font-semibold tracking-tight sm:text-5xl lg:text-6xl">{{ recipe.title }}</h1>
        <p v-if="recipe.description" class="mx-auto mt-4 max-w-2xl text-base leading-7 text-muted-foreground sm:text-lg">{{ recipe.description }}</p>
        <dl class="mt-6 flex flex-wrap justify-center gap-x-5 gap-y-2 text-sm text-muted-foreground">
          <div v-if="formatRecipeMeta(recipe)"><dt class="sr-only">Recipe details</dt><dd>{{ formatRecipeMeta(recipe) }}</dd></div>
          <div><dt class="sr-only">Favorites</dt><dd>{{ recipe.favoriteCount }} saved</dd></div>
          <div v-if="recipe.publishedAt"><dt class="sr-only">Publication date</dt><dd>{{ formatPublicDate(recipe.publishedAt) }}</dd></div>
          <div v-if="recipe.authorUsername"><dt class="sr-only">Author</dt><dd>By <NuxtLink class="font-medium text-foreground underline-offset-4 hover:underline" :to="`/users/${recipe.authorUsername}`">{{ recipe.authorUsername }}</NuxtLink></dd></div>
        </dl>
        <div class="mt-5 flex flex-wrap justify-center gap-2">
          <NuxtLink v-for="category in recipe.categories" :key="categorySlug(category)" class="rounded-sm border px-2 py-1 text-xs text-muted-foreground hover:text-foreground" :to="`/categories/${categorySlug(category)}`">{{ categoryName(category) }}</NuxtLink>
        </div>
        <div class="mt-6 flex flex-wrap justify-center gap-3">
          <FavoriteButton :count="recipe.favoriteCount" :favorited="recipe.favorited" :recipe-slug="recipe.slug" @updated="updateFavorite" />
          <UiButton v-if="canEditRecipe" as-child variant="outline"><NuxtLink :to="`/recipes/${recipe.slug}/edit`">Edit recipe</NuxtLink></UiButton>
        </div>
      </header>

      <div class="mx-auto max-w-6xl overflow-hidden rounded-md border bg-muted">
        <RecipeImage eager :recipe="recipe" />
      </div>

      <div v-if="recipe.containsAlcohol" class="mx-auto mt-6 max-w-4xl rounded-md border bg-muted px-4 py-3 text-sm">
        This recipe contains alcohol. Availability is determined by your authenticated session and enforced by the API.
      </div>

      <div class="mx-auto mt-12 grid max-w-5xl gap-12 lg:grid-cols-[minmax(16rem,0.65fr)_minmax(0,1.35fr)]">
        <aside>
          <h2 class="section-heading">Ingredients</h2>
          <ul v-if="sortedIngredients.length > 0" class="mt-5 divide-y border-y">
            <li v-for="recipeIngredient in sortedIngredients" :key="recipeIngredient.id ?? recipeIngredient.position" class="py-3 text-sm leading-6">{{ formatIngredientAmount(recipeIngredient) }}</li>
          </ul>
          <p v-else class="mt-4 text-sm text-muted-foreground">Ingredients have not been listed yet.</p>
        </aside>

        <section>
          <h2 class="section-heading">Method</h2>
          <ol v-if="sortedSteps.length > 0" class="mt-5 space-y-6">
            <li v-for="step in sortedSteps" :key="step.id ?? step.position" class="grid grid-cols-[2rem_minmax(0,1fr)] gap-4">
              <span class="text-sm font-semibold text-muted-foreground">{{ step.position }}.</span>
              <p class="leading-7 text-foreground">{{ step.instruction }}</p>
            </li>
          </ol>
          <p v-else class="mt-4 text-sm text-muted-foreground">Preparation steps have not been listed yet.</p>
        </section>
      </div>

      <section class="mx-auto mt-14 grid max-w-5xl gap-10 border-t pt-10 lg:grid-cols-[minmax(0,1fr)_15rem]">
        <RecipeComments :comments="comments" :recipe-slug="recipe.slug" />
        <aside>
          <h2 class="text-sm font-semibold">Recipe actions</h2>
          <div class="mt-3 grid gap-2">
            <UiButton as-child variant="outline"><NuxtLink to="/recipes">Back to recipes</NuxtLink></UiButton>
            <ReportAction :login-redirect="`/recipes/${recipe.slug}`" :target-id="recipe.id" target-type="recipe" />
          </div>
        </aside>
      </section>

      <section v-if="relatedRecipes.length > 0" class="mt-16 border-t pt-10" aria-labelledby="related-recipes-title">
        <h2 id="related-recipes-title" class="section-heading">Related recipes</h2>
        <div class="mt-6 grid gap-x-5 gap-y-8 sm:grid-cols-2 lg:grid-cols-3">
          <RecipeCard v-for="relatedRecipe in relatedRecipes" :key="relatedRecipe.slug" :recipe="relatedRecipe as RecipeResource" />
        </div>
      </section>
    </article>
  </main>

  <main v-else class="page-main">
    <EmptyState
      action-label="Browse recipes"
      action-to="/recipes"
      :description="errorStatus(recipeError) === 403 ? 'This recipe is not available to your current account. Alcohol visibility and moderation rules are enforced by the API.' : 'This recipe is unavailable.'"
      :title="errorStatus(recipeError) === 403 ? 'Recipe restricted' : 'Recipe unavailable'"
    />
  </main>
</template>
