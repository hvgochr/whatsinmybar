<script setup lang="ts">
import { Cancel01Icon, FilterHorizontalIcon } from '@hugeicons/core-free-icons'
import { HugeiconsIcon } from '@hugeicons/vue'
import type { RouteLocationRaw } from 'vue-router'
import type { Category, Ingredient } from '../../types/api'
import type { RecipeSearchState } from '../../utils/recipe-search'
import UiButton from '../ui/button/Button.vue'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetTrigger } from '../ui/sheet'

const props = withDefaults(defineProps<{
  activeFilters: Array<{ key: string, label: string, to: RouteLocationRaw, value: string }>
  categories: Category[]
  clearTo: RouteLocationRaw
  fixedCategory?: string
  ingredients: Ingredient[]
  pending: boolean
  resultCount: number
  state: RecipeSearchState
}>(), {
  fixedCategory: undefined
})

const emit = defineEmits<{
  apply: [state: RecipeSearchState]
}>()

const advancedOpen = ref(false)
const filters = reactive<RecipeSearchState>({ ...props.state })

watch(() => props.state, state => Object.assign(filters, state), { deep: true })

function apply() {
  advancedOpen.value = false
  emit('apply', { ...filters, category: props.fixedCategory ?? filters.category, page: 1 })
}
</script>

<template>
  <section class="mb-8 border-b pb-6" aria-label="Recipe collection controls">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <p class="pb-2 text-sm text-muted-foreground" aria-live="polite">
        {{ resultCount }} recipe{{ resultCount === 1 ? '' : 's' }}
      </p>

      <div class="flex flex-wrap items-end gap-2">
        <label class="grid min-w-44 gap-2">
          <span class="field-label">Sort order</span>
          <select v-model="filters.sort" class="control" :disabled="pending" @change="apply">
            <option value="newest">Newest first</option>
            <option value="popular">Most saved</option>
            <option value="oldest">Oldest first</option>
          </select>
        </label>

        <Sheet v-model:open="advancedOpen">
          <SheetTrigger as-child class="lg:hidden">
            <UiButton type="button" variant="outline" :disabled="pending">
              <HugeiconsIcon :icon="FilterHorizontalIcon" :size="17" :stroke-width="1.75" aria-hidden="true" />Advanced filters
            </UiButton>
          </SheetTrigger>
          <SheetContent side="right" class="w-[min(24rem,92vw)] overflow-y-auto">
            <SheetHeader class="text-left">
              <SheetTitle>Advanced filters</SheetTitle>
              <SheetDescription>Choose the recipes you want to see.</SheetDescription>
            </SheetHeader>
            <form class="mt-6 grid gap-4" @submit.prevent="apply">
              <label v-if="!fixedCategory" class="grid gap-2">
                <span class="field-label">Category</span>
                <select v-model="filters.category" class="control">
                  <option value="">Any category</option>
                  <option v-for="category in categories" :key="category.slug" :value="category.slug">{{ category.name }}</option>
                </select>
              </label>
              <label class="grid gap-2">
                <span class="field-label">Ingredient</span>
                <select v-model="filters.ingredient" class="control">
                  <option value="">Any ingredient</option>
                  <option v-for="ingredient in ingredients" :key="ingredient.slug" :value="ingredient.slug">{{ ingredient.name }}</option>
                </select>
              </label>
              <label class="grid gap-2">
                <span class="field-label">Alcohol preference</span>
                <select v-model="filters.alcohol" class="control">
                  <option value="">Any recipe</option>
                  <option value="with">With alcohol</option>
                  <option value="without">Zero-proof</option>
                </select>
              </label>
              <UiButton type="submit" :disabled="pending">{{ pending ? 'Applying...' : 'Apply filters' }}</UiButton>
            </form>
          </SheetContent>
        </Sheet>

        <NuxtLink v-if="activeFilters.length" class="inline-flex min-h-10 items-center px-2 text-sm font-medium underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring" :to="clearTo">Clear filters</NuxtLink>
      </div>
    </div>

    <details class="mt-4 hidden lg:block">
      <summary class="inline-flex min-h-10 cursor-pointer list-none items-center gap-2 rounded-md border bg-background px-4 text-sm font-medium hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring">
        <HugeiconsIcon :icon="FilterHorizontalIcon" :size="17" :stroke-width="1.75" aria-hidden="true" />Advanced filters
      </summary>
      <form class="mt-4 grid max-w-3xl gap-4 rounded-md border bg-card p-4 md:grid-cols-2" :class="fixedCategory ? '' : 'xl:grid-cols-3'" @submit.prevent="apply">
        <label v-if="!fixedCategory" class="grid gap-2">
          <span class="field-label">Category</span>
          <select v-model="filters.category" class="control">
            <option value="">Any category</option>
            <option v-for="category in categories" :key="category.slug" :value="category.slug">{{ category.name }}</option>
          </select>
        </label>
        <label class="grid gap-2">
          <span class="field-label">Ingredient</span>
          <select v-model="filters.ingredient" class="control">
            <option value="">Any ingredient</option>
            <option v-for="ingredient in ingredients" :key="ingredient.slug" :value="ingredient.slug">{{ ingredient.name }}</option>
          </select>
        </label>
        <label class="grid gap-2">
          <span class="field-label">Alcohol preference</span>
          <select v-model="filters.alcohol" class="control">
            <option value="">Any recipe</option>
            <option value="with">With alcohol</option>
            <option value="without">Zero-proof</option>
          </select>
        </label>
        <div class="flex items-end md:col-span-full">
          <UiButton type="submit" variant="outline" :disabled="pending">{{ pending ? 'Applying...' : 'Apply filters' }}</UiButton>
        </div>
      </form>
    </details>

    <div v-if="activeFilters.length" class="mt-4 flex flex-wrap items-center gap-2" aria-label="Active recipe filters">
      <NuxtLink
        v-for="filter in activeFilters"
        :key="filter.key"
        :to="filter.to"
        class="inline-flex min-h-8 items-center gap-1.5 rounded-sm border bg-background px-2 text-xs text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
        :aria-label="`Remove ${filter.label} filter`"
      >
        <span>{{ filter.label }}: <span class="text-foreground">{{ filter.value }}</span></span>
        <HugeiconsIcon :icon="Cancel01Icon" :size="14" :stroke-width="1.75" aria-hidden="true" />
      </NuxtLink>
    </div>
  </section>
</template>
