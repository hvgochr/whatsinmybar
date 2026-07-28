<script setup lang="ts">
import CategoryCard from '../components/categories/CategoryCard.vue'
import RecipeCard from '../components/recipes/RecipeCard.vue'
import UiButton from '../components/ui/button/Button.vue'
import { collectionItems } from '../utils/api-collections'
import { publicUrl } from '../utils/public-content'

const api = useApi()
const runtimeConfig = useRuntimeConfig()

const [{ data: recipeCollection }, { data: categoryCollection }] = await Promise.all([
  useAsyncData('home:featured-recipes', () => api.recipes.list({ sort: 'popular' })),
  useAsyncData('home:categories', () => api.categories.list())
])

const featuredRecipes = computed(() => collectionItems(recipeCollection.value).slice(0, 3))
const featuredCategories = computed(() => collectionItems(categoryCollection.value).slice(0, 3))

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
  <main class="page-shell">
    <section class="grid items-start gap-7 py-8 md:grid-cols-[minmax(0,0.9fr)_minmax(320px,420px)] md:py-12 lg:gap-14" aria-labelledby="home-title">
      <div>
        <p class="eyebrow">
          Cocktail community
        </p>
        <h1 id="home-title" class="page-title">
          What's In My Bar
        </h1>
        <p class="page-copy">
          Discover community-tested cocktail recipes, browse by ingredient, and keep a public profile for your own bar notebook.
        </p>
        <div class="mt-7 flex flex-wrap gap-3">
          <UiButton as-child>
            <NuxtLink to="/recipes">
              Explore recipes
            </NuxtLink>
          </UiButton>
          <UiButton as-child variant="outline">
            <NuxtLink to="/categories">
              Browse categories
            </NuxtLink>
          </UiButton>
        </div>
      </div>

      <div class="auth-panel">
        <div class="panel-header">
          <h2 class="panel-title">
            Start mixing
          </h2>
          <p class="panel-copy">
            Create an account or log back in to manage your bar profile.
          </p>
        </div>

        <div class="form-stack">
          <UiButton as-child class="w-full">
            <NuxtLink to="/register">
              Create an account
            </NuxtLink>
          </UiButton>
          <UiButton as-child class="w-full" variant="outline">
            <NuxtLink to="/login">
              Log in
            </NuxtLink>
          </UiButton>
        </div>
      </div>
    </section>

    <section v-if="featuredRecipes.length > 0" class="mt-8" aria-labelledby="featured-recipes-title">
      <div class="mb-5 flex items-end justify-between gap-4">
        <div>
          <p class="eyebrow">
            Popular recipes
          </p>
          <h2 id="featured-recipes-title" class="section-title">
            Saved by the community
          </h2>
        </div>
        <NuxtLink class="font-black text-primary hover:underline" to="/recipes?sort=popular">
          View all
        </NuxtLink>
      </div>

      <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <RecipeCard v-for="recipe in featuredRecipes" :key="recipe.slug" :recipe="recipe" />
      </div>
    </section>

    <section v-if="featuredCategories.length > 0" class="mt-10" aria-labelledby="featured-categories-title">
      <div class="mb-5 flex items-end justify-between gap-4">
        <div>
          <p class="eyebrow">
            Shelves
          </p>
          <h2 id="featured-categories-title" class="section-title">
            Browse by mood
          </h2>
        </div>
        <NuxtLink class="font-black text-primary hover:underline" to="/categories">
          View all
        </NuxtLink>
      </div>

      <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <CategoryCard v-for="category in featuredCategories" :key="category.slug" :category="category" />
      </div>
    </section>
  </main>
</template>
