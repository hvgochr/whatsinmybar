<script setup lang="ts">
import { Toaster } from 'vue-sonner'
import 'vue-sonner/style.css'

const theme = useTheme()
</script>

<template>
  <Toaster
    :theme="theme.resolvedTheme.value"
    position="bottom-right"
    :toast-options="{
      classes: {
        toast: 'border-border bg-popover text-popover-foreground shadow-sm',
        description: 'text-muted-foreground',
        actionButton: 'bg-primary text-primary-foreground',
        cancelButton: 'bg-muted text-muted-foreground'
      }
    }"
  />
</template>
