<script setup lang="ts">
import EmptyState from '../../components/common/EmptyState.vue'
import PublicPageHeader from '../../components/common/PublicPageHeader.vue'
import RecipeCard from '../../components/recipes/RecipeCard.vue'
import { collectionItems } from '../../utils/api-collections'
import { formatPublicDate, imageUrl, publicDescription, publicUrl } from '../../utils/public-content'

const api = useApi()
const route = useRoute()
const runtimeConfig = useRuntimeConfig()
const username = computed(() => String(route.params.username))

const { data: profile, error: profileError } = await useAsyncData(`profile:${username.value}`, () => api.profiles.get(username.value))

if (profileError.value) {
  throw createError({
    statusCode: errorStatus(profileError.value),
    statusMessage: 'Profile not found'
  })
}

const { data: recipesCollection, pending, error } = await useAsyncData(`profile:${username.value}:recipes`, () => api.recipes.list({
  author: username.value,
  sort: 'newest'
}))

const recipes = computed(() => collectionItems(recipesCollection.value))
const avatarSrc = computed(() => imageUrl(profile.value?.avatarPath, runtimeConfig.public.apiBaseUrl))
const description = computed(() => publicDescription(profile.value?.bio, `${username.value} shares cocktail recipes on What's In My Bar.`))
const canonicalUrl = computed(() => publicUrl(runtimeConfig.public.siteUrl, `/users/${username.value}`))

useSeoMeta({
  title: () => `${profile.value?.username ?? username.value} | What's In My Bar`,
  description: () => description.value,
  ogDescription: () => description.value,
  ogImage: () => avatarSrc.value,
  ogTitle: () => `${profile.value?.username ?? username.value} | What's In My Bar`,
  ogType: 'profile',
  ogUrl: () => canonicalUrl.value
})

useHead({
  link: [
    { href: canonicalUrl.value, rel: 'canonical' }
  ]
})

function errorStatus(error: unknown): number {
  const status = typeof error === 'object' && error !== null && 'status' in error
    ? Number((error as { status?: unknown }).status)
    : 500

  return status === 404 ? 404 : 500
}
</script>

<template>
  <main class="page-shell">
    <section v-if="profile" class="grid gap-7 py-8 md:grid-cols-[220px_minmax(0,1fr)] md:items-center md:py-12">
      <div class="avatar-preview size-36 text-5xl">
        <img v-if="avatarSrc" :alt="`${profile.username} avatar`" :src="avatarSrc">
        <span v-else>{{ profile.username.slice(0, 1).toUpperCase() }}</span>
      </div>

      <div>
        <p class="eyebrow">
          Public profile
        </p>
        <h1 class="m-0 text-4xl font-black leading-tight text-foreground md:text-6xl">
          {{ profile.username }}
        </h1>
        <p class="mt-4 max-w-2xl text-lg text-muted-foreground">
          {{ profile.bio || 'This bartender has not written a bio yet.' }}
        </p>
        <p class="mt-4 text-sm font-bold text-muted-foreground">
          Member since {{ formatPublicDate(profile.createdAt) }}
        </p>
      </div>
    </section>

    <PublicPageHeader
      v-else
      description="This public profile is unavailable."
      eyebrow="Public profile"
      title="Profile"
    />

    <h2 class="section-title">
      Published recipes
    </h2>

    <div v-if="pending" class="loading-panel">
      Loading profile recipes...
    </div>

    <EmptyState
      v-else-if="error"
      action-label="Browse recipes"
      action-to="/recipes"
      description="This profile's public recipes are unavailable right now."
      title="Recipes could not be loaded"
    />

    <EmptyState
      v-else-if="recipes.length === 0"
      action-label="Browse recipes"
      action-to="/recipes"
      description="No published recipes are attached to this profile yet."
      title="No public recipes yet"
    />

    <section v-else class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3" aria-label="Profile recipes">
      <RecipeCard v-for="recipe in recipes" :key="recipe.slug" :recipe="recipe" />
    </section>
  </main>
</template>
