<script setup lang="ts">
import EmptyState from '../../../components/common/EmptyState.vue'
import PublicPageHeader from '../../../components/common/PublicPageHeader.vue'
import RecipeEditor from '../../../components/recipes/RecipeEditor.vue'
import type { Category, Ingredient, RecipeResource, User } from '../../../types/api'
import { collectionItems } from '../../../utils/api-collections'

const api = useApi()
const auth = useAuth()
const route = useRoute()
const slug = computed(() => String(route.params.slug))

const categories = ref<Category[]>([])
const ingredients = ref<Ingredient[]>([])
const recipe = ref<RecipeResource | null>(null)
const loading = ref(true)
const loadError = ref<string | null>(null)

useSeoMeta({
  title: 'Edit recipe | What\'s In My Bar',
  description: 'Edit a cocktail recipe on What\'s In My Bar.',
  robots: 'noindex, nofollow'
})

onMounted(async () => {
  try {
    const user = await auth.restoreSession()

    if (!user) {
      await navigateTo('/login', { replace: true })
      return
    }

    const [recipeResource, categoryCollection, ingredientCollection] = await Promise.all([
      api.recipes.get(slug.value),
      api.categories.list(),
      api.ingredients.list()
    ])

    if (!canManageRecipe(recipeResource, user)) {
      loadError.value = 'You do not have permission to edit this recipe.'
      return
    }

    recipe.value = recipeResource
    categories.value = collectionItems(categoryCollection)
    ingredients.value = collectionItems(ingredientCollection)
  } catch {
    loadError.value = 'Recipe editor could not be loaded.'
  } finally {
    loading.value = false
  }
})

function canManageRecipe(recipeResource: RecipeResource, user: User): boolean {
  return recipeResource.authorUsername === user.username || user.roles.includes('ROLE_ADMIN')
}
</script>

<template>
  <main class="page-shell">
    <PublicPageHeader
      action-label="View recipe"
      :action-to="recipe ? `/recipes/${recipe.slug}` : '/recipes'"
      description="Update recipe details, measured ingredients, preparation steps, image, and publication status."
      eyebrow="Recipe editor"
      :title="recipe ? `Edit ${recipe.title}` : 'Edit recipe'"
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
      v-else-if="recipe"
      :categories="categories"
      :ingredients="ingredients"
      :initial-recipe="recipe"
      mode="edit"
    />
  </main>
</template>
