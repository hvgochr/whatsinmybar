<script setup lang="ts">
import type { RecipeResource } from '../../types/api'
import { imageUrl } from '../../utils/public-content'

const props = defineProps<{
  recipe: Pick<RecipeResource, 'imagePath' | 'title' | 'containsAlcohol'>
  eager?: boolean
  variant?: 'card' | 'detail' | 'default'
}>()

const runtimeConfig = useRuntimeConfig()
const src = computed(() => imageUrl(props.recipe.imagePath, runtimeConfig.public.apiBaseUrl))
</script>

<template>
  <div
    class="relative overflow-hidden bg-muted"
    :class="variant === 'card' ? 'aspect-[4/5]' : variant === 'detail' ? 'aspect-[4/3] sm:aspect-[16/10]' : 'aspect-[4/3]'"
  >
    <img
      v-if="src"
      :alt="recipe.title"
      class="h-full w-full object-cover"
      :loading="eager ? 'eager' : 'lazy'"
      :src="src"
    >
    <div v-else class="grid h-full place-items-center bg-muted p-6 text-center">
      <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
        Photo unavailable
      </p>
    </div>
  </div>
</template>
