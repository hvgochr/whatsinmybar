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
  <header class="grid gap-6 py-8 md:grid-cols-[minmax(0,0.75fr)_auto] md:items-end md:py-12">
    <div>
      <p v-if="eyebrow" class="eyebrow">
        {{ eyebrow }}
      </p>
      <h1 class="m-0 max-w-3xl text-4xl font-black leading-tight text-foreground md:text-6xl">
        {{ title }}
      </h1>
      <p class="mt-4 max-w-2xl text-base text-muted-foreground md:text-lg">
        {{ description }}
      </p>
    </div>

    <UiButton v-if="actionLabel && actionTo" as-child>
      <NuxtLink :to="actionTo">
        {{ actionLabel }}
      </NuxtLink>
    </UiButton>
  </header>
</template>
