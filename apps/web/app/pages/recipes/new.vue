<script setup lang="ts">
import EmptyState from '../../components/common/EmptyState.vue'
import PublicPageHeader from '../../components/common/PublicPageHeader.vue'
import RecipeEditor from '../../components/recipes/RecipeEditor.vue'
import type { Category, Ingredient } from '../../types/api'
import { collectionItems } from '../../utils/api-collections'

const api = useApi()
const auth = useAuth()

const categories = ref<Category[]>([])
const ingredients = ref<Ingredient[]>([])
const loading = ref(true)
const loadError = ref<string | null>(null)

useSeoMeta({
  title: 'Create recipe | What\'s In My Bar',
  description: 'Create a cocktail recipe draft on What\'s In My Bar.',
  robots: 'noindex, nofollow'
})

onMounted(async () => {
  try {
    const user = await auth.restoreSession()

    if (!user) {
      await navigateTo('/login', { replace: true })
      return
    }

    const [categoryCollection, ingredientCollection] = await Promise.all([
      api.categories.list(),
      api.ingredients.list()
    ])

    categories.value = collectionItems(categoryCollection)
    ingredients.value = collectionItems(ingredientCollection)
  } catch {
    loadError.value = 'Recipe editor could not be loaded.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <main class="page-shell">
    <PublicPageHeader
      description="Start with a private draft, add measured ingredients and ordered steps, then publish when the recipe is ready."
      eyebrow="Recipe editor"
      title="Create a recipe"
    />

    <section v-if="loading" class="content-panel loading-panel" aria-live="polite">
      Loading recipe editor...
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
