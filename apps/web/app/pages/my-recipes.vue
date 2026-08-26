<script setup lang="ts">
const auth = useAuth()
const viewer = auth.currentUser.value ?? await auth.restoreSession()
await navigateTo(viewer ? `/users/${viewer.username}#my-recipes` : '/login?redirect=%2Fmy-recipes', { replace: true })
</script>

<template><main class="page-main"><p class="text-sm text-muted-foreground">Opening your recipes...</p></main></template>
