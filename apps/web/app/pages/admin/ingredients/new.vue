<script setup lang="ts">
import AdminIngredientForm from '../../../components/admin/AdminIngredientForm.vue'
import AdminShell from '../../../components/admin/AdminShell.vue'
definePageMeta({ layout: 'admin' })
await useRequireAdmin()
useSeoMeta({ title: 'Create ingredient | Administration', robots: 'noindex, nofollow' })
</script>
<template><AdminShell current="ingredients" title="Create ingredient" description="Add an ingredient and its alcohol classification."><AdminIngredientForm mode="create" /></AdminShell></template>
