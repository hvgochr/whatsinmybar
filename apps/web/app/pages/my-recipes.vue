<script setup lang="ts">
import EmptyState from '../components/common/EmptyState.vue'
import FormAlert from '../components/common/FormAlert.vue'
import PaginationNav from '../components/common/PaginationNav.vue'
import RecipeImage from '../components/recipes/RecipeImage.vue'
import UiButton from '../components/ui/button/Button.vue'
import { ApiRequestError } from '../services/api-client'
import type { RecipeResource } from '../types/api'
import { pageFromQuery, pageLocation, paginationState } from '../utils/pagination'
import { formatRecipeMeta } from '../utils/public-content'

const api = useApi()
const auth = useAuth()
const route = useRoute()
const viewer = auth.currentUser.value ?? await auth.restoreSession()

if (!viewer) await navigateTo('/login?redirect=%2Fmy-recipes', { replace: true })

const page = computed(() => pageFromQuery(route.query))
const { data, pending, error } = await useAsyncData(`my-recipes:${route.fullPath}`, () => api.account.ownedRecipes({ page: page.value }), { watch: [() => route.fullPath] })
const recipes = computed(() => data.value?.items ?? [])
const pagination = computed(() => paginationState({
  currentPage: data.value?.page ?? page.value,
  itemsOnPage: recipes.value.length,
  pageSize: data.value?.pageSize,
  totalItems: data.value?.totalItems ?? 0,
  totalPages: data.value?.totalPages ?? null
}))
const actionPending = ref<Record<string, boolean>>({})
const actionError = ref<Record<string, string>>({})

useSeoMeta({ title: 'My recipes | WhatsInMyBar', description: 'Manage your recipe drafts, published recipes, and archives.' })

async function updateWorkflow(recipe: RecipeResource, action: 'archive' | 'publish') {
  if (actionPending.value[recipe.slug]) return
  actionPending.value[recipe.slug] = true
  actionError.value[recipe.slug] = ''
  try {
    Object.assign(recipe, action === 'publish' ? await api.recipes.publish(recipe.slug) : await api.recipes.archive(recipe.slug))
  } catch (caught: unknown) {
    actionError.value[recipe.slug] = caught instanceof ApiRequestError ? caught.message : 'The recipe status could not be updated.'
  } finally {
    actionPending.value[recipe.slug] = false
  }
}

function pageTo(value: number) {
  return pageLocation('/my-recipes', route.query, value)
}
</script>

<template>
  <main class="page-main">
    <header class="flex flex-col gap-5 border-b pb-8 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="mb-2 text-xs font-medium uppercase tracking-wider text-muted-foreground">Account</p>
        <h1 class="page-heading">My recipes</h1>
        <p class="page-lead">Manage drafts, published recipes, and archived recipes.</p>
      </div>
      <UiButton as-child><NuxtLink to="/recipes/new">Create recipe</NuxtLink></UiButton>
    </header>

    <div v-if="pending" class="mt-8 space-y-3" aria-label="Loading your recipes"><div v-for="index in 4" :key="index" class="h-32 animate-pulse rounded-md bg-muted" /></div>
    <EmptyState v-else-if="error" class="mt-8" title="Your recipes could not be loaded" description="Try this page again in a moment." action-label="Reload" action-to="/my-recipes" />
    <EmptyState v-else-if="!recipes.length" class="mt-8" title="No recipes yet" description="Create a private draft and it will appear here." action-label="Create recipe" action-to="/recipes/new" />

    <section v-else class="mt-8 divide-y border-y" aria-label="Your recipes">
      <article v-for="recipe in recipes" :key="recipe.slug" class="grid gap-5 py-5 sm:grid-cols-[9rem_minmax(0,1fr)_auto] sm:items-center">
        <div class="overflow-hidden rounded-md border"><RecipeImage :recipe="recipe" /></div>
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2"><h2 class="truncate text-lg font-semibold">{{ recipe.title }}</h2><span class="status-label">{{ recipe.status }}</span><span v-if="recipe.moderationStatus !== 'visible'" class="status-label">{{ recipe.moderationStatus.replace('_', ' ') }}</span></div>
          <p class="mt-1 text-sm text-muted-foreground">{{ formatRecipeMeta(recipe) || 'Recipe details incomplete' }}</p>
          <p v-if="recipe.description" class="mt-2 line-clamp-2 text-sm text-muted-foreground">{{ recipe.description }}</p>
          <FormAlert v-if="actionError[recipe.slug]" class="mt-3" :message="actionError[recipe.slug] ?? ''" tone="error" />
        </div>
        <div class="flex flex-wrap gap-2 sm:max-w-48 sm:justify-end">
          <UiButton as-child size="sm" variant="outline"><NuxtLink :to="`/recipes/${recipe.slug}`">View</NuxtLink></UiButton>
          <UiButton as-child size="sm" variant="outline"><NuxtLink :to="`/recipes/${recipe.slug}/edit`">Edit</NuxtLink></UiButton>
          <UiButton v-if="recipe.status === 'draft'" size="sm" :disabled="actionPending[recipe.slug]" @click="updateWorkflow(recipe, 'publish')">{{ actionPending[recipe.slug] ? 'Publishing…' : 'Publish' }}</UiButton>
          <UiButton v-if="recipe.status === 'published'" size="sm" variant="outline" :disabled="actionPending[recipe.slug]" @click="updateWorkflow(recipe, 'archive')">{{ actionPending[recipe.slug] ? 'Archiving…' : 'Archive' }}</UiButton>
        </div>
      </article>
    </section>

    <PaginationNav v-if="!pending && !error" aria-label="Your recipes pagination" :next-to="pageTo(pagination.nextPage)" :pagination="pagination" :previous-to="pageTo(pagination.previousPage)" />
  </main>
</template>
