<script setup lang="ts">
import { FavouriteIcon } from '@hugeicons/core-free-icons'
import { HugeiconsIcon } from '@hugeicons/vue'
import { toFormErrors } from '../../utils/api-errors'

const props = withDefaults(defineProps<{
  contrast?: boolean
  count: number
  favorited: boolean
  overlay?: boolean
  recipeSlug: string
}>(), {
  contrast: true,
  overlay: false
})

const emit = defineEmits<{
  updated: [state: { count: number, favorited: boolean }]
}>()

const api = useApi()
const auth = useAuth()
const notifications = useNotifications()
const count = ref(props.count)
const favorited = ref(props.favorited)
const pending = ref(false)
const label = computed(() => favorited.value ? `Remove ${props.recipeSlug} from favorites` : `Add ${props.recipeSlug} to favorites`)

watch(() => props.count, nextCount => { count.value = nextCount })
watch(() => props.favorited, nextFavorited => { favorited.value = nextFavorited })

async function toggleFavorite() {
  if (pending.value) return

  if (!auth.isAuthenticated.value) {
    await navigateTo(`/login?redirect=${encodeURIComponent(`/recipes/${props.recipeSlug}`)}`)
    return
  }

  pending.value = true
  try {
    const state = favorited.value
      ? await api.favorites.remove(props.recipeSlug)
      : await api.favorites.add(props.recipeSlug)

    count.value = state.favoriteCount
    favorited.value = state.favorited
    emit('updated', { count: state.favoriteCount, favorited: state.favorited })
    notifications.success(
      `favorite:${props.recipeSlug}`,
      state.favorited ? 'Added to favorites.' : 'Removed from favorites.'
    )
  } catch (error: unknown) {
    notifications.error(`favorite:${props.recipeSlug}`, toFormErrors(error).message ?? 'Favorite could not be updated.')
  } finally {
    pending.value = false
  }
}
</script>

<template>
  <div class="inline-flex" :class="overlay ? 'absolute top-3 right-3 z-20' : 'relative'">
    <button
      type="button"
      class="group/favorite inline-flex min-h-11 min-w-11 items-center justify-center gap-1.5 rounded-md px-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60"
      :class="contrast ? 'text-white focus-visible:ring-offset-black/60' : 'text-foreground focus-visible:ring-offset-background'"
      :aria-label="auth.isAuthenticated.value ? label : 'Log in to save this recipe'"
      :aria-pressed="auth.isAuthenticated.value ? favorited : undefined"
      :disabled="pending"
      :title="auth.isAuthenticated.value ? label : 'Log in to save this recipe'"
      @click.stop.prevent="toggleFavorite"
    >
      <HugeiconsIcon
        :icon="FavouriteIcon"
        :size="21"
        :stroke-width="1.9"
        :fill="favorited ? 'currentColor' : 'none'"
        aria-hidden="true"
      />
      <span class="tabular-nums">{{ count }}</span>
      <span class="sr-only">{{ pending ? 'Updating favorite' : '' }}</span>
    </button>
  </div>
</template>
