<script setup lang="ts">
const auth = useAuth()

useSeoMeta({
  title: 'Log out | What\'s In My Bar'
})

const error = ref(false)
const pending = ref(false)
async function logout() {
  error.value = false
  pending.value = true
  try {
    await auth.logout()
    await navigateTo('/login', { replace: true })
  } catch {
    error.value = true
  } finally {
    pending.value = false
  }
}
onMounted(logout)
</script>

<template>
  <main class="page-main grid min-h-[50vh] place-items-center">
    <section class="w-full max-w-sm rounded-md border bg-card p-6 text-center" aria-labelledby="logout-title">
      <h1 id="logout-title" class="text-xl font-semibold">
        Logging out
      </h1>
      <p class="mt-2 text-sm text-muted-foreground">
        You are being logged out.
      </p>
      <p v-if="error" role="alert">Logout could not be confirmed. Please retry.</p>
      <button v-if="error" :disabled="pending" class="mt-4 underline" @click="logout">Retry logout</button>
    </section>
  </main>
</template>
