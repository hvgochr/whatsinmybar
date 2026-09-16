<script setup lang="ts">
import type { NuxtError } from '#app'
import Wordmark from './components/brand/Wordmark.vue'
import UiButton from './components/ui/button/Button.vue'

const props = defineProps<{ error: NuxtError }>()
const heading = ref<HTMLElement | null>(null)
const statusCode = computed(() => Number(props.error.statusCode) || 500)
const title = computed(() => statusCode.value === 404
  ? 'Page not found'
  : statusCode.value === 403
    ? 'This page is not available'
    : 'Something went wrong')
const description = computed(() => statusCode.value === 404
  ? 'The page may have moved, or it may not be available to you.'
  : statusCode.value === 403
    ? 'You do not have access to this page. Its details have not been disclosed.'
    : 'The page could not be loaded. You can safely return to public recipes and try again later.')

useSeoMeta({
  title: () => `${title.value} | What's In My Bar`,
  robots: 'noindex, nofollow'
})

onMounted(() => heading.value?.focus())
</script>

<template>
  <div class="flex min-h-screen flex-col bg-background text-foreground">
    <header class="border-b">
      <div class="container-page flex h-16 items-center">
        <button type="button" aria-label="Return to WhatsInMyBar home" @click="clearError({ redirect: '/' })"><Wordmark /></button>
      </div>
    </header>
    <main class="container-page grid flex-1 place-items-center py-16">
      <section class="w-full max-w-2xl rounded-md border bg-card p-6 sm:p-10" :aria-labelledby="`error-${statusCode}-title`">
        <p class="text-sm font-medium text-muted-foreground">Error {{ statusCode }}</p>
        <h1 :id="`error-${statusCode}-title`" ref="heading" class="mt-2 text-3xl font-semibold tracking-tight focus:outline-none sm:text-4xl" tabindex="-1">{{ title }}</h1>
        <p class="mt-4 max-w-xl leading-7 text-muted-foreground">{{ description }}</p>
        <div class="mt-7 flex flex-wrap gap-3">
          <UiButton type="button" @click="clearError({ redirect: '/recipes' })">Browse recipes</UiButton>
          <UiButton type="button" variant="outline" @click="clearError({ redirect: '/' })">Back home</UiButton>
        </div>
      </section>
    </main>
  </div>
</template>
