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
const modelValue = defineModel<string>()
const textareaValue = computed(() => modelValue.value ?? attrValue(attrs.value) ?? attrValue(attrs.defaultValue) ?? attrValue(attrs['default-value']) ?? '')

function updateValue(event: Event) {
  modelValue.value = (event.target as HTMLTextAreaElement).value
}

function attrValue(value: unknown): string | null {
  return typeof value === 'string' ? value : null
}
</script>

<template>
  <textarea
    v-bind="attrs"
    :value="textareaValue"
    :class="cn(
      'flex min-h-28 w-full rounded-lg border border-input bg-background px-3.5 py-3 text-base text-foreground shadow-sm transition-colors placeholder:text-muted-foreground/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-70',
      props.class
    )"
    @input="updateValue"
  />
</template>
