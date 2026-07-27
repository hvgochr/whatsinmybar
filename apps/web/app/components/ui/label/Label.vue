<script setup lang="ts">
import { Label, type LabelProps } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import { cn } from '../../../lib/utils'

const props = defineProps<LabelProps & {
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <Label
    v-bind="props"
    :class="cn('text-sm font-extrabold leading-none text-foreground peer-disabled:cursor-not-allowed peer-disabled:opacity-70', props.class)"
  >
    <slot />
  </Label>
</template>
