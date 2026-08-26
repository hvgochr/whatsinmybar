<script setup lang="ts">
import EmptyState from '../../components/common/EmptyState.vue'
import PublicPageHeader from '../../components/common/PublicPageHeader.vue'
import RecipeCard from '../../components/recipes/RecipeCard.vue'
import ReportAction from '../../components/social/ReportAction.vue'
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
  <main class="page-main">
    <section v-if="profile" class="grid gap-6 border-b pb-10 sm:grid-cols-[7rem_minmax(0,1fr)] sm:items-center">
      <div class="grid size-28 place-items-center overflow-hidden rounded-full border bg-muted text-3xl font-semibold">
        <img v-if="avatarSrc" class="h-full w-full object-cover" :alt="`${profile.username}'s avatar`" :src="avatarSrc">
        <span v-else aria-hidden="true">{{ profile.username.slice(0, 1).toUpperCase() }}</span>
      </div>

      <div>
        <p class="mb-2 text-xs font-medium uppercase tracking-wider text-muted-foreground">Public profile</p>
        <h1 class="page-heading">
          {{ profile.username }}
        </h1>
        <p class="mt-3 max-w-2xl text-base leading-7 text-muted-foreground">
          {{ profile.bio || 'This bartender has not written a bio yet.' }}
        </p>
        <p class="mt-3 text-sm text-muted-foreground">
          Member since {{ formatPublicDate(profile.createdAt) }}
        </p>
        <div class="mt-4 max-w-sm">
          <ReportAction
            :login-redirect="`/users/${profile.username}`"
            :target-id="profile.id"
            target-type="user"
          />
        </div>
      </div>
    </section>

    <PublicPageHeader
      v-else
      description="This public profile is unavailable."
      eyebrow="Public profile"
      title="Profile"
    />

    <h2 class="section-heading mt-10">
      Published recipes
    </h2>

    <div v-if="pending" class="mt-5 grid gap-x-5 gap-y-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" aria-label="Loading profile recipes">
      <div v-for="index in 4" :key="index" class="space-y-3"><div class="aspect-[4/3] animate-pulse rounded-md bg-muted" /><div class="h-5 w-2/3 animate-pulse rounded bg-muted" /></div>
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

    <section v-else class="mt-5 grid gap-x-5 gap-y-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" aria-label="Profile recipes">
      <RecipeCard v-for="recipe in recipes" :key="recipe.slug" :recipe="recipe" />
    </section>
  </main>
</template>
