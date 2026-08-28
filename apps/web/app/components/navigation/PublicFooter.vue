<script setup lang="ts">
import Wordmark from '../brand/Wordmark.vue'
</script>

<template>
  <footer class="mt-auto border-t">
    <div class="container-page flex flex-col gap-6 py-8 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <Wordmark />
        <p class="mt-2 text-sm text-muted-foreground">Cocktail recipes, shared clearly.</p>
      </div>
      <nav class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-muted-foreground" aria-label="Footer navigation">
        <NuxtLink class="hover:text-foreground" to="/recipes">Recipes</NuxtLink>
        <NuxtLink class="hover:text-foreground" to="/categories">Categories</NuxtLink>
        <NuxtLink class="hover:text-foreground" to="/register">Create an account</NuxtLink>
      </nav>
    </div>
  </footer>
</template>
