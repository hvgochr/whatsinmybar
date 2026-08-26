<script setup lang="ts">
import CategoryCard from '../components/categories/CategoryCard.vue'
import RecipeCard from '../components/recipes/RecipeCard.vue'
import UiButton from '../components/ui/button/Button.vue'
import { collectionItems } from '../utils/api-collections'
import { publicUrl } from '../utils/public-content'

const api = useApi()
const runtimeConfig = useRuntimeConfig()

const [
  { data: popularCollection, pending: recipesPending, error: recipesError },
  { data: newestCollection },
  { data: categoryCollection, pending: categoriesPending }
] = await Promise.all([
  useAsyncData('home:featured-recipes', () => api.recipes.list({ sort: 'popular' })),
  useAsyncData('home:newest-recipes', () => api.recipes.list({ sort: 'newest' })),
  useAsyncData('home:categories', () => api.categories.list())
])

const featuredRecipes = computed(() => collectionItems(popularCollection.value).slice(0, 4))
const newestRecipes = computed(() => collectionItems(newestCollection.value).slice(0, 4))
const featuredCategories = computed(() => collectionItems(categoryCollection.value).slice(0, 4))

useSeoMeta({
  title: "What's In My Bar",
  description: 'Discover, create, and share cocktail recipes with a community of home bartenders.',
  ogDescription: 'Discover, create, and share cocktail recipes with a community of home bartenders.',
  ogTitle: "What's In My Bar",
  ogType: 'website',
  ogUrl: publicUrl(runtimeConfig.public.siteUrl, '/')
})

useHead({
  link: [
    { href: publicUrl(runtimeConfig.public.siteUrl, '/'), rel: 'canonical' }
  ]
})
</script>

<template>
  <main>
    <section class="border-b" aria-labelledby="home-title">
      <div class="container-page py-14 sm:py-20">
        <p class="mb-3 text-sm font-medium text-muted-foreground">Cocktail recipes from real home bars</p>
        <h1 id="home-title" class="max-w-3xl text-4xl font-semibold tracking-tight sm:text-5xl">Find a recipe worth making.</h1>
        <p class="mt-4 max-w-2xl text-base leading-7 text-muted-foreground sm:text-lg">
          Discover clear, community-shared recipes, browse by category or ingredient, and keep the ones you want to make again.
        </p>
        <div class="mt-7 flex flex-wrap gap-3">
          <UiButton as-child><NuxtLink to="/recipes">Explore recipes</NuxtLink></UiButton>
          <UiButton as-child variant="outline"><NuxtLink to="/categories">Browse categories</NuxtLink></UiButton>
        </div>
      </div>
    </section>

    <div class="container-page space-y-16 py-12 sm:py-16">
      <section aria-labelledby="featured-recipes-title">
        <div class="mb-6 flex items-end justify-between gap-4">
          <div>
            <h2 id="featured-recipes-title" class="section-heading">Popular recipes</h2>
            <p class="section-description">Recipes most often saved by the community.</p>
          </div>
          <NuxtLink class="text-sm font-medium underline-offset-4 hover:underline" to="/recipes?sort=popular">View all</NuxtLink>
        </div>
        <div v-if="recipesPending" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4" aria-label="Loading popular recipes">
          <div v-for="index in 4" :key="index" class="space-y-3"><div class="aspect-[4/3] animate-pulse rounded-md bg-muted" /><div class="h-5 w-2/3 animate-pulse rounded bg-muted" /></div>
        </div>
        <div v-else-if="featuredRecipes.length" class="grid gap-x-5 gap-y-8 sm:grid-cols-2 lg:grid-cols-4">
          <RecipeCard v-for="recipe in featuredRecipes" :key="recipe.slug" :recipe="recipe" />
        </div>
        <p v-else-if="recipesError" class="rounded-md border p-5 text-sm text-muted-foreground">Popular recipes could not be loaded. Try the complete recipe index.</p>
        <p v-else class="rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground">No published recipes are available yet.</p>
      </section>

      <section v-if="newestRecipes.length" aria-labelledby="newest-recipes-title">
        <div class="mb-6 flex items-end justify-between gap-4">
          <div>
            <h2 id="newest-recipes-title" class="section-heading">Recently added</h2>
            <p class="section-description">The latest recipes available to you.</p>
          </div>
          <NuxtLink class="text-sm font-medium underline-offset-4 hover:underline" to="/recipes?sort=newest">View all</NuxtLink>
        </div>
        <div class="grid gap-x-5 gap-y-8 sm:grid-cols-2 lg:grid-cols-4">
          <RecipeCard v-for="recipe in newestRecipes" :key="recipe.slug" :recipe="recipe" />
        </div>
      </section>

      <section aria-labelledby="featured-categories-title">
        <div class="mb-6 flex items-end justify-between gap-4">
          <div>
            <h2 id="featured-categories-title" class="section-heading">Browse categories</h2>
            <p class="section-description">Start with a style, occasion, or classic family.</p>
          </div>
          <NuxtLink class="text-sm font-medium underline-offset-4 hover:underline" to="/categories">All categories</NuxtLink>
        </div>
        <div v-if="categoriesPending" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><div v-for="index in 4" :key="index" class="h-48 animate-pulse rounded-md bg-muted" /></div>
        <div v-else-if="featuredCategories.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <CategoryCard v-for="category in featuredCategories" :key="category.slug" :category="category" />
        </div>
        <p v-else class="rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground">Categories will appear here when they are available.</p>
      </section>

      <section class="flex flex-col gap-5 border-t pt-10 sm:flex-row sm:items-center sm:justify-between" aria-labelledby="share-recipe-title">
        <div>
          <h2 id="share-recipe-title" class="section-heading">Have a recipe to share?</h2>
          <p class="section-description">Create a draft, refine the details, then publish when it is ready.</p>
        </div>
        <UiButton as-child variant="outline"><NuxtLink to="/recipes/new">Create a recipe</NuxtLink></UiButton>
      </section>
    </div>
  </main>
</template>
