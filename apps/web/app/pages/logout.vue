<script setup lang="ts">
const auth = useAuth()

useSeoMeta({
  title: 'Log out | What\'s In My Bar'
})

onMounted(async () => {
  auth.clearSession()
  await navigateTo('/login', { replace: true })
})
</script>

<template>
  <main class="page-shell">
    <section class="auth-panel" aria-labelledby="logout-title">
      <h1 id="logout-title" class="panel-title">
        Logging out
      </h1>
      <p class="panel-copy">
        Your session is being cleared.
      </p>
    </section>
  </main>
</template>
