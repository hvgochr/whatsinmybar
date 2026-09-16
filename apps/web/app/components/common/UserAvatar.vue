<script setup lang="ts">
import { imageUrl } from '../../utils/public-content'

const props = withDefaults(defineProps<{
  eager?: boolean
  path?: string | null
  sizes?: string
  username?: string | null
}>(), {
  eager: false,
  path: null,
  sizes: '4rem',
  username: null
})

const runtimeConfig = useRuntimeConfig()
const failed = ref(false)
const source = computed(() => failed.value ? undefined : imageUrl(props.path, runtimeConfig.public.apiBaseUrl))
const initial = computed(() => props.username?.slice(0, 1).toUpperCase() || '?')

watch(() => props.path, () => {
  failed.value = false
})
</script>

<template>
  <span class="grid overflow-hidden bg-muted text-center font-semibold">
    <img
      v-if="source"
      :alt="username ? `${username}'s avatar` : 'Member avatar'"
      class="size-full object-cover"
      decoding="async"
      height="512"
      :loading="eager ? 'eager' : 'lazy'"
      :sizes="sizes"
      :src="source"
      width="512"
      @error="failed = true"
    >
    <span v-else class="grid size-full place-items-center" aria-hidden="true">{{ initial }}</span>
  </span>
</template>
