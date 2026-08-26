<script setup lang="ts">
const auth = useAuth()
const viewer = auth.currentUser.value ?? await auth.restoreSession()
await navigateTo(viewer ? `/users/${viewer.username}#favorites` : '/login?redirect=%2Ffavorites', { replace: true })
</script>

<template><main class="page-main"><p class="text-sm text-muted-foreground">Opening your favorites...</p></main></template>
