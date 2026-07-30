<script setup lang="ts">
defineProps<{
  current: 'dashboard' | 'users' | 'recipes' | 'categories' | 'ingredients' | 'reports'
  description: string
  title: string
}>()

const navItems = [
  { key: 'dashboard', label: 'Dashboard', to: '/admin' },
  { key: 'users', label: 'Users', to: '/admin/users' },
  { key: 'recipes', label: 'Recipes', to: '/admin/recipes' },
  { key: 'categories', label: 'Categories', to: '/admin/categories' },
  { key: 'ingredients', label: 'Ingredients', to: '/admin/ingredients' },
  { key: 'reports', label: 'Reports', to: '/admin/reports' }
] as const
</script>

<template>
  <main class="page-shell">
    <header class="grid gap-5 py-8">
      <div>
        <p class="eyebrow">
          Admin
        </p>
        <h1 class="page-title">
          {{ title }}
        </h1>
        <p class="page-copy">
          {{ description }}
        </p>
      </div>

      <nav class="flex gap-2 overflow-x-auto rounded-lg border border-border bg-card p-2" aria-label="Admin navigation">
        <NuxtLink
          v-for="item in navItems"
          :key="item.key"
          class="min-h-11 rounded-md px-3 py-2 text-sm font-black text-muted-foreground hover:bg-background hover:text-foreground"
          :class="current === item.key ? 'bg-background text-foreground shadow-sm' : ''"
          :to="item.to"
        >
          {{ item.label }}
        </NuxtLink>
      </nav>
    </header>

    <slot />
  </main>
</template>
