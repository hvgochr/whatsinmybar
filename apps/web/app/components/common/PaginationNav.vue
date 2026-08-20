<script setup lang="ts">
import type { PaginationState } from '../../utils/pagination'
import UiButton from '../ui/button/Button.vue'

withDefaults(defineProps<{
  ariaLabel?: string
  nextTo: Record<string, unknown>
  pagination: PaginationState
  previousTo: Record<string, unknown>
}>(), {
  ariaLabel: 'Results pagination'
})
</script>

<template>
  <nav
    v-if="pagination.totalItems > 0"
    class="mt-8 flex flex-col gap-4 rounded-lg border border-border bg-card p-4 text-card-foreground shadow-sm sm:flex-row sm:items-center sm:justify-between"
    :aria-label="ariaLabel"
  >
    <p class="text-sm font-bold text-muted-foreground">
      <span v-if="pagination.resultStart > 0">Showing {{ pagination.resultStart }}-{{ pagination.resultEnd }} of {{ pagination.totalItems }}</span>
      <span v-else>{{ pagination.totalItems }} total result{{ pagination.totalItems === 1 ? '' : 's' }}</span>
      <span v-if="pagination.totalPages !== null"> · Page {{ pagination.currentPage }} of {{ pagination.totalPages }}</span>
      <span v-else> · Page {{ pagination.currentPage }}</span>
    </p>

    <div class="flex flex-wrap gap-2">
      <UiButton v-if="pagination.hasPreviousPage" as-child variant="outline">
        <NuxtLink :to="previousTo">
          Previous
        </NuxtLink>
      </UiButton>
      <UiButton v-else type="button" variant="outline" disabled>
        Previous
      </UiButton>

      <UiButton v-if="pagination.hasNextPage" as-child variant="outline">
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
