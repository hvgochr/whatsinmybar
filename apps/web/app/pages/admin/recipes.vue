<script setup lang="ts">
import AdminBadge from '../../components/admin/AdminBadge.vue'
import AdminListToolbar from '../../components/admin/AdminListToolbar.vue'
import AdminShell from '../../components/admin/AdminShell.vue'
import AdminTableShell from '../../components/admin/AdminTableShell.vue'
import DestructiveConfirm from '../../components/common/DestructiveConfirm.vue'
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
const notifications = useNotifications()
const { error, items: recipes, nextTo, pagination, pending, previousTo } = await usePaginatedAdminList<AdminRecipe>('admin:recipes', '/admin/recipes', api.admin.recipes.list)
const search = ref('')
const statusFilter = ref<'all' | RecipeStatus>('all')
const rowPending = ref<Record<string, boolean>>({})
const rowError = ref<Record<string, string>>({})
const deleteOpen = ref<Record<string, boolean>>({})
const filteredRecipes = computed(() => {
  const query = search.value.trim().toLowerCase()
  return recipes.value.filter(recipe => (!query || `${recipe.title} ${recipe.authorUsername ?? ''}`.toLowerCase().includes(query)) && (statusFilter.value === 'all' || recipe.status === statusFilter.value))
})

useSeoMeta({ title: 'Admin recipes | What\'s In My Bar', description: 'Manage recipe workflow, classification, and moderation.' })

async function updateRecipe(recipe: AdminRecipe, payload: Partial<AdminRecipe>) {
  rowPending.value[recipe.slug] = true
  rowError.value[recipe.slug] = ''
  try {
    const updated = await api.admin.recipes.update(recipe.slug, payload)
    recipes.value = recipes.value.map(current => current.slug === recipe.slug ? { ...current, ...updated } : current)
    notifications.success(`admin-recipe:${recipe.slug}`, payload.deleted ? 'Recipe deleted.' : 'Recipe updated.')
    if (payload.deleted) deleteOpen.value[recipe.slug] = false
  } catch (caught: unknown) {
    rowError.value[recipe.slug] = caught instanceof ApiRequestError ? caught.message : 'Recipe could not be updated.'
  } finally {
    rowPending.value[recipe.slug] = false
  }
}
</script>

<template>
  <AdminShell current="recipes" description="Review recipes across statuses and apply moderation or classification changes." title="Recipes">
    <AdminListToolbar v-model:search="search" placeholder="Title or author">
      <label class="grid gap-2"><span class="field-label">Status</span><select v-model="statusFilter" class="control min-w-40"><option value="all">All statuses</option><option v-for="option in adminRecipeStatusOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
    </AdminListToolbar>
    <div v-if="pending" class="grid min-h-48 place-items-center rounded-md border bg-card text-sm text-muted-foreground">Loading recipes...</div>
    <EmptyState v-else-if="error" action-label="Reload" action-to="/admin/recipes" description="Recipes are unavailable right now." title="Recipes could not be loaded" />
    <AdminTableShell v-else :empty="filteredRecipes.length === 0" empty-message="No recipes match the current filters on this page." label="Recipes">
      <thead><tr><th>Recipe</th><th>Status</th><th>Moderation</th><th>Alcohol</th><th>Created</th><th class="text-right">Actions</th></tr></thead>
      <tbody>
        <tr v-for="recipe in filteredRecipes" :key="recipe.slug">
          <td><div class="flex flex-wrap items-center gap-2"><NuxtLink class="font-medium underline-offset-4 hover:underline" :to="`/recipes/${recipe.slug}`">{{ recipe.title }}</NuxtLink><AdminBadge v-if="recipe.deleted" tone="danger">deleted</AdminBadge></div><p class="mt-1 text-muted-foreground">{{ recipe.authorUsername || 'Unknown author' }} · {{ recipe.favoriteCount }} saved</p></td>
          <td><select class="control min-w-32" :disabled="rowPending[recipe.slug]" :value="recipe.status" @change="updateRecipe(recipe, { status: ($event.target as HTMLSelectElement).value as RecipeStatus })"><option v-for="option in adminRecipeStatusOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></td>
          <td><select class="control min-w-40" :disabled="rowPending[recipe.slug]" :value="recipe.moderationStatus" @change="updateRecipe(recipe, { moderationStatus: ($event.target as HTMLSelectElement).value as ModerationStatus })"><option v-for="option in adminModerationStatusOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></td>
          <td><select class="control min-w-48" :disabled="rowPending[recipe.slug]" :value="String(recipe.containsAlcoholOverride ?? '')" @change="updateRecipe(recipe, { containsAlcoholOverride: ($event.target as HTMLSelectElement).value === '' ? null : ($event.target as HTMLSelectElement).value === 'true' })"><option value="">Computed: {{ recipe.containsAlcohol ? 'with alcohol' : 'zero-proof' }}</option><option value="true">Force alcohol</option><option value="false">Force zero-proof</option></select></td>
          <td class="whitespace-nowrap text-muted-foreground">{{ recipe.createdAt ? new Date(recipe.createdAt).toLocaleDateString('en') : 'Unknown' }}</td>
          <td>
            <div class="flex justify-end gap-2"><UiButton as-child size="sm" variant="outline"><NuxtLink :to="`/recipes/${recipe.slug}/edit`">Edit</NuxtLink></UiButton><DestructiveConfirm v-if="!recipe.deleted" v-model:open="deleteOpen[recipe.slug]" confirm-label="Delete recipe" :description="`“${recipe.title}” will be removed from public and personal collections.`" :error="rowError[recipe.slug]" :pending="rowPending[recipe.slug]" title="Delete this recipe?" @confirm="updateRecipe(recipe, { deleted: true })"><template #trigger><UiButton size="sm" variant="outline">Delete</UiButton></template></DestructiveConfirm></div>
            <FormAlert v-if="rowError[recipe.slug] && !deleteOpen[recipe.slug]" class="mt-2" :message="rowError[recipe.slug] ?? ''" tone="error" />
          </td>
        </tr>
      </tbody>
    </AdminTableShell>
    <PaginationNav v-if="!pending && !error" aria-label="Recipe administration pagination" :next-to="nextTo" :pagination="pagination" :previous-to="previousTo" />
  </AdminShell>
</template>
