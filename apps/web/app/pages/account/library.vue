<script setup lang="ts">
import EmptyState from '../../components/common/EmptyState.vue'
import FormAlert from '../../components/common/FormAlert.vue'
import PaginationNav from '../../components/common/PaginationNav.vue'
import UiButton from '../../components/ui/button/Button.vue'
import { ApiRequestError } from '../../services/api-client'
import type { RecipeResource } from '../../types/api'
import { pageFromQuery, pageLocation, paginationState } from '../../utils/pagination'

const api = useApi()
const auth = useAuth()
const route = useRoute()
const viewer = auth.currentUser.value ?? await auth.restoreSession()

if (!viewer) {
  await navigateTo('/login?redirect=%2Faccount%2Flibrary', { replace: true })
}

const ownedPage = computed(() => pageFromQuery(route.query, 'ownedPage'))
const savedPage = computed(() => pageFromQuery(route.query, 'savedPage'))
const [{ data: ownedData, pending: ownedPending, error: ownedError }, { data: savedData, pending: savedPending, error: savedError, refresh: refreshSaved }] = await Promise.all([
  useAsyncData(`account:owned:${route.fullPath}`, () => api.account.ownedRecipes({ page: ownedPage.value }), { watch: [() => route.fullPath] }),
  useAsyncData(`account:saved:${route.fullPath}`, () => api.account.savedRecipes({ page: savedPage.value }), { watch: [() => route.fullPath] })
])

const ownedRecipes = computed(() => ownedData.value?.items ?? [])
const savedRecipes = computed(() => savedData.value?.items ?? [])
const ownedPagination = computed(() => paginationState({
  currentPage: ownedData.value?.page ?? ownedPage.value,
  itemsOnPage: ownedRecipes.value.length,
  pageSize: ownedData.value?.pageSize,
  totalItems: ownedData.value?.totalItems ?? 0,
  totalPages: ownedData.value?.totalPages ?? null
}))
const savedPagination = computed(() => paginationState({
  currentPage: savedData.value?.page ?? savedPage.value,
  itemsOnPage: savedRecipes.value.length,
  pageSize: savedData.value?.pageSize,
  totalItems: savedData.value?.totalItems ?? 0,
  totalPages: savedData.value?.totalPages ?? null
}))
const actionPending = ref<Record<string, boolean>>({})
const actionError = ref<Record<string, string>>({})
const isMinor = computed(() => {
  if (!viewer) {
    return false
  }

  const today = new Date()
  const threshold = `${today.getFullYear() - 18}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`

  return viewer.birthDate > threshold
})

useSeoMeta({
  title: 'Recipe library | What\'s In My Bar',
  description: 'Manage your recipes and saved cocktail recipes.'
})

async function updateWorkflow(recipe: RecipeResource, action: 'archive' | 'publish') {
  actionPending.value[recipe.slug] = true
  actionError.value[recipe.slug] = ''

  try {
    const workflow = action === 'publish'
      ? await api.recipes.publish(recipe.slug)
      : await api.recipes.archive(recipe.slug)
    Object.assign(recipe, workflow)
  } catch (error: unknown) {
    actionError.value[recipe.slug] = errorMessage(error, `Recipe could not be ${action === 'publish' ? 'published' : 'archived'}.`)
  } finally {
    actionPending.value[recipe.slug] = false
  }
}

async function removeSaved(recipe: RecipeResource) {
  actionPending.value[recipe.slug] = true
  actionError.value[recipe.slug] = ''

  try {
    await api.favorites.remove(recipe.slug)
    await refreshSaved()
  } catch (error: unknown) {
    actionError.value[recipe.slug] = errorMessage(error, 'Recipe could not be removed from your saved recipes.')
  } finally {
    actionPending.value[recipe.slug] = false
  }
}

function errorMessage(error: unknown, fallback: string): string {
  return error instanceof ApiRequestError ? error.message : fallback
}

function ownedPageTo(page: number) {
  return pageLocation('/account/library', route.query, page, 'ownedPage')
}

function savedPageTo(page: number) {
  return pageLocation('/account/library', route.query, page, 'savedPage')
}
</script>

<template>
  <main class="page-shell">
    <section aria-labelledby="library-title">
      <p class="eyebrow">Account</p>
      <h1 id="library-title" class="page-title">Your recipe library</h1>
      <p class="page-copy">Return to drafts, manage published recipes, and keep saved cocktails close at hand.</p>
      <div class="mt-5 flex flex-wrap gap-3">
        <UiButton as-child>
          <NuxtLink to="/recipes/new">Create recipe</NuxtLink>
        </UiButton>
        <UiButton as-child variant="outline">
          <NuxtLink to="/account">Account settings</NuxtLink>
        </UiButton>
      </div>
    </section>

    <section aria-labelledby="owned-recipes-title">
      <div class="mb-4">
        <p class="eyebrow">Created by you</p>
        <h2 id="owned-recipes-title" class="section-title">Your recipes</h2>
        <p class="section-copy">Drafts, published recipes, and archived recipes all stay available here.</p>
      </div>

      <div v-if="ownedPending" class="loading-panel" aria-live="polite">Loading your recipes...</div>
      <EmptyState
        v-else-if="ownedError"
        title="Your recipes could not be loaded"
        description="Try this page again in a moment."
        action-label="Reload"
        action-to="/account/library"
      />
      <EmptyState
        v-else-if="ownedRecipes.length === 0"
        title="No recipes yet"
        description="Create a private draft and it will appear here."
        action-label="Create recipe"
        action-to="/recipes/new"
      />
      <div v-else class="grid gap-4">
        <article v-for="recipe in ownedRecipes" :key="recipe.slug" class="content-panel p-5">
          <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-xl font-black">{{ recipe.title }}</h3>
                <span class="rounded-full bg-secondary px-2.5 py-1 text-xs font-black uppercase text-secondary-foreground">{{ recipe.status }}</span>
              </div>
              <p class="mt-2 text-sm text-muted-foreground">{{ recipe.description }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
              <UiButton as-child size="sm" variant="outline">
                <NuxtLink :to="`/recipes/${recipe.slug}`">View</NuxtLink>
              </UiButton>
              <UiButton as-child size="sm" variant="outline">
                <NuxtLink :to="`/recipes/${recipe.slug}/edit`">Edit</NuxtLink>
              </UiButton>
              <UiButton v-if="recipe.status === 'draft'" size="sm" :disabled="actionPending[recipe.slug]" @click="updateWorkflow(recipe, 'publish')">
                {{ actionPending[recipe.slug] ? 'Publishing...' : 'Publish' }}
              </UiButton>
              <UiButton v-if="recipe.status === 'published'" size="sm" variant="outline" :disabled="actionPending[recipe.slug]" @click="updateWorkflow(recipe, 'archive')">
                {{ actionPending[recipe.slug] ? 'Archiving...' : 'Archive' }}
              </UiButton>
            </div>
          </div>
          <FormAlert v-if="actionError[recipe.slug]" class="mt-4" :message="actionError[recipe.slug] ?? ''" tone="error" />
        </article>
      </div>

      <PaginationNav
        v-if="!ownedPending && !ownedError"
        aria-label="Your recipes pagination"
        :next-to="ownedPageTo(ownedPagination.nextPage)"
        :pagination="ownedPagination"
        :previous-to="ownedPageTo(ownedPagination.previousPage)"
      />
    </section>

    <section aria-labelledby="saved-recipes-title">
      <div class="mb-4">
        <p class="eyebrow">Saved for later</p>
        <h2 id="saved-recipes-title" class="section-title">Saved recipes</h2>
        <p class="section-copy">Recipes you save from discovery and detail pages appear here.</p>
      </div>

      <FormAlert
        v-if="isMinor"
        class="mb-4"
        message="Alcoholic saved recipes are hidden because of your account's age restriction."
        tone="error"
      />
      <div v-if="savedPending" class="loading-panel" aria-live="polite">Loading saved recipes...</div>
      <EmptyState
        v-else-if="savedError"
        title="Saved recipes could not be loaded"
        description="Try this page again in a moment."
        action-label="Reload"
        action-to="/account/library"
      />
      <EmptyState
        v-else-if="savedRecipes.length === 0"
        title="Nothing saved yet"
        description="Browse published recipes and save the ones you want to revisit."
        action-label="Browse recipes"
        action-to="/recipes"
      />
      <div v-else class="grid gap-4 md:grid-cols-2">
        <article v-for="recipe in savedRecipes" :key="recipe.slug" class="content-panel p-5">
          <h3 class="text-xl font-black">
            <NuxtLink class="hover:text-primary" :to="`/recipes/${recipe.slug}`">{{ recipe.title }}</NuxtLink>
          </h3>
          <p class="mt-2 text-sm text-muted-foreground">{{ recipe.description }}</p>
          <div class="mt-4 flex flex-wrap gap-2">
            <UiButton as-child size="sm" variant="outline">
              <NuxtLink :to="`/recipes/${recipe.slug}`">View</NuxtLink>
            </UiButton>
            <UiButton size="sm" variant="outline" :disabled="actionPending[recipe.slug]" @click="removeSaved(recipe)">
              {{ actionPending[recipe.slug] ? 'Removing...' : 'Remove saved recipe' }}
            </UiButton>
          </div>
          <FormAlert v-if="actionError[recipe.slug]" class="mt-4" :message="actionError[recipe.slug] ?? ''" tone="error" />
        </article>
      </div>

      <PaginationNav
        v-if="!savedPending && !savedError"
        aria-label="Saved recipes pagination"
        :next-to="savedPageTo(savedPagination.nextPage)"
        :pagination="savedPagination"
        :previous-to="savedPageTo(savedPagination.previousPage)"
      />
    </section>
  </main>
</template>
