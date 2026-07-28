<script setup lang="ts">
import type { RecipeResource } from '../../types/api'
import { imageUrl } from '../../utils/public-content'

const props = defineProps<{
  recipe: Pick<RecipeResource, 'imagePath' | 'title' | 'containsAlcohol'>
  eager?: boolean
}>()

const runtimeConfig = useRuntimeConfig()
const src = computed(() => imageUrl(props.recipe.imagePath, runtimeConfig.public.apiBaseUrl))
</script>

<template>
  <div class="relative aspect-[4/3] overflow-hidden bg-muted">
    <img
      v-if="src"
      :alt="recipe.title"
      class="h-full w-full object-cover"
      :loading="eager ? 'eager' : 'lazy'"
      :src="src"
    >
    <div v-else class="grid h-full place-items-center bg-[radial-gradient(circle_at_30%_20%,hsl(var(--secondary)),transparent_32%),linear-gradient(135deg,hsl(var(--muted)),hsl(var(--accent)/0.42))] p-6 text-center">
      <p class="m-0 text-sm font-black uppercase tracking-normal text-accent-foreground/80">
        {{ recipe.containsAlcohol ? 'Cocktail recipe' : 'Zero-proof recipe' }}
      </p>
    </div>
  </div>
</template>
