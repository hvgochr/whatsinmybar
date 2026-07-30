<script setup lang="ts">
defineProps<{
  label: string
  value: number | string
}>()
</script>

<template>
  <article class="rounded-lg border border-border bg-card p-4 text-card-foreground shadow-sm">
    <p class="text-sm font-bold text-muted-foreground">
      {{ label }}
    </p>
    <p class="mt-2 text-3xl font-black text-foreground">
      {{ value }}
    </p>
  </article>
</template>
