<script setup lang="ts">
import { Search01Icon } from '@hugeicons/core-free-icons'
import { HugeiconsIcon } from '@hugeicons/vue'
import UiInput from '../ui/input/Input.vue'

withDefaults(defineProps<{
  label?: string
  placeholder?: string
}>(), {
  label: 'Filter this page',
  placeholder: 'Search'
})

const search = defineModel<string>('search', { default: '' })
</script>

<template>
  <div class="mb-4 flex flex-col gap-3 rounded-md border bg-card p-3 sm:flex-row sm:items-end">
    <label class="grid min-w-0 flex-1 gap-2 sm:max-w-sm">
      <span class="field-label">{{ label }}</span>
      <span class="relative block">
        <HugeiconsIcon :icon="Search01Icon" :size="16" :stroke-width="1.75" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
        <UiInput v-model="search" class="pl-9" type="search" :placeholder="placeholder" />
      </span>
    </label>
    <slot />
  </div>
</template>
