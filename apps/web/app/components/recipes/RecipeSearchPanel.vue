<script setup lang="ts">
import { Cancel01Icon, FilterHorizontalIcon } from '@hugeicons/core-free-icons'
import { HugeiconsIcon } from '@hugeicons/vue'
import type { RouteLocationRaw } from 'vue-router'
import type { Category, Ingredient } from '../../types/api'
import type { RecipeSearchState } from '../../utils/recipe-search'
import UiButton from '../ui/button/Button.vue'
import UiInput from '../ui/input/Input.vue'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetTrigger } from '../ui/sheet'

const props = defineProps<{
  activeFilters: Array<{ key: string, label: string, to: RouteLocationRaw, value: string }>
  categories: Category[]
  ingredients: Ingredient[]
  pending: boolean
  state: RecipeSearchState
}>()

const emit = defineEmits<{
  apply: [state: RecipeSearchState]
}>()

const advancedOpen = ref(false)
const filters = reactive<RecipeSearchState>({ ...props.state })

watch(() => props.state, state => Object.assign(filters, state), { deep: true })

function apply() {
  advancedOpen.value = false
  emit('apply', { ...filters, page: 1 })
}

</script>

<template>
  <section class="mb-8 border-b pb-6" aria-labelledby="recipe-filters-title">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h2 id="recipe-filters-title" class="text-base font-semibold">Filter recipes</h2>
        <p class="mt-1 text-sm text-muted-foreground">Narrow the current collection without leaving the page.</p>
      </div>
      <NuxtLink v-if="activeFilters.length" class="text-sm font-medium underline-offset-4 hover:underline" to="/recipes">Clear filters</NuxtLink>
    </div>

    <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(12rem,1fr)_minmax(10rem,0.7fr)_minmax(9rem,0.55fr)_auto_auto] lg:items-end" @submit.prevent="apply">
      <label class="grid gap-2"><span class="field-label">Search within recipes</span><UiInput v-model="filters.q" type="search" placeholder="Name or keyword" /></label>
      <label class="grid gap-2"><span class="field-label">Category</span><select v-model="filters.category" class="control"><option value="">Any category</option><option v-for="category in categories" :key="category.slug" :value="category.slug">{{ category.name }}</option></select></label>
      <label class="grid gap-2"><span class="field-label">Sort</span><select v-model="filters.sort" class="control"><option value="newest">Newest first</option><option value="popular">Most saved</option><option value="oldest">Oldest first</option></select></label>
      <UiButton type="submit" :disabled="pending">{{ pending ? 'Applying...' : 'Apply' }}</UiButton>

      <Sheet v-model:open="advancedOpen">
        <SheetTrigger as-child class="lg:hidden"><UiButton type="button" variant="outline"><HugeiconsIcon :icon="FilterHorizontalIcon" :size="17" :stroke-width="1.75" aria-hidden="true" />Advanced</UiButton></SheetTrigger>
        <SheetContent side="right" class="w-[min(24rem,92vw)] overflow-y-auto">
          <SheetHeader class="text-left"><SheetTitle>Advanced filters</SheetTitle><SheetDescription>Filter by ingredients, alcohol preference, author, popularity, or date.</SheetDescription></SheetHeader>
          <div class="mt-6 grid gap-4">
            <label class="grid gap-2"><span class="field-label">Ingredient</span><select v-model="filters.ingredient" class="control"><option value="">Any ingredient</option><option v-for="ingredient in ingredients" :key="ingredient.slug" :value="ingredient.slug">{{ ingredient.name }}</option></select></label>
            <label class="grid gap-2"><span class="field-label">Alcohol</span><select v-model="filters.alcohol" class="control"><option value="">Any serve</option><option value="with">With alcohol</option><option value="without">Zero-proof</option></select></label>
            <label class="grid gap-2"><span class="field-label">Author username</span><UiInput v-model="filters.author" type="search" /></label>
            <label class="grid gap-2"><span class="field-label">Minimum saves</span><UiInput v-model="filters.minFavorites" min="1" type="number" /></label>
            <label class="grid gap-2"><span class="field-label">Published after</span><UiInput v-model="filters.publishedAfter" type="date" /></label>
            <label class="grid gap-2"><span class="field-label">Published before</span><UiInput v-model="filters.publishedBefore" type="date" /></label>
            <UiButton type="button" :disabled="pending" @click="apply">{{ pending ? 'Applying...' : 'Apply filters' }}</UiButton>
          </div>
        </SheetContent>
      </Sheet>
    </form>

    <details class="mt-4 hidden rounded-md border bg-card p-4 lg:block">
      <summary class="cursor-pointer text-sm font-medium"><span class="inline-flex items-center gap-2"><HugeiconsIcon :icon="FilterHorizontalIcon" :size="17" :stroke-width="1.75" aria-hidden="true" />Advanced filters</span></summary>
      <form class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-6 xl:items-end" @submit.prevent="apply">
        <label class="grid gap-2"><span class="field-label">Ingredient</span><select v-model="filters.ingredient" class="control"><option value="">Any ingredient</option><option v-for="ingredient in ingredients" :key="ingredient.slug" :value="ingredient.slug">{{ ingredient.name }}</option></select></label>
        <label class="grid gap-2"><span class="field-label">Alcohol</span><select v-model="filters.alcohol" class="control"><option value="">Any serve</option><option value="with">With alcohol</option><option value="without">Zero-proof</option></select></label>
        <label class="grid gap-2"><span class="field-label">Author</span><UiInput v-model="filters.author" type="search" /></label>
        <label class="grid gap-2"><span class="field-label">Minimum saves</span><UiInput v-model="filters.minFavorites" min="1" type="number" /></label>
        <label class="grid gap-2"><span class="field-label">Published after</span><UiInput v-model="filters.publishedAfter" type="date" /></label>
        <label class="grid gap-2"><span class="field-label">Published before</span><UiInput v-model="filters.publishedBefore" type="date" /></label>
        <UiButton class="xl:col-start-6" type="submit" variant="outline" :disabled="pending">Apply advanced filters</UiButton>
      </form>
    </details>

    <div v-if="activeFilters.length" class="mt-4 flex flex-wrap items-center gap-2" aria-label="Active recipe filters">
      <NuxtLink
        v-for="filter in activeFilters"
        :key="filter.key"
        :to="filter.to"
        class="inline-flex min-h-8 items-center gap-1.5 rounded-sm border bg-background px-2 text-xs text-muted-foreground hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        :aria-label="`Remove ${filter.label} filter`"
      >
        <span>{{ filter.label }}: <span class="text-foreground">{{ filter.value }}</span></span>
        <HugeiconsIcon :icon="Cancel01Icon" :size="14" :stroke-width="1.75" aria-hidden="true" />
      </NuxtLink>
    </div>
  </section>
</template>
