<script setup lang="ts">
import EmptyState from '../components/common/EmptyState.vue'
import PaginationNav from '../components/common/PaginationNav.vue'
import RecipeCard from '../components/recipes/RecipeCard.vue'
import { pageFromQuery, pageLocation, paginationState } from '../utils/pagination'

const api = useApi()
const auth = useAuth()
const route = useRoute()
const viewer = auth.currentUser.value ?? await auth.restoreSession()

if (!viewer) await navigateTo('/login?redirect=%2Ffavorites', { replace: true })

const page = computed(() => pageFromQuery(route.query))
const { data, pending, error } = await useAsyncData(`favorites:${route.fullPath}`, () => api.account.savedRecipes({ page: page.value }), { watch: [() => route.fullPath] })
const recipes = computed(() => data.value?.items ?? [])
const pagination = computed(() => paginationState({
  currentPage: data.value?.page ?? page.value,
  itemsOnPage: recipes.value.length,
  pageSize: data.value?.pageSize,
  totalItems: data.value?.totalItems ?? 0,
  totalPages: data.value?.totalPages ?? null
}))

useSeoMeta({ title: 'My favorites | WhatsInMyBar', description: 'Recipes you have saved for later.' })

function pageTo(value: number) {
  return pageLocation('/favorites', route.query, value)
}
</script>

<template>
  <main class="page-main">
    <header class="border-b pb-8">
      <p class="mb-2 text-xs font-medium uppercase tracking-wider text-muted-foreground">Account</p>
      <h1 class="page-heading">My favorites</h1>
      <p class="page-lead">Published recipes you have saved and are currently allowed to view.</p>
    </header>
    <div v-if="pending" class="mt-8 grid gap-x-5 gap-y-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" aria-label="Loading favorites"><div v-for="index in 8" :key="index" class="space-y-3"><div class="aspect-[4/3] animate-pulse rounded-md bg-muted" /><div class="h-5 w-2/3 animate-pulse rounded bg-muted" /></div></div>
    <EmptyState v-else-if="error" class="mt-8" title="Favorites could not be loaded" description="Try this page again in a moment." action-label="Reload" action-to="/favorites" />
    <EmptyState v-else-if="!recipes.length" class="mt-8" title="Nothing saved yet" description="Browse published recipes and save the ones you want to revisit." action-label="Browse recipes" action-to="/recipes" />
    <section v-else class="mt-8 grid gap-x-5 gap-y-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" aria-label="Favorite recipes"><RecipeCard v-for="recipe in recipes" :key="recipe.slug" :recipe="recipe" /></section>
    <PaginationNav v-if="!pending && !error" aria-label="Favorites pagination" :next-to="pageTo(pagination.nextPage)" :pagination="pagination" :previous-to="pageTo(pagination.previousPage)" />
  </main>
</template>
