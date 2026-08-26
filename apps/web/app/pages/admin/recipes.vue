<script setup lang="ts">
import AdminBadge from '../../components/admin/AdminBadge.vue'
import AdminShell from '../../components/admin/AdminShell.vue'
import EmptyState from '../../components/common/EmptyState.vue'
import FormAlert from '../../components/common/FormAlert.vue'
import PaginationNav from '../../components/common/PaginationNav.vue'
import UiButton from '../../components/ui/button/Button.vue'
import { usePaginatedAdminList } from '../../composables/usePaginatedAdminList'
import { ApiRequestError } from '../../services/api-client'
import type { AdminRecipe, ModerationStatus, RecipeStatus } from '../../types/api'
import { adminModerationStatusOptions, adminRecipeStatusOptions } from '../../utils/admin'

definePageMeta({ layout: 'admin' })

await useRequireAdmin()

const api = useApi()
const { error, items: recipes, nextTo, pagination, pending, previousTo } = await usePaginatedAdminList<AdminRecipe>(
  'admin:recipes',
  '/admin/recipes',
  api.admin.recipes.list
)
const rowPending = ref<Record<string, boolean>>({})
const rowMessage = ref<Record<string, string>>({})
const rowError = ref<Record<string, string>>({})

useSeoMeta({
  title: 'Admin recipes | What\'s In My Bar',
  description: 'Manage recipe workflow, alcohol override, moderation status, and soft deletion.'
})

async function updateRecipe(recipe: AdminRecipe, payload: Partial<AdminRecipe>) {
  rowPending.value[recipe.slug] = true
  rowMessage.value[recipe.slug] = ''
  rowError.value[recipe.slug] = ''

  try {
    const updatedRecipe = await api.admin.recipes.update(recipe.slug, payload)
    recipes.value = recipes.value.map(currentRecipe => currentRecipe.slug === recipe.slug ? { ...currentRecipe, ...updatedRecipe } : currentRecipe)
    rowMessage.value[recipe.slug] = 'Recipe updated.'
  } catch (error: unknown) {
    rowError.value[recipe.slug] = error instanceof ApiRequestError ? error.message : 'Recipe could not be updated.'
  } finally {
    rowPending.value[recipe.slug] = false
  }
}

function setRecipeStatus(recipe: AdminRecipe, event: Event) {
  return updateRecipe(recipe, {
    status: (event.target as HTMLSelectElement).value as RecipeStatus
  })
}

function setModerationStatus(recipe: AdminRecipe, event: Event) {
  return updateRecipe(recipe, {
    moderationStatus: (event.target as HTMLSelectElement).value as ModerationStatus
  })
}

function setAlcoholOverride(recipe: AdminRecipe, event: Event) {
  const value = (event.target as HTMLSelectElement).value

  return updateRecipe(recipe, {
    containsAlcoholOverride: value === '' ? null : value === 'true'
  })
}

function deleteRecipe(recipe: AdminRecipe) {
  return updateRecipe(recipe, { deleted: true })
}
</script>

<template>
  <AdminShell
    current="recipes"
    description="Review recipes across statuses and apply moderation or classification changes."
    title="Recipes"
  >
    <div v-if="pending" class="grid min-h-48 place-items-center rounded-md border bg-card text-sm text-muted-foreground">
      Loading recipes...
    </div>

    <EmptyState
      v-else-if="error"
      action-label="Reload"
      action-to="/admin/recipes"
      description="Recipes are unavailable right now."
      title="Recipes could not be loaded"
    />

    <div v-else class="grid gap-4">
      <article v-for="recipe in recipes" :key="recipe.slug" class="rounded-md border bg-card p-4">
        <div class="grid gap-4 lg:grid-cols-[minmax(240px,1fr)_repeat(4,minmax(140px,0.55fr))_auto] lg:items-start">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <NuxtLink class="font-medium text-foreground underline-offset-4 hover:underline" :to="`/recipes/${recipe.slug}`">
                {{ recipe.title }}
              </NuxtLink>
              <AdminBadge v-if="recipe.deleted" tone="danger">
                deleted
              </AdminBadge>
            </div>
            <p class="mt-1 text-sm text-muted-foreground">
              {{ recipe.authorUsername || 'Unknown author' }} · {{ recipe.favoriteCount }} saved
            </p>
          </div>

          <label class="grid gap-2">
            <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Status</span>
            <select class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground" :disabled="rowPending[recipe.slug]" :value="recipe.status" @change="setRecipeStatus(recipe, $event)">
              <option v-for="option in adminRecipeStatusOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label class="grid gap-2">
            <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Moderation</span>
            <select class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground" :disabled="rowPending[recipe.slug]" :value="recipe.moderationStatus" @change="setModerationStatus(recipe, $event)">
              <option v-for="option in adminModerationStatusOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label class="grid gap-2">
            <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Alcohol</span>
            <select class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground" :disabled="rowPending[recipe.slug]" :value="String(recipe.containsAlcoholOverride ?? '')" @change="setAlcoholOverride(recipe, $event)">
              <option value="">
                Computed: {{ recipe.containsAlcohol ? 'with alcohol' : 'zero-proof' }}
              </option>
              <option value="true">
                Force alcohol
              </option>
              <option value="false">
                Force zero-proof
              </option>
            </select>
          </label>

          <div class="grid gap-2">
            <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Dates</span>
            <p class="text-sm text-muted-foreground">
              Created {{ recipe.createdAt ? new Date(recipe.createdAt).toLocaleDateString('en') : 'unknown' }}
            </p>
            <p v-if="recipe.publishedAt" class="text-sm text-muted-foreground">
              Published {{ new Date(recipe.publishedAt).toLocaleDateString('en') }}
            </p>
          </div>

          <div class="grid gap-2">
            <UiButton type="button" variant="outline" :disabled="recipe.deleted || rowPending[recipe.slug]" @click="deleteRecipe(recipe)">
              Delete
            </UiButton>
          </div>
        </div>

        <div class="mt-3">
          <FormAlert v-if="rowError[recipe.slug]" :message="rowError[recipe.slug] ?? ''" tone="error" />
          <FormAlert v-else-if="rowMessage[recipe.slug]" :message="rowMessage[recipe.slug] ?? ''" tone="success" />
          <span v-else-if="rowPending[recipe.slug]" class="text-sm text-muted-foreground">Saving...</span>
        </div>
      </article>

      <p v-if="recipes.length === 0" class="text-muted-foreground">
        No recipes found.
      </p>
    </div>

    <PaginationNav
      v-if="!pending && !error"
      aria-label="Recipe administration pagination"
      :next-to="nextTo"
      :pagination="pagination"
      :previous-to="previousTo"
    />
  </AdminShell>
</template>
