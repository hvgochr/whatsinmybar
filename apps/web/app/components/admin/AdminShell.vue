<script setup lang="ts">
defineProps<{
  current: 'dashboard' | 'users' | 'recipes' | 'categories' | 'ingredients' | 'comments' | 'reports'
  description: string
  title: string
}>()

</script>

<template>
  <div class="mx-auto max-w-[96rem]">
    <header class="mb-6 grid gap-2">
      <div>
        <p class="mb-2 text-xs font-medium uppercase tracking-wider text-muted-foreground">Administration / {{ current }}</p>
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
          {{ title }}
        </h1>
        <p class="mt-2 max-w-3xl text-sm text-muted-foreground">
          {{ description }}
        </p>
      </div>
    </header>

    <slot />
  </div>
</template>
