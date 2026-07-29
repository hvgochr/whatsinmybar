<script setup lang="ts">
import type { Category, Ingredient } from '../../types/api'
import type { RecipeSearchState } from '../../utils/recipe-search'
import UiButton from '../ui/button/Button.vue'
import UiInput from '../ui/input/Input.vue'

defineProps<{
  activeFilters: Array<{ label: string, value: string }>
  categories: Category[]
  ingredients: Ingredient[]
  pending: boolean
  state: RecipeSearchState
}>()

const emit = defineEmits<{
  submit: [event: Event]
}>()

const selectClass = 'min-h-12 rounded-lg border border-input bg-background px-3.5 py-3 text-foreground shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background'
</script>

<template>
  <section class="mb-8 rounded-lg border border-border bg-card p-4 text-card-foreground shadow-sm md:p-5" aria-labelledby="recipe-search-title">
    <div class="mb-5 grid gap-2 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
      <div>
        <h2 id="recipe-search-title" class="section-title">
          Search the bar
        </h2>
        <p class="section-copy">
          Combine text search with category, ingredient, alcohol, author, and date filters.
        </p>
      </div>
      <NuxtLink class="font-black text-primary hover:underline" to="/recipes">
        Reset filters
      </NuxtLink>
    </div>

    <form class="grid gap-4" @submit.prevent="emit('submit', $event)">
      <div class="grid gap-3 lg:grid-cols-[minmax(240px,1.4fr)_repeat(4,minmax(150px,0.75fr))_auto]">
        <label class="grid gap-2">
          <span class="text-sm font-black">Search</span>
          <UiInput :default-value="state.q" name="q" type="search" />
        </label>

        <label class="grid gap-2">
          <span class="text-sm font-black">Category</span>
          <select :class="selectClass" name="category" :value="state.category">
            <option value="">
              Any category
            </option>
            <option v-for="category in categories" :key="category.slug" :value="category.slug">
              {{ category.name }}
            </option>
          </select>
        </label>

        <label class="grid gap-2">
          <span class="text-sm font-black">Ingredient</span>
          <select :class="selectClass" name="ingredient" :value="state.ingredient">
            <option value="">
              Any ingredient
            </option>
            <option v-for="ingredient in ingredients" :key="ingredient.slug" :value="ingredient.slug">
              {{ ingredient.name }}
            </option>
          </select>
        </label>

        <label class="grid gap-2">
          <span class="text-sm font-black">Alcohol</span>
          <select :class="selectClass" name="alcohol" :value="state.alcohol">
            <option value="">
              Any serve
            </option>
            <option value="with">
              With alcohol
            </option>
            <option value="without">
              Zero-proof
            </option>
          </select>
        </label>

        <label class="grid gap-2">
          <span class="text-sm font-black">Sort</span>
          <select :class="selectClass" name="sort" :value="state.sort">
            <option value="newest">
              Newest first
            </option>
            <option value="popular">
              Most saved
            </option>
            <option value="oldest">
              Oldest first
            </option>
          </select>
        </label>

        <div class="grid items-end">
          <UiButton type="submit" :disabled="pending">
            {{ pending ? 'Searching...' : 'Search' }}
          </UiButton>
        </div>
      </div>

      <details class="rounded-lg border border-border bg-background p-3">
        <summary class="cursor-pointer text-sm font-black text-foreground">
          Advanced filters
        </summary>
        <div class="mt-4 grid gap-3 md:grid-cols-2 lg:grid-cols-4">
          <label class="grid gap-2">
            <span class="text-sm font-black">Author username</span>
            <UiInput :default-value="state.author" name="author" type="search" />
          </label>
          <label class="grid gap-2">
            <span class="text-sm font-black">Minimum saves</span>
            <UiInput :default-value="state.minFavorites" min="1" name="minFavorites" type="number" />
          </label>
          <label class="grid gap-2">
            <span class="text-sm font-black">Published after</span>
            <UiInput :default-value="state.publishedAfter" name="publishedAfter" type="date" />
          </label>
          <label class="grid gap-2">
            <span class="text-sm font-black">Published before</span>
            <UiInput :default-value="state.publishedBefore" name="publishedBefore" type="date" />
          </label>
        </div>
      </details>
    </form>

    <div v-if="activeFilters.length > 0" class="mt-5 flex flex-wrap gap-2" aria-label="Active recipe filters">
      <span
        v-for="filter in activeFilters"
        :key="`${filter.label}:${filter.value}`"
        class="rounded-full border border-border bg-background px-3 py-1.5 text-xs font-black text-muted-foreground"
      >
        {{ filter.label }}: <span class="text-foreground">{{ filter.value }}</span>
      </span>
    </div>
  </section>
</template>
