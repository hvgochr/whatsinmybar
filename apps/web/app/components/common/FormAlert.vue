<script setup lang="ts">
defineProps<{
  message: string
  tone: 'error' | 'success'
}>()
</script>

<template>
  <p
    class="form-banner"
    :class="tone === 'error' ? 'form-banner-error' : 'form-banner-success'"
    role="status"
  >
    {{ message }}
  </p>
</template>
