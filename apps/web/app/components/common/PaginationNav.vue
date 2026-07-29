<script setup lang="ts">
import UiButton from '../ui/button/Button.vue'

defineProps<{
  currentPage: number
  hasNextPage: boolean
  hasPreviousPage: boolean
  lastPage: number | null
  nextTo: Record<string, unknown>
  previousTo: Record<string, unknown>
  resultEnd: number
  resultStart: number
  totalItems: number
}>()
</script>

<template>
  <nav
    v-if="totalItems > 0"
    class="mt-8 flex flex-col gap-4 rounded-lg border border-border bg-card p-4 text-card-foreground shadow-sm sm:flex-row sm:items-center sm:justify-between"
    aria-label="Recipe results pagination"
  >
    <p class="text-sm font-bold text-muted-foreground">
      Showing {{ resultStart }}-{{ resultEnd }} of {{ totalItems }}
      <span v-if="lastPage"> · Page {{ currentPage }} of {{ lastPage }}</span>
      <span v-else> · Page {{ currentPage }}</span>
    </p>

    <div class="flex flex-wrap gap-2">
      <UiButton v-if="hasPreviousPage" as-child variant="outline">
        <NuxtLink :to="previousTo">
          Previous
        </NuxtLink>
      </UiButton>
      <UiButton v-else type="button" variant="outline" disabled>
        Previous
      </UiButton>

      <UiButton v-if="hasNextPage" as-child variant="outline">
        <NuxtLink :to="nextTo">
          Next
        </NuxtLink>
      </UiButton>
      <UiButton v-else type="button" variant="outline" disabled>
        Next
      </UiButton>
    </div>
  </nav>
</template>
