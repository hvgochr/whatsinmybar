<script setup lang="ts">
import UiAlert from '../ui/alert/Alert.vue'

defineProps<{
  message: string
  tone: 'error' | 'success'
}>()
</script>

<template>
  <UiAlert :variant="tone === 'error' ? 'destructive' : 'success'" role="status">
    {{ message }}
  </UiAlert>
</template>
