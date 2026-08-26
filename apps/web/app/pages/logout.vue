<script setup lang="ts">
const auth = useAuth()

useSeoMeta({
  title: 'Log out | What\'s In My Bar'
})

onMounted(async () => {
  try {
    await auth.logout()
  } finally {
    await navigateTo('/login', { replace: true })
  }
})
</script>

<template>
  <main class="page-main grid min-h-[50vh] place-items-center">
    <section class="w-full max-w-sm rounded-md border bg-card p-6 text-center" aria-labelledby="logout-title">
      <h1 id="logout-title" class="text-xl font-semibold">
        Logging out
      </h1>
      <p class="mt-2 text-sm text-muted-foreground">
        Your session is being cleared.
      </p>
    </section>
  </main>
</template>
