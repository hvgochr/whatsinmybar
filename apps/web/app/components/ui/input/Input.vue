<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '../../../lib/utils'

defineOptions({
  inheritAttrs: false
})

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()

const attrs = useAttrs()
const modelValue = defineModel<string | number>()
const inputValue = computed(() => modelValue.value ?? attrValue(attrs.value) ?? attrValue(attrs.defaultValue) ?? attrValue(attrs['default-value']) ?? '')

function updateValue(event: Event) {
  modelValue.value = (event.target as HTMLInputElement).value
}

function attrValue(value: unknown): string | number | null {
  return typeof value === 'string' || typeof value === 'number' ? value : null
}
</script>

<template>
  <input
    v-bind="attrs"
    :value="inputValue"
    :class="cn(
      'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground transition-colors file:border-0 file:bg-transparent file:text-sm file:font-semibold placeholder:text-muted-foreground/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-70',
      props.class
    )"
    @input="updateValue"
  >
</template>
