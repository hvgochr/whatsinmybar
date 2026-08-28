<script setup lang="ts">
import EmptyState from '../../components/common/EmptyState.vue'
import PublicPageHeader from '../../components/common/PublicPageHeader.vue'
import RecipeCard from '../../components/recipes/RecipeCard.vue'
import type { RecipeResource } from '../../types/api'
import { collectionItems } from '../../utils/api-collections'

const api = useApi()
const runtimeConfig = useRuntimeConfig()

const { data, pending, error } = await useAsyncData('categories:index', () => api.categories.list())
const categories = computed(() => collectionItems(data.value))
const previewLimit = 6
const previewedSlugs = computed(() => new Set(categories.value.slice(0, previewLimit).map(category => category.slug)))
const { data: previewsData, pending: previewsPending } = await useAsyncData('categories:previews', async () => {
  const entries = await Promise.all(categories.value.slice(0, previewLimit).map(async category => {
    try {
      const collection = await api.recipes.list({ category: category.slug, sort: 'popular' })
      return [category.slug, collectionItems(collection).slice(0, 4)] as const
    } catch {
      return [category.slug, []] as const
    }
  }))

  return Object.fromEntries(entries) as Record<string, RecipeResource[]>
})
const previews = computed(() => previewsData.value ?? {})

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

    <div v-if="pending" class="grid gap-10" aria-label="Loading categories">
      <div v-for="index in 3" :key="index" class="grid gap-4"><div class="h-8 w-52 animate-pulse rounded bg-muted" /><div class="grid grid-cols-2 gap-4 lg:grid-cols-4"><div v-for="card in 4" :key="card" class="aspect-[4/5] animate-pulse rounded-md bg-muted" /></div></div>
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

    <section v-else class="grid gap-14" aria-label="Recipe categories">
      <article v-for="category in categories" :key="category.slug" :aria-labelledby="`category-${category.slug}`">
        <header class="mb-5 flex items-end justify-between gap-4 border-b pb-4">
          <div class="min-w-0">
            <h2 :id="`category-${category.slug}`" class="section-heading">{{ category.name }}</h2>
            <p v-if="category.description" class="section-description max-w-2xl">{{ category.description }}</p>
          </div>
          <NuxtLink class="shrink-0 text-sm font-medium underline-offset-4 hover:underline" :to="`/categories/${category.slug}`">View all</NuxtLink>
        </header>

        <div v-if="previewsPending && previewedSlugs.has(category.slug)" class="flex gap-4 overflow-hidden md:grid md:grid-cols-4">
          <div v-for="index in 4" :key="index" class="aspect-[4/5] w-[78vw] max-w-[20rem] shrink-0 animate-pulse rounded-md bg-muted md:w-auto" />
        </div>
        <div v-else-if="previews[category.slug]?.length" class="-mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-4 sm:-mx-6 sm:px-6 md:mx-0 md:grid md:grid-cols-3 md:overflow-visible md:px-0 md:pb-0 xl:grid-cols-4">
          <RecipeCard v-for="recipe in previews[category.slug]" :key="recipe.slug" class="w-[78vw] max-w-[20rem] shrink-0 snap-start md:w-auto md:max-w-none" :recipe="recipe" />
        </div>
        <div v-else class="flex min-h-24 items-center justify-between gap-4 rounded-md border border-dashed px-5 py-4">
          <p class="text-sm text-muted-foreground">{{ previewedSlugs.has(category.slug) ? 'No recipes are available in this category yet.' : 'Open this category to browse its recipes.' }}</p>
          <NuxtLink class="shrink-0 text-sm font-medium underline-offset-4 hover:underline" :to="`/categories/${category.slug}`">Browse</NuxtLink>
        </div>
      </article>
    </section>
  </main>
</template>
