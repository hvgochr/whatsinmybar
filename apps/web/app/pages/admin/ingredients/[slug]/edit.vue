<script setup lang="ts">
import AdminIngredientForm from '../../../../components/admin/AdminIngredientForm.vue'
import AdminShell from '../../../../components/admin/AdminShell.vue'
import EmptyState from '../../../../components/common/EmptyState.vue'
import { collectionItems } from '../../../../utils/api-collections'
definePageMeta({ layout: 'admin' })
await useRequireAdmin()
const api = useApi()
const route = useRoute()
const slug = String(route.params.slug)
const { data, pending, error } = await useAsyncData(`admin:ingredient:${slug}`, () => api.ingredients.list())
const ingredient = computed(() => collectionItems(data.value).find(item => item.slug === slug) ?? null)
useSeoMeta({ title: 'Edit ingredient | Administration', robots: 'noindex, nofollow' })
</script>
<template><AdminShell current="ingredients" title="Edit ingredient" description="Update ingredient naming and alcohol classification."><div v-if="pending" class="grid min-h-48 place-items-center rounded-md border bg-card text-sm text-muted-foreground">Loading ingredient...</div><EmptyState v-else-if="error || !ingredient" title="Ingredient unavailable" description="This ingredient could not be loaded." action-label="Back to ingredients" action-to="/admin/ingredients" /><AdminIngredientForm v-else :ingredient="ingredient" mode="edit" /></AdminShell></template>
