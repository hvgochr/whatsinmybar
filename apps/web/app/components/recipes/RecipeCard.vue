<script setup lang="ts">
import type { RecipeResource } from '../../types/api'
import { categoryName, categorySlug, formatRecipeMeta } from '../../utils/public-content'
import RecipeImage from './RecipeImage.vue'
import FavoriteButton from '../social/FavoriteButton.vue'

const props = defineProps<{
  recipe: RecipeResource
}>()

const category = computed(() => props.recipe.categories?.[0])
const meta = computed(() => formatRecipeMeta(props.recipe))
const favoriteCount = ref(props.recipe.favoriteCount)
const favorited = ref(props.recipe.favorited)

watch(() => props.recipe, (recipe) => {
  favoriteCount.value = recipe.favoriteCount
  favorited.value = recipe.favorited
})

function updateFavorite(state: { count: number, favorited: boolean }) {
  favoriteCount.value = state.count
  favorited.value = state.favorited
}
</script>

<template>
  <article class="group min-w-0">
    <div class="relative overflow-hidden rounded-md border bg-muted">
      <NuxtLink :to="`/recipes/${recipe.slug}`" class="block focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring">
        <RecipeImage :recipe="recipe" />
      </NuxtLink>
      <FavoriteButton
        class="absolute top-2 right-2"
        compact
        :count="favoriteCount"
        :favorited="favorited"
        :recipe-slug="recipe.slug"
        @updated="updateFavorite"
      />
    </div>
    <div class="pt-3">
      <div class="flex items-start justify-between gap-3">
        <h2 class="min-w-0 text-base font-semibold leading-snug">
          <NuxtLink class="underline-offset-4 group-hover:underline" :to="`/recipes/${recipe.slug}`">{{ recipe.title }}</NuxtLink>
        </h2>
        <span v-if="recipe.containsAlcohol" class="shrink-0 text-xs text-muted-foreground">Alcohol</span>
      </div>
      <p v-if="meta" class="mt-1 text-sm text-muted-foreground">{{ meta }}</p>
      <NuxtLink v-if="category" class="mt-1 inline-block text-xs text-muted-foreground underline-offset-4 hover:text-foreground hover:underline" :to="`/categories/${categorySlug(category)}`">
        {{ categoryName(category) }}
      </NuxtLink>
    </div>
  </article>
</template>
