<script setup lang="ts">
import UiButton from '../ui/button/Button.vue'

defineProps<{
  actionLabel?: string
  actionTo?: string
  current: 'dashboard' | 'users' | 'recipes' | 'categories' | 'ingredients' | 'comments' | 'reports'
  description: string
  title: string
}>()

</script>

<template>
  <div class="mx-auto max-w-[96rem]">
    <header class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="mb-2 text-xs font-medium uppercase tracking-wider text-muted-foreground">Administration / {{ current }}</p>
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
          {{ title }}
        </h1>
        <p class="mt-2 max-w-3xl text-sm text-muted-foreground">
          {{ description }}
        </p>
      </div>
      <UiButton v-if="actionLabel && actionTo" as-child><NuxtLink :to="actionTo">{{ actionLabel }}</NuxtLink></UiButton>
    </header>

    <slot />
  </div>
</template>
