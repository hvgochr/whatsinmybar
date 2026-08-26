<script setup lang="ts">
import { Primitive, type PrimitiveProps } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import { cn } from '../../../lib/utils'
import { buttonVariants, type ButtonVariants } from './variants'

defineOptions({ inheritAttrs: false })

interface Props extends PrimitiveProps {
  class?: HTMLAttributes['class']
  size?: ButtonVariants['size']
  variant?: ButtonVariants['variant']
}

const props = withDefaults(defineProps<Props>(), {
  as: 'button',
  class: undefined,
  size: 'default',
  variant: 'default'
})
</script>

<template>
  <Primitive
    v-bind="$attrs"
    :as="as"
    :as-child="asChild"
    :class="cn(buttonVariants({ variant, size }), props.class)"
  >
    <slot />
  </Primitive>
</template>
