<script setup lang="ts">
import EmptyState from '../../../components/common/EmptyState.vue'
import PublicPageHeader from '../../../components/common/PublicPageHeader.vue'
import RecipeEditor from '../../../components/recipes/RecipeEditor.vue'
import type { Category, Ingredient, RecipeResource } from '../../../types/api'
import { collectionItems } from '../../../utils/api-collections'

const api = useApi()
const auth = useAuth()
const route = useRoute()
const slug = computed(() => String(route.params.slug))
const viewer = auth.currentUser.value ?? await auth.restoreSession()

if (!viewer) await navigateTo(`/login?redirect=${encodeURIComponent(`/recipes/${slug.value}/edit`)}`, { replace: true })

const [recipeResource, categoriesResource, ingredientsResource] = await Promise.all([
  useAsyncData(`recipe-editor:${slug.value}`, () => api.recipes.get(slug.value)),
  useAsyncData('recipe-editor:categories', () => api.categories.list()),
  useAsyncData('recipe-editor:ingredients', () => api.ingredients.list())
])
const recipe = computed<RecipeResource | null>(() => recipeResource.data.value ?? null)
const categories = computed<Category[]>(() => collectionItems(categoriesResource.data.value))
const ingredients = computed<Ingredient[]>(() => collectionItems(ingredientsResource.data.value))
const loading = computed(() => recipeResource.pending.value || categoriesResource.pending.value || ingredientsResource.pending.value)
const loadError = computed(() => {
  if (recipeResource.error.value || categoriesResource.error.value || ingredientsResource.error.value) {
    return 'Recipe editor could not be loaded.'
  }

  if (recipe.value && viewer && !canManageRecipe(recipe.value, viewer)) {
    return 'You do not have permission to edit this recipe.'
  }

  return null
})

useSeoMeta({
  title: 'Edit recipe | What\'s In My Bar',
  description: 'Edit a cocktail recipe on What\'s In My Bar.',
  robots: 'noindex, nofollow'
})

function canManageRecipe(recipeResource: RecipeResource, user: { username: string, roles: readonly string[] }): boolean {
  return recipeResource.authorUsername === user.username || user.roles.includes('ROLE_ADMIN')
}
</script>

<template>
  <main class="page-main">
    <PublicPageHeader
      action-label="View recipe"
      :action-to="recipe ? `/recipes/${recipe.slug}` : '/recipes'"
      description="Update recipe details, measured ingredients, preparation steps, image, and publication status."
      eyebrow="Recipe editor"
      :title="recipe ? `Edit ${recipe.title}` : 'Edit recipe'"
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
      v-else-if="recipe"
      :categories="categories"
      :ingredients="ingredients"
      :initial-recipe="recipe"
      mode="edit"
    />
  </main>
</template>
