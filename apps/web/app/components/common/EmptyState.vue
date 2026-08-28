<script setup lang="ts">
import UiButton from '../ui/button/Button.vue'

defineProps<{
  title: string
  description: string
  actionLabel?: string
  actionTo?: string
}>()
</script>

<template>
  <section class="grid min-h-56 place-items-center rounded-md border border-dashed bg-card px-5 py-12 text-center text-card-foreground">
    <div class="max-w-md">
      <h2 class="text-xl font-semibold tracking-tight">
        {{ title }}
      </h2>
      <p class="mt-3 text-muted-foreground">
        {{ description }}
      </p>
      <UiButton v-if="actionLabel && actionTo" as-child class="mt-6">
        <NuxtLink :to="actionTo">
          {{ actionLabel }}
        </NuxtLink>
      </UiButton>
    </div>
  </section>
</template>
