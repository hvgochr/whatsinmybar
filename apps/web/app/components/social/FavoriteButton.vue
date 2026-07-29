<script setup lang="ts">
import { ApiRequestError } from '../../services/api-client'
import UiButton from '../ui/button/Button.vue'

const props = defineProps<{
  count: number
  favorited: boolean
  recipeSlug: string
}>()

const emit = defineEmits<{
  updated: [state: { count: number, favorited: boolean }]
}>()

const api = useApi()
const auth = useAuth()
const count = ref(props.count)
const favorited = ref(props.favorited)
const pending = ref(false)
const errorMessage = ref<string | null>(null)

watch(() => props.count, (nextCount) => {
  count.value = nextCount
})

watch(() => props.favorited, (nextFavorited) => {
  favorited.value = nextFavorited
})

async function toggleFavorite() {
  if (!auth.isAuthenticated.value) {
    return
  }

  pending.value = true
  errorMessage.value = null

  try {
    const state = favorited.value
      ? await api.favorites.remove(props.recipeSlug)
      : await api.favorites.add(props.recipeSlug)

    count.value = state.favoriteCount
    favorited.value = state.favorited
    emit('updated', { count: state.favoriteCount, favorited: state.favorited })
  } catch (error: unknown) {
    errorMessage.value = error instanceof ApiRequestError
      ? error.message
      : 'Favorite could not be updated.'
  } finally {
    pending.value = false
  }
}
</script>

<template>
  <div class="rounded-lg border border-border bg-card p-4 text-card-foreground">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="text-sm font-black text-foreground">
          {{ count }} saved
        </p>
        <p class="mt-1 text-sm text-muted-foreground">
          Keep recipes on your shelf for later.
        </p>
      </div>

      <UiButton v-if="auth.isAuthenticated.value" type="button" :variant="favorited ? 'outline' : 'default'" :disabled="pending" @click="toggleFavorite">
        {{ pending ? 'Saving...' : favorited ? 'Saved' : 'Save' }}
      </UiButton>
      <UiButton v-else as-child variant="outline">
        <NuxtLink :to="`/login?redirect=/recipes/${recipeSlug}`">
          Log in to save
        </NuxtLink>
      </UiButton>
    </div>

    <p v-if="errorMessage" class="mt-3 text-sm font-bold text-destructive">
      {{ errorMessage }}
    </p>
  </div>
</template>
