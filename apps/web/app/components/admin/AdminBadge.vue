<script setup lang="ts">
defineProps<{
  tone?: 'default' | 'danger' | 'muted' | 'success' | 'warning'
}>()
</script>

<template>
  <span
    class="inline-flex min-h-6 items-center rounded-sm border px-2 py-0.5 text-xs font-medium"
    :class="{
      'border-border bg-background text-muted-foreground': !tone || tone === 'default',
      'border-foreground bg-foreground text-background': tone === 'danger',
      'border-border bg-muted text-muted-foreground': tone === 'muted',
      'border-foreground bg-background text-foreground': tone === 'success',
      'border-foreground/50 bg-muted text-foreground': tone === 'warning'
    }"
  >
    <slot />
  </span>
</template>
