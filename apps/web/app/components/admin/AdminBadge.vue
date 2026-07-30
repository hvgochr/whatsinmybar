<script setup lang="ts">
defineProps<{
  tone?: 'default' | 'danger' | 'muted' | 'success' | 'warning'
}>()
</script>

<template>
  <span
    class="inline-flex min-h-7 items-center rounded-full border px-2.5 py-1 text-xs font-black"
    :class="{
      'border-border bg-background text-muted-foreground': !tone || tone === 'default',
      'border-destructive/30 bg-destructive/10 text-destructive': tone === 'danger',
      'border-border bg-muted text-muted-foreground': tone === 'muted',
      'border-primary/30 bg-primary/10 text-primary': tone === 'success',
      'border-secondary/80 bg-secondary text-secondary-foreground': tone === 'warning'
    }"
  >
    <slot />
  </span>
</template>
