<script setup lang="ts">
import EmptyState from '../../components/common/EmptyState.vue'
import PublicPageHeader from '../../components/common/PublicPageHeader.vue'
import RecipeEditor from '../../components/recipes/RecipeEditor.vue'
import type { Category, Ingredient } from '../../types/api'
import { collectionItems } from '../../utils/api-collections'

const api = useApi()
const auth = useAuth()
const viewer = auth.currentUser.value ?? await auth.restoreSession()

if (!viewer) await navigateTo('/login?redirect=%2Frecipes%2Fnew', { replace: true })

const [categoriesResource, ingredientsResource] = await Promise.all([
  useAsyncData('recipe-editor:categories', () => api.categories.list()),
  useAsyncData('recipe-editor:ingredients', () => api.ingredients.list())
])
const categories = computed<Category[]>(() => collectionItems(categoriesResource.data.value))
const ingredients = computed<Ingredient[]>(() => collectionItems(ingredientsResource.data.value))
const loading = computed(() => categoriesResource.pending.value || ingredientsResource.pending.value)
const loadError = computed(() => categoriesResource.error.value || ingredientsResource.error.value
  ? 'Recipe editor could not be loaded.'
  : null)

useSeoMeta({
  title: 'Create recipe | What\'s In My Bar',
  description: 'Create a cocktail recipe draft on What\'s In My Bar.',
  robots: 'noindex, nofollow'
})

</script>

<template>
  <main class="page-main">
    <PublicPageHeader
      description="Start with a private draft, add measured ingredients and ordered steps, then publish when the recipe is ready."
      eyebrow="Recipe editor"
      title="Create a recipe"
    />

    <section v-if="loading" class="grid gap-4 lg:grid-cols-[13rem_minmax(0,1fr)]" aria-live="polite">
      <div class="h-72 animate-pulse rounded-md bg-muted" /><div class="space-y-4"><div v-for="index in 4" :key="index" class="h-56 animate-pulse rounded-md bg-muted" /></div>
    </section>

    <EmptyState
      v-else-if="loadError"
      action-label="Back to recipes"
      action-to="/recipes"
      :description="loadError"
      title="Editor unavailable"
    />

    <RecipeEditor
      v-else
      :categories="categories"
      :ingredients="ingredients"
      mode="create"
    />
  </main>
</template>
