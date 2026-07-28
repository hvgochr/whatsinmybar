<script setup lang="ts">
import type { RecipeResource } from '../../types/api'
import { categoryName, categorySlug, formatPublicDate, formatRecipeMeta, publicDescription } from '../../utils/public-content'
import RecipeImage from './RecipeImage.vue'

const props = defineProps<{
  recipe: RecipeResource
}>()

const categories = computed(() => props.recipe.categories?.slice(0, 2) ?? [])
const meta = computed(() => formatRecipeMeta(props.recipe))
</script>

<template>
  <article class="group overflow-hidden rounded-lg border border-border bg-card text-card-foreground shadow-sm transition-shadow hover:shadow-warm">
    <NuxtLink :to="`/recipes/${recipe.slug}`" class="block focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background">
      <RecipeImage :recipe="recipe" />
    </NuxtLink>

    <div class="grid gap-4 p-4">
      <div>
        <div class="mb-2 flex flex-wrap gap-2">
          <NuxtLink
            v-for="category in categories"
            :key="categorySlug(category)"
            class="rounded-full bg-secondary px-2.5 py-1 text-xs font-black text-secondary-foreground hover:bg-secondary/82"
            :to="`/categories/${categorySlug(category)}`"
          >
            {{ categoryName(category) }}
          </NuxtLink>
        </div>

        <h2 class="m-0 text-xl font-black leading-tight">
          <NuxtLink class="hover:text-primary" :to="`/recipes/${recipe.slug}`">
            {{ recipe.title }}
          </NuxtLink>
        </h2>
        <p class="mt-2 line-clamp-3 text-sm text-muted-foreground">
          {{ publicDescription(recipe.description, 'A community cocktail recipe ready to discover.', 132) }}
        </p>
      </div>

      <dl class="grid gap-2 text-sm text-muted-foreground">
        <div v-if="meta" class="flex flex-wrap gap-1">
          <dt class="sr-only">
            Recipe details
          </dt>
          <dd>{{ meta }}</dd>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div v-if="recipe.authorUsername" class="flex gap-1">
            <dt>By</dt>
            <dd>
              <NuxtLink class="font-bold text-foreground hover:text-primary" :to="`/users/${recipe.authorUsername}`">
                {{ recipe.authorUsername }}
              </NuxtLink>
            </dd>
          </div>
          <div class="flex gap-1 font-bold text-foreground">
            <dt class="sr-only">
              Favorites
            </dt>
            <dd>{{ recipe.favoriteCount }} saved</dd>
          </div>
        </div>
        <div v-if="recipe.publishedAt" class="flex gap-1">
          <dt>Published</dt>
          <dd>{{ formatPublicDate(recipe.publishedAt) }}</dd>
        </div>
      </dl>
    </div>
  </article>
</template>
