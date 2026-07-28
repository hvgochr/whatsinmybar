<script setup lang="ts">
import EmptyState from '../../components/common/EmptyState.vue'
import PublicPageHeader from '../../components/common/PublicPageHeader.vue'
import RecipeCard from '../../components/recipes/RecipeCard.vue'
import { collectionItems } from '../../utils/api-collections'
import { publicDescription, publicUrl } from '../../utils/public-content'

const api = useApi()
const route = useRoute()
const runtimeConfig = useRuntimeConfig()
const slug = computed(() => String(route.params.slug))

const { data: category, error: categoryError } = await useAsyncData(`category:${slug.value}`, () => api.categories.get(slug.value))

if (categoryError.value) {
  throw createError({
    statusCode: errorStatus(categoryError.value),
    statusMessage: 'Category not found'
  })
}

const { data: recipesCollection, pending, error } = await useAsyncData(`category:${slug.value}:recipes`, () => api.recipes.list({
  category: slug.value,
  sort: 'popular'
}))

const recipes = computed(() => collectionItems(recipesCollection.value))
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
</script>

<template>
  <main class="page-shell">
    <PublicPageHeader
      v-if="category"
      action-label="All categories"
      action-to="/categories"
      :description="description"
      eyebrow="Category"
      :title="category.name"
    />

    <div v-if="pending" class="loading-panel">
      Loading category recipes...
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

    <section v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3" aria-label="Category recipes">
      <RecipeCard v-for="recipe in recipes" :key="recipe.slug" :recipe="recipe" />
    </section>
  </main>
</template>
