<script setup lang="ts">
import type { RadioGroupItemProps } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import { reactiveOmit } from '@vueuse/core'
import { RadioGroupIndicator, RadioGroupItem } from 'reka-ui'
import { cn } from '~/lib/utils'

const props = defineProps<RadioGroupItemProps & { class?: HTMLAttributes['class'] }>()
const delegatedProps = reactiveOmit(props, 'class')
</script>

<template>
  <RadioGroupItem v-bind="delegatedProps" :class="cn('grid size-4 shrink-0 place-items-center rounded-full border border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:opacity-50', props.class)">
    <RadioGroupIndicator class="size-2 rounded-full bg-primary" />
  </RadioGroupItem>
</template>
