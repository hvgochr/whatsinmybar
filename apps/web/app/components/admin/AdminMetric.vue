<script setup lang="ts">
defineProps<{
  label: string
  value: number | string
}>()
</script>

<template>
  <article class="rounded-md border bg-card p-5 text-card-foreground">
    <p class="text-sm font-medium text-muted-foreground">
      {{ label }}
    </p>
    <p class="mt-2 text-3xl font-semibold tracking-tight text-foreground">
      {{ value }}
    </p>
  </article>
</template>
