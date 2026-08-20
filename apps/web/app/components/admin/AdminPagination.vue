<script setup lang="ts">
import UiButton from '../ui/button/Button.vue'
import { adminPageTo } from '../../utils/admin-pagination'

const props = defineProps<{
  page: number
  path: string
  totalItems: number
  totalPages: number
}>()

const hasPreviousPage = computed(() => props.page > 1)
const hasNextPage = computed(() => props.page < props.totalPages)
const previousTo = computed(() => adminPageTo(props.path, props.page - 1))
const nextTo = computed(() => adminPageTo(props.path, props.page + 1))
</script>

<template>
  <nav
    v-if="totalItems > 0"
    class="mt-6 flex flex-col gap-4 rounded-lg border border-border bg-card p-4 text-card-foreground shadow-sm sm:flex-row sm:items-center sm:justify-between"
    aria-label="Admin list pagination"
  >
    <p class="text-sm font-bold text-muted-foreground">
      {{ totalItems }} result{{ totalItems === 1 ? '' : 's' }} · Page {{ page }} of {{ totalPages }}
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
