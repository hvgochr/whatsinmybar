<script setup lang="ts">
import UiSonner from './components/ui/sonner/Sonner.vue'

const auth = useAuth()
const session = useSessionState()
const retrying = ref(false)
async function retrySession() {
  retrying.value = true
  try {
    await auth.restoreSession()
    await refreshNuxtData()
  } catch {
    // Keep the degraded state and the public page available.
  } finally {
    retrying.value = false
  }
}
</script>

<template>
  <NuxtRouteAnnouncer />
  <UiSonner />
  <div v-if="auth.status.value === 'degraded'" role="status" class="border-b bg-muted px-4 py-2 text-center text-sm">
    Your session is temporarily unavailable. Public recipes are still accessible.
    <button class="ml-2 underline" :disabled="retrying" @click="retrySession">{{ retrying ? 'Retrying...' : 'Retry session' }}</button>
  </div>
  <NuxtLayout>
    <NuxtPage :page-key="route => `${route.path}:${session.revision.value}`" />
  </NuxtLayout>
</template>
