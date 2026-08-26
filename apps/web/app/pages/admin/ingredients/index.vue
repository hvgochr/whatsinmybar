<script setup lang="ts">
import AdminBadge from '../../../components/admin/AdminBadge.vue'
import AdminListToolbar from '../../../components/admin/AdminListToolbar.vue'
import AdminShell from '../../../components/admin/AdminShell.vue'
import AdminTableShell from '../../../components/admin/AdminTableShell.vue'
import EmptyState from '../../../components/common/EmptyState.vue'
import PaginationNav from '../../../components/common/PaginationNav.vue'
import UiButton from '../../../components/ui/button/Button.vue'
import { usePaginatedAdminList } from '../../../composables/usePaginatedAdminList'
import type { Ingredient } from '../../../types/api'

definePageMeta({ layout: 'admin' })
await useRequireAdmin()
const api = useApi()
const { error, items: ingredients, nextTo, pagination, pending, previousTo } = await usePaginatedAdminList<Ingredient>('admin:ingredients', '/admin/ingredients', api.admin.ingredients.list)
const search = ref('')
const alcoholFilter = ref<'all' | 'with' | 'without'>('all')
const filtered = computed(() => { const q = search.value.trim().toLowerCase(); return ingredients.value.filter(item => (!q || `${item.name} ${item.slug}`.toLowerCase().includes(q)) && (alcoholFilter.value === 'all' || (alcoholFilter.value === 'with' ? item.containsAlcohol : !item.containsAlcohol))) })
useSeoMeta({ title: 'Admin ingredients | What\'s In My Bar', description: 'Manage ingredients and alcohol classification.' })
</script>

<template>
  <AdminShell current="ingredients" title="Ingredients" description="Maintain ingredient naming and alcohol classification." action-label="Create ingredient" action-to="/admin/ingredients/new">
    <AdminListToolbar v-model:search="search" placeholder="Name or slug"><label class="grid gap-2"><span class="field-label">Classification</span><select v-model="alcoholFilter" class="control min-w-44"><option value="all">All ingredients</option><option value="with">Contains alcohol</option><option value="without">Zero-proof</option></select></label></AdminListToolbar>
    <div v-if="pending" class="grid min-h-48 place-items-center rounded-md border bg-card text-sm text-muted-foreground">Loading ingredients...</div>
    <EmptyState v-else-if="error" action-label="Reload" action-to="/admin/ingredients" description="Ingredients are unavailable right now." title="Ingredients could not be loaded" />
    <AdminTableShell v-else :empty="filtered.length === 0" empty-message="No ingredients match the current filters on this page." label="Ingredients">
      <thead><tr><th>Name</th><th>Slug</th><th>Classification</th><th>Updated</th><th class="text-right">Actions</th></tr></thead>
      <tbody><tr v-for="ingredient in filtered" :key="ingredient.slug"><td class="font-medium">{{ ingredient.name }}</td><td class="text-muted-foreground">{{ ingredient.slug }}</td><td><AdminBadge :tone="ingredient.containsAlcohol ? 'warning' : 'success'">{{ ingredient.containsAlcohol ? 'contains alcohol' : 'zero-proof' }}</AdminBadge></td><td class="whitespace-nowrap text-muted-foreground">{{ ingredient.updatedAt ? new Date(ingredient.updatedAt).toLocaleDateString('en') : 'Unknown' }}</td><td><div class="flex justify-end"><UiButton as-child size="sm" variant="outline"><NuxtLink :to="`/admin/ingredients/${ingredient.slug}/edit`">Edit</NuxtLink></UiButton></div></td></tr></tbody>
    </AdminTableShell>
    <PaginationNav v-if="!pending && !error" aria-label="Ingredient list pagination" :next-to="nextTo" :pagination="pagination" :previous-to="previousTo" />
  </AdminShell>
</template>
