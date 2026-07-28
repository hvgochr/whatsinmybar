<script setup lang="ts">
import EmptyState from '../../components/common/EmptyState.vue'
import RecipeCard from '../../components/recipes/RecipeCard.vue'
import RecipeImage from '../../components/recipes/RecipeImage.vue'
import UiButton from '../../components/ui/button/Button.vue'
import type { RecipeResource } from '../../types/api'
import { collectionItems } from '../../utils/api-collections'
import {
  categoryName,
  categorySlug,
  formatIngredientAmount,
  formatPublicDate,
  formatRecipeMeta,
  imageUrl,
  publicDescription,
  publicUrl
} from '../../utils/public-content'

const api = useApi()
const route = useRoute()
const runtimeConfig = useRuntimeConfig()
const slug = computed(() => String(route.params.slug))

const { data: recipe, error: recipeError } = await useAsyncData(`recipe:${slug.value}`, () => api.recipes.get(slug.value))

if (recipeError.value) {
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
const visibleComments = computed(() => comments.value.filter(comment => !comment.parentId).slice(0, 6))
const relatedRecipes = computed(() => collectionItems(relatedRecipesData.value).filter(relatedRecipe => relatedRecipe.slug !== recipe.value?.slug).slice(0, 3))
const sortedIngredients = computed(() => [...(recipe.value?.recipeIngredients ?? [])].sort((a, b) => a.position - b.position))
const sortedSteps = computed(() => [...(recipe.value?.steps ?? [])].sort((a, b) => a.position - b.position))
const pageDescription = computed(() => publicDescription(recipe.value?.description, 'A community cocktail recipe on What\'s In My Bar.'))
const canonicalUrl = computed(() => publicUrl(runtimeConfig.public.siteUrl, `/recipes/${slug.value}`))
const ogImage = computed(() => imageUrl(recipe.value?.imagePath, runtimeConfig.public.apiBaseUrl))

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

  return status === 404 ? 404 : 500
}
</script>

<template>
  <main v-if="recipe" class="page-shell">
    <article>
      <header class="grid gap-7 py-8 lg:grid-cols-[minmax(0,0.8fr)_minmax(320px,0.55fr)] lg:items-start">
        <div>
          <p class="eyebrow">
            {{ recipe.containsAlcohol ? 'Cocktail recipe' : 'Zero-proof recipe' }}
          </p>
          <h1 class="m-0 max-w-3xl text-4xl font-black leading-tight text-foreground md:text-6xl">
            {{ recipe.title }}
          </h1>
          <p class="mt-4 max-w-2xl text-lg text-muted-foreground">
            {{ recipe.description }}
          </p>

          <dl class="mt-6 flex flex-wrap gap-3 text-sm font-bold text-muted-foreground">
            <div v-if="formatRecipeMeta(recipe)" class="rounded-full border border-border bg-card px-3 py-1.5">
              <dt class="sr-only">
                Recipe details
              </dt>
              <dd>{{ formatRecipeMeta(recipe) }}</dd>
            </div>
            <div class="rounded-full border border-border bg-card px-3 py-1.5">
              <dt class="sr-only">
                Favorites
              </dt>
              <dd>{{ recipe.favoriteCount }} saved</dd>
            </div>
            <div v-if="recipe.publishedAt" class="rounded-full border border-border bg-card px-3 py-1.5">
              <dt class="sr-only">
                Publication date
              </dt>
              <dd>{{ formatPublicDate(recipe.publishedAt) }}</dd>
            </div>
          </dl>

          <div class="mt-5 flex flex-wrap gap-2">
            <NuxtLink
              v-for="category in recipe.categories"
              :key="categorySlug(category)"
              class="rounded-full bg-secondary px-3 py-1.5 text-sm font-black text-secondary-foreground hover:bg-secondary/82"
              :to="`/categories/${categorySlug(category)}`"
            >
              {{ categoryName(category) }}
            </NuxtLink>
          </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-border bg-card shadow-warm">
          <RecipeImage eager :recipe="recipe" />
        </div>
      </header>

      <div class="grid gap-6 lg:grid-cols-[minmax(260px,0.42fr)_minmax(0,1fr)]">
        <aside class="content-panel p-5 md:p-6">
          <h2 class="section-title">
            Ingredients
          </h2>
          <ul v-if="sortedIngredients.length > 0" class="mt-5 grid gap-3">
            <li v-for="recipeIngredient in sortedIngredients" :key="recipeIngredient.id ?? recipeIngredient.position" class="rounded-lg border border-border bg-background px-3.5 py-3">
              {{ formatIngredientAmount(recipeIngredient) }}
            </li>
          </ul>
          <p v-else class="mt-4 text-muted-foreground">
            Ingredients have not been listed yet.
          </p>
        </aside>

        <section class="content-panel p-5 md:p-6">
          <h2 class="section-title">
            Method
          </h2>
          <ol v-if="sortedSteps.length > 0" class="mt-5 grid gap-4">
            <li v-for="step in sortedSteps" :key="step.id ?? step.position" class="grid gap-3 rounded-lg border border-border bg-background p-4 sm:grid-cols-[2.5rem_minmax(0,1fr)]">
              <span class="grid size-10 place-items-center rounded-full bg-primary text-sm font-black text-primary-foreground">
                {{ step.position }}
              </span>
              <p class="m-0 text-foreground">
                {{ step.instruction }}
              </p>
            </li>
          </ol>
          <p v-else class="mt-4 text-muted-foreground">
            Preparation steps have not been listed yet.
          </p>
        </section>
      </div>

      <section class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(260px,0.4fr)]">
        <div class="content-panel p-5 md:p-6">
          <h2 class="section-title">
            Community notes
          </h2>
          <div v-if="visibleComments.length > 0" class="mt-5 grid gap-3">
            <article v-for="comment in visibleComments" :key="comment.id" class="rounded-lg border border-border bg-background p-4">
              <p class="m-0 font-black">
                {{ comment.authorUsername }}
              </p>
              <p class="mt-2 text-muted-foreground">
                {{ comment.message || 'This comment is no longer visible.' }}
              </p>
            </article>
          </div>
          <p v-else class="mt-4 text-muted-foreground">
            No public comments yet.
          </p>
        </div>

        <aside class="content-panel p-5 md:p-6">
          <h2 class="section-title">
            Author
          </h2>
          <p v-if="recipe.authorUsername" class="mt-3 text-muted-foreground">
            Created by
            <NuxtLink class="font-black text-foreground hover:text-primary" :to="`/users/${recipe.authorUsername}`">
              {{ recipe.authorUsername }}
            </NuxtLink>.
          </p>
          <p v-else class="mt-3 text-muted-foreground">
            The author profile is unavailable.
          </p>
          <UiButton as-child class="mt-5 w-full" variant="outline">
            <NuxtLink to="/recipes">
              Back to recipes
            </NuxtLink>
          </UiButton>
        </aside>
      </section>

      <section v-if="relatedRecipes.length > 0" class="mt-10" aria-labelledby="related-recipes-title">
        <h2 id="related-recipes-title" class="section-title">
          Related recipes
        </h2>
        <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          <RecipeCard v-for="relatedRecipe in relatedRecipes" :key="relatedRecipe.slug" :recipe="relatedRecipe as RecipeResource" />
        </div>
      </section>
    </article>
  </main>

  <main v-else class="page-shell">
    <EmptyState
      action-label="Browse recipes"
      action-to="/recipes"
      description="This recipe is unavailable or restricted."
      title="Recipe unavailable"
    />
  </main>
</template>
