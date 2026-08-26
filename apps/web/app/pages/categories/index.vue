<script setup lang="ts">
import CategoryCard from '../../components/categories/CategoryCard.vue'
import EmptyState from '../../components/common/EmptyState.vue'
import PublicPageHeader from '../../components/common/PublicPageHeader.vue'
import { collectionItems } from '../../utils/api-collections'

const api = useApi()
const runtimeConfig = useRuntimeConfig()

const { data, pending, error } = await useAsyncData('categories:index', () => api.categories.list())
const categories = computed(() => collectionItems(data.value))

useSeoMeta({
  title: 'Cocktail categories | What\'s In My Bar',
  description: 'Browse cocktail recipe categories, from classics and aperitifs to zero-proof drinks.',
  ogDescription: 'Browse cocktail recipe categories, from classics and aperitifs to zero-proof drinks.',
  ogTitle: 'Cocktail categories | What\'s In My Bar',
  ogType: 'website',
  ogUrl: new URL('/categories', runtimeConfig.public.siteUrl).toString()
})

useHead({
  link: [
    { href: new URL('/categories', runtimeConfig.public.siteUrl).toString(), rel: 'canonical' }
  ]
})
</script>

<template>
  <main class="page-main">
    <PublicPageHeader
      description="Browse published recipes by style, occasion, or established cocktail family."
      eyebrow="Explore"
      title="Categories"
    />

    <div v-if="pending" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Loading categories">
      <div v-for="index in 8" :key="index" class="h-48 animate-pulse rounded-md bg-muted" />
    </div>

    <EmptyState
      v-else-if="error"
      description="The category shelf is unavailable right now."
      title="Categories could not be loaded"
    />

    <EmptyState
      v-else-if="categories.length === 0"
      description="Categories will appear here once the community starts organizing recipes."
      title="No categories yet"
    />

    <section v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Recipe categories">
      <CategoryCard v-for="category in categories" :key="category.slug" :category="category" />
    </section>
  </main>
</template>
