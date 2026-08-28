<script setup lang="ts">
import UiButton from '../ui/button/Button.vue'

defineProps<{
  eyebrow?: string
  title: string
  description: string
  actionLabel?: string
  actionTo?: string
}>()
</script>

<template>
  <header class="grid gap-6 py-4 md:grid-cols-[minmax(0,0.75fr)_auto] md:items-end md:py-6">
    <div>
      <p v-if="eyebrow" class="mb-2 text-xs font-medium uppercase tracking-wider text-muted-foreground">
        {{ eyebrow }}
      </p>
      <h1 class="page-heading max-w-3xl">
        {{ title }}
      </h1>
      <p class="mt-4 max-w-2xl text-base text-muted-foreground md:text-lg">
        {{ description }}
      </p>
    </div>

    <UiButton v-if="actionLabel && actionTo" as-child class="md:justify-self-end">
      <NuxtLink :to="actionTo">
        {{ actionLabel }}
      </NuxtLink>
    </UiButton>
  </header>
</template>
