<script setup lang="ts">
import type { RecipeResource } from '../../types/api'
import { categoryName, formatRecipeMeta } from '../../utils/public-content'
import FavoriteButton from '../social/FavoriteButton.vue'
import RecipeImage from './RecipeImage.vue'

const props = defineProps<{
  recipe: RecipeResource
  showStatus?: boolean
}>()

const category = computed(() => props.recipe.categories?.[0])
const meta = computed(() => formatRecipeMeta(props.recipe))
const favoriteCount = ref(props.recipe.favoriteCount)
const favorited = ref(props.recipe.favorited)
const hasImage = computed(() => Boolean(props.recipe.imagePath))

watch(() => props.recipe, recipe => {
  favoriteCount.value = recipe.favoriteCount
  favorited.value = recipe.favorited
})

function updateFavorite(state: { count: number, favorited: boolean }) {
  favoriteCount.value = state.count
  favorited.value = state.favorited
}
</script>

<template>
  <article class="group relative aspect-[4/5] min-w-0 overflow-hidden rounded-md border bg-muted">
    <NuxtLink
      :to="`/recipes/${recipe.slug}`"
      class="absolute inset-0 z-10 block rounded-[inherit] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
      :aria-label="`View ${recipe.title}`"
    >
      <RecipeImage class="h-full" variant="card" :recipe="recipe" />
      <div v-if="hasImage" class="pointer-events-none absolute inset-x-0 bottom-0 h-3/5 bg-linear-to-t from-black/90 via-black/45 to-transparent" aria-hidden="true" />
      <div class="absolute inset-x-0 bottom-0 p-4" :class="hasImage ? 'text-white' : 'text-foreground'">
        <div class="mb-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[0.6875rem] font-medium uppercase tracking-wide" :class="hasImage ? 'text-white/75' : 'text-muted-foreground'">
          <span v-if="category">{{ categoryName(category) }}</span>
          <span v-if="showStatus">{{ recipe.status }}</span>
          <span v-if="recipe.containsAlcohol">Contains alcohol</span>
        </div>
        <h2 class="line-clamp-2 text-lg font-semibold leading-snug text-balance">{{ recipe.title }}</h2>
        <p v-if="meta" class="mt-1.5 line-clamp-1 text-sm" :class="hasImage ? 'text-white/80' : 'text-muted-foreground'">{{ meta }}</p>
      </div>
    </NuxtLink>

    <FavoriteButton
      :contrast="hasImage"
      :count="favoriteCount"
      :favorited="favorited"
      overlay
      :recipe-slug="recipe.slug"
      @updated="updateFavorite"
    />
  </article>
</template>
