<script setup lang="ts">
import type { RecipeResource } from '../../types/api'
import { imageUrl } from '../../utils/public-content'

const props = defineProps<{
  recipe: Pick<RecipeResource, 'imagePath' | 'title' | 'containsAlcohol'>
  eager?: boolean
  variant?: 'card' | 'detail' | 'default'
}>()

const runtimeConfig = useRuntimeConfig()
const api = useApi()
const { currentUser } = useAuth()
const container = ref<HTMLElement>()
const objectUrl = ref<string>()
const failed = ref(false)
const src = computed(() => failed.value ? undefined : currentUser.value
  ? objectUrl.value
  : imageUrl(props.recipe.imagePath, runtimeConfig.public.apiBaseUrl))

// Public images remain SSR-rendered. Authenticated images use the existing
// Bearer/refresh client after mount, without exposing a token in an image URL.
let stop: (() => void) | undefined
onMounted(() => {
  stop = watch([() => props.recipe.imagePath, () => currentUser.value?.id, () => props.eager], ([path, viewer, eager], _, onCleanup) => {
    failed.value = false
    objectUrl.value = undefined
    const controller = new AbortController()
    let url: string | undefined
    let observer: IntersectionObserver | null = null
    let started = false
    onCleanup(() => {
      observer?.disconnect()
      controller.abort()
      if (url) URL.revokeObjectURL(url)
      objectUrl.value = undefined
    })
    if (!path || !viewer) return
    const load = async () => {
      if (started || controller.signal.aborted) return
      started = true
      observer?.disconnect()
      try {
        const blob = await api.recipes.imageFile(path, controller.signal)
        if (!controller.signal.aborted) {
          url = URL.createObjectURL(blob)
          objectUrl.value = url
        }
      } catch {
        if (!controller.signal.aborted) failed.value = true
      }
    }
    if (eager || typeof IntersectionObserver === 'undefined' || !container.value) {
      void load()
      return
    }
    // Native img lazy loading cannot defer the authenticated Blob request.
    observer = new IntersectionObserver((entries) => {
      if (entries.some(entry => entry.isIntersecting)) void load()
    }, { rootMargin: '200px' })
    observer.observe(container.value)
  }, { immediate: true })
})
onUnmounted(() => stop?.())
</script>

<template>
  <div
    ref="container"
    class="relative overflow-hidden bg-muted"
    :class="variant === 'card' ? 'aspect-[4/5]' : variant === 'detail' ? 'aspect-[4/3] sm:aspect-[16/10]' : 'aspect-[4/3]'"
  >
    <img
      v-if="src"
      :alt="recipe.title"
      class="h-full w-full object-cover"
      :loading="eager ? 'eager' : 'lazy'"
      :src="src"
      @error="failed = true"
    >
    <div v-else class="grid h-full place-items-center bg-muted p-6 text-center">
      <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
        Photo unavailable
      </p>
    </div>
  </div>
</template>
