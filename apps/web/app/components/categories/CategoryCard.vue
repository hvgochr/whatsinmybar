<script setup lang="ts">
import type { Category } from '../../types/api'
import UiButton from '../ui/button/Button.vue'

defineProps<{
  category: Category
}>()
</script>

<template>
  <article class="group flex min-h-48 flex-col rounded-md border bg-card p-5 text-card-foreground">
    <h2 class="text-xl font-semibold tracking-tight">
      <NuxtLink class="underline-offset-4 group-hover:underline" :to="`/categories/${category.slug}`">
        {{ category.name }}
      </NuxtLink>
    </h2>
    <p class="mt-3 flex-1 text-sm leading-6 text-muted-foreground">
      {{ category.description || 'A curated shelf of cocktail recipes from the community.' }}
    </p>
    <UiButton as-child class="mt-5 self-start" variant="outline" size="sm">
      <NuxtLink :to="`/categories/${category.slug}`">
        Browse category
      </NuxtLink>
    </UiButton>
  </article>
</template>
