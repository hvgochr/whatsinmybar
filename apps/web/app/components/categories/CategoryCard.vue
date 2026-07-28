<script setup lang="ts">
import type { Category } from '../../types/api'
import UiButton from '../ui/button/Button.vue'

defineProps<{
  category: Category
}>()
</script>

<template>
  <article class="rounded-lg border border-border bg-card p-5 text-card-foreground shadow-sm">
    <h2 class="m-0 text-2xl font-black">
      <NuxtLink class="hover:text-primary" :to="`/categories/${category.slug}`">
        {{ category.name }}
      </NuxtLink>
    </h2>
    <p class="mt-3 min-h-12 text-muted-foreground">
      {{ category.description || 'A curated shelf of cocktail recipes from the community.' }}
    </p>
    <UiButton as-child class="mt-5" variant="outline">
      <NuxtLink :to="`/categories/${category.slug}`">
        Browse category
      </NuxtLink>
    </UiButton>
  </article>
</template>
