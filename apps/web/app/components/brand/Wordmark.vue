<script setup lang="ts">
withDefaults(defineProps<{ decorative?: boolean }>(), { decorative: false })
</script>

<template>
  <svg
    class="h-6 w-[168px] shrink-0 text-foreground"
    viewBox="0 0 168 24"
    xmlns="http://www.w3.org/2000/svg"
    :aria-hidden="decorative ? 'true' : undefined"
    :aria-label="decorative ? undefined : 'WhatsInMyBar'"
    role="img"
  >
    <text fill="currentColor" font-family="Geist Sans, Geist, ui-sans-serif, system-ui, sans-serif" font-size="19" font-weight="650" letter-spacing="-0.75" x="0" y="18">WhatsInMyBar</text>
  </svg>
</template>
