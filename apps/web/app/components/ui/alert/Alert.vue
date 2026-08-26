<script setup lang="ts">
import { cva, type VariantProps } from 'class-variance-authority'
import type { HTMLAttributes } from 'vue'
import { cn } from '../../../lib/utils'

const alertVariants = cva(
  'relative w-full rounded-md border px-4 py-3 text-sm',
  {
    defaultVariants: {
      variant: 'default'
    },
    variants: {
      variant: {
        default: 'bg-background text-foreground',
        destructive: 'border-foreground bg-muted text-foreground',
        success: 'border-border bg-muted text-foreground'
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
