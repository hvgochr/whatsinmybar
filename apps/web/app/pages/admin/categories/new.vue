<script setup lang="ts">
import AdminCategoryForm from '../../../components/admin/AdminCategoryForm.vue'
import AdminShell from '../../../components/admin/AdminShell.vue'
definePageMeta({ layout: 'admin' })
await useRequireAdmin()
useSeoMeta({ title: 'Create category | Administration', robots: 'noindex, nofollow' })
</script>
<template><AdminShell current="categories" title="Create category" description="Add a category used to organize public recipe discovery."><AdminCategoryForm mode="create" /></AdminShell></template>
