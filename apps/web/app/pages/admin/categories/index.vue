<script setup lang="ts">
import AdminListToolbar from '../../../components/admin/AdminListToolbar.vue'
import AdminShell from '../../../components/admin/AdminShell.vue'
import AdminTableShell from '../../../components/admin/AdminTableShell.vue'
import EmptyState from '../../../components/common/EmptyState.vue'
import PaginationNav from '../../../components/common/PaginationNav.vue'
import UiButton from '../../../components/ui/button/Button.vue'
import { usePaginatedAdminList } from '../../../composables/usePaginatedAdminList'
import type { Category } from '../../../types/api'

definePageMeta({ layout: 'admin' })
await useRequireAdmin()
const api = useApi()
const { error, items: categories, nextTo, pagination, pending, previousTo } = await usePaginatedAdminList<Category>('admin:categories', '/admin/categories', api.admin.categories.list)
const search = ref('')
const filtered = computed(() => { const q = search.value.trim().toLowerCase(); return categories.value.filter(item => !q || `${item.name} ${item.slug} ${item.description ?? ''}`.toLowerCase().includes(q)) })
useSeoMeta({ title: 'Admin categories | What\'s In My Bar', description: 'Manage recipe categories.' })
</script>

<template>
  <AdminShell current="categories" title="Categories" description="Maintain the category shelves used by recipe discovery." action-label="Create category" action-to="/admin/categories/new">
    <AdminListToolbar v-model:search="search" placeholder="Name, slug, or description" />
    <div v-if="pending" class="grid min-h-48 place-items-center rounded-md border bg-card text-sm text-muted-foreground">Loading categories...</div>
    <EmptyState v-else-if="error" action-label="Reload" action-to="/admin/categories" description="Categories are unavailable right now." title="Categories could not be loaded" />
    <AdminTableShell v-else :empty="filtered.length === 0" empty-message="No categories match the current search on this page." label="Categories">
      <thead><tr><th>Name</th><th>Slug</th><th>Description</th><th>Updated</th><th class="text-right">Actions</th></tr></thead>
      <tbody><tr v-for="category in filtered" :key="category.slug"><td class="font-medium">{{ category.name }}</td><td class="text-muted-foreground">{{ category.slug }}</td><td class="max-w-xl text-muted-foreground">{{ category.description || 'No description' }}</td><td class="whitespace-nowrap text-muted-foreground">{{ category.updatedAt ? new Date(category.updatedAt).toLocaleDateString('en') : 'Unknown' }}</td><td><div class="flex justify-end"><UiButton as-child size="sm" variant="outline"><NuxtLink :to="`/admin/categories/${category.slug}/edit`">Edit</NuxtLink></UiButton></div></td></tr></tbody>
    </AdminTableShell>
    <PaginationNav v-if="!pending && !error" aria-label="Category list pagination" :next-to="nextTo" :pagination="pagination" :previous-to="previousTo" />
  </AdminShell>
</template>
