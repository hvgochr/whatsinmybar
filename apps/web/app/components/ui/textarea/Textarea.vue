<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '../../../lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()

const modelValue = defineModel<string>()
</script>

<template>
  <textarea
    v-model="modelValue"
    :class="cn(
      'flex min-h-28 w-full rounded-lg border border-input bg-background px-3.5 py-3 text-base text-foreground shadow-sm transition-colors placeholder:text-muted-foreground/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-70',
      props.class
    )"
  />
</template>
