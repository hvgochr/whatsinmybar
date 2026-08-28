<script setup lang="ts">
import PublicFooter from '../components/navigation/PublicFooter.vue'
import PublicHeader from '../components/navigation/PublicHeader.vue'
</script>

<template>
  <div class="flex min-h-screen flex-col bg-background">
    <a href="#main-content" class="sr-only z-50 rounded-md bg-background px-4 py-2 focus:not-sr-only focus:fixed focus:top-2 focus:left-2">Skip to content</a>
    <PublicHeader />
    <div id="main-content" class="flex-1"><slot /></div>
    <PublicFooter />
  </div>
</template>
