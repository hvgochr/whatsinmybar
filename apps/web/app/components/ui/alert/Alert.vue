<script setup lang="ts">
import { cva, type VariantProps } from 'class-variance-authority'
import type { HTMLAttributes } from 'vue'
import { cn } from '../../../lib/utils'

const alertVariants = cva(
  'rounded-lg border px-3.5 py-3 text-sm font-bold',
  {
    defaultVariants: {
      variant: 'default'
    },
    variants: {
      variant: {
        default: 'border-border bg-muted text-foreground',
        destructive: 'border-destructive/30 bg-destructive/10 text-destructive',
        success: 'border-success/30 bg-success/12 text-success'
      }
    }
  }
)

type AlertVariants = VariantProps<typeof alertVariants>

const props = defineProps<{
  class?: HTMLAttributes['class']
  variant?: AlertVariants['variant']
}>()
</script>

<template>
  <div :class="cn(alertVariants({ variant }), props.class)" role="alert">
    <slot />
  </div>
</template>
