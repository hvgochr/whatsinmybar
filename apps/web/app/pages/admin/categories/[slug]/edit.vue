<script setup lang="ts">
import AdminCategoryForm from '../../../../components/admin/AdminCategoryForm.vue'
import AdminShell from '../../../../components/admin/AdminShell.vue'
import EmptyState from '../../../../components/common/EmptyState.vue'
definePageMeta({ layout: 'admin' })
await useRequireAdmin()
const api = useApi()
const route = useRoute()
const slug = String(route.params.slug)
const { data: category, pending, error } = await useAsyncData(`admin:category:${slug}`, () => api.categories.get(slug))
useSeoMeta({ title: 'Edit category | Administration', robots: 'noindex, nofollow' })
</script>
<template><AdminShell current="categories" title="Edit category" description="Update the category name, slug, and description."><div v-if="pending" class="grid min-h-48 place-items-center rounded-md border bg-card text-sm text-muted-foreground">Loading category...</div><EmptyState v-else-if="error || !category" title="Category unavailable" description="This category could not be loaded." action-label="Back to categories" action-to="/admin/categories" /><AdminCategoryForm v-else :category="category" mode="edit" /></AdminShell></template>
