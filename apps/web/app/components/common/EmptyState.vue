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
  <section class="grid min-h-64 place-items-center rounded-lg border border-dashed border-border bg-card px-5 py-12 text-center text-card-foreground">
    <div class="max-w-md">
      <h2 class="m-0 text-2xl font-black">
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
