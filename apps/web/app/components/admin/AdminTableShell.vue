<script setup lang="ts">
defineProps<{
  empty: boolean
  emptyMessage: string
  label: string
}>()
</script>

<template>
  <div class="overflow-hidden rounded-md border bg-card">
    <div class="overflow-x-auto">
      <table class="admin-table" :aria-label="label"><slot /></table>
    </div>
    <p v-if="empty" class="p-5 text-sm text-muted-foreground">{{ emptyMessage }}</p>
  </div>
</template>
