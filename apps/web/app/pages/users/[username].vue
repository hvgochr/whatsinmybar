<script setup lang="ts">
import EmptyState from '../../components/common/EmptyState.vue'
import FormAlert from '../../components/common/FormAlert.vue'
import PaginationNav from '../../components/common/PaginationNav.vue'
import UserAvatar from '../../components/common/UserAvatar.vue'
import RecipeCard from '../../components/recipes/RecipeCard.vue'
import ReportAction from '../../components/social/ReportAction.vue'
import UiButton from '../../components/ui/button/Button.vue'
import { ApiRequestError } from '../../services/api-client'
import type { RecipeResource } from '../../types/api'
import { collectionItems, collectionLastPage, collectionTotal } from '../../utils/api-collections'
import { paginationState } from '../../utils/pagination'
import { formatPublicDate, imageUrl, publicDescription, publicUrl } from '../../utils/public-content'

const api = useApi()
const auth = useAuth()
const notifications = useNotifications()
const route = useRoute()
const runtimeConfig = useRuntimeConfig()
const username = computed(() => String(route.params.username))

const { data: profile, error: profileError } = await useAsyncData(`profile:${username.value}`, () => api.profiles.get(username.value))

if (profileError.value) {
  throw createError({ statusCode: errorStatus(profileError.value), statusMessage: 'Profile not found' })
}

const isOwner = computed(() => auth.currentUser.value?.username === profile.value?.username)
const publicPage = computed(() => pageValue('page'))
const recipesPage = computed(() => pageValue('recipesPage'))
const favoritesPage = computed(() => pageValue('favoritesPage'))

const { data: publicRecipesData, pending: publicPending, error: publicError, refresh: refreshPublic } = await useAsyncData(`profile:${username.value}:public-recipes`, () => api.recipes.list({ author: username.value, sort: 'newest', page: publicPage.value }), { watch: [publicPage] })
const { data: ownedData, pending: ownedPending, error: ownedError, refresh: refreshOwned } = await useAsyncData(
  `profile:${username.value}:owned:${recipesPage.value}`,
  () => isOwner.value ? api.account.ownedRecipes({ page: recipesPage.value }) : Promise.resolve(null),
  { watch: [recipesPage] }
)
const { data: favoritesData, pending: favoritesPending, error: favoritesError } = await useAsyncData(
  `profile:${username.value}:favorites:${favoritesPage.value}`,
  () => isOwner.value ? api.account.savedRecipes({ page: favoritesPage.value }) : Promise.resolve(null),
  { watch: [favoritesPage] }
)

const publicRecipes = computed(() => collectionItems(publicRecipesData.value))
const ownedRecipes = computed(() => ownedData.value?.items ?? [])
const favorites = computed(() => favoritesData.value?.items ?? [])
const avatarSrc = computed(() => imageUrl(profile.value?.avatarPath, runtimeConfig.public.apiBaseUrl))
const description = computed(() => publicDescription(profile.value?.bio, `${username.value} shares cocktail recipes on What's In My Bar.`))
const canonicalUrl = computed(() => publicUrl(runtimeConfig.public.siteUrl, `/users/${username.value}`))
const actionPending = ref<Record<string, boolean>>({})
const actionError = ref<Record<string, string>>({})

const publicPagination = computed(() => paginationState({
  currentPage: publicPage.value,
  itemsOnPage: publicRecipes.value.length,
  totalItems: collectionTotal(publicRecipesData.value),
  totalPages: collectionLastPage(publicRecipesData.value)
}))
const ownedPagination = computed(() => paginationState({
  currentPage: ownedData.value?.page ?? recipesPage.value,
  itemsOnPage: ownedRecipes.value.length,
  pageSize: ownedData.value?.pageSize,
  totalItems: ownedData.value?.totalItems ?? 0,
  totalPages: ownedData.value?.totalPages ?? null
}))
const favoritesPagination = computed(() => paginationState({
  currentPage: favoritesData.value?.page ?? favoritesPage.value,
  itemsOnPage: favorites.value.length,
  pageSize: favoritesData.value?.pageSize,
  totalItems: favoritesData.value?.totalItems ?? 0,
  totalPages: favoritesData.value?.totalPages ?? null
}))

useSeoMeta({
  title: () => `${profile.value?.username ?? username.value} | What's In My Bar`,
  description: () => description.value,
  ogDescription: () => description.value,
  ogImage: () => avatarSrc.value,
  ogTitle: () => `${profile.value?.username ?? username.value} | What's In My Bar`,
  ogType: 'profile',
  ogUrl: () => canonicalUrl.value
})
useHead({ link: [{ href: canonicalUrl.value, rel: 'canonical' }] })

async function updateWorkflow(recipe: RecipeResource, action: 'archive' | 'publish') {
  if (actionPending.value[recipe.slug]) return
  actionPending.value[recipe.slug] = true
  actionError.value[recipe.slug] = ''

  try {
    Object.assign(recipe, action === 'publish' ? await api.recipes.publish(recipe.slug) : await api.recipes.archive(recipe.slug))
    await Promise.all([refreshOwned(), refreshPublic()])
    notifications.success(`profile-recipe:${recipe.slug}`, action === 'publish' ? 'Recipe published.' : 'Recipe archived.')
  } catch (caught: unknown) {
    actionError.value[recipe.slug] = caught instanceof ApiRequestError ? caught.message : 'The recipe status could not be updated.'
  } finally {
    actionPending.value[recipe.slug] = false
  }
}

function pageValue(key: string): number {
  const value = Number(Array.isArray(route.query[key]) ? route.query[key]?.[0] : route.query[key])
  return Number.isInteger(value) && value > 0 ? value : 1
}

function ownerPageTo(key: 'favoritesPage' | 'recipesPage' | 'page', page: number, hash: string) {
  const query = page <= 1
    ? Object.fromEntries(Object.entries(route.query).filter(([queryKey]) => queryKey !== key))
    : { ...route.query, [key]: String(page) }
  return { path: route.path, query, hash }
}

function errorStatus(error: unknown): number {
  const status = typeof error === 'object' && error !== null && 'status' in error ? Number((error as { status?: unknown }).status) : 500
  return status === 404 ? 404 : 500
}
</script>

<template>
  <main class="page-main">
    <section v-if="profile" class="grid gap-6 border-b pb-10 sm:grid-cols-[7rem_minmax(0,1fr)] sm:items-center">
      <UserAvatar class="size-28 rounded-full border text-3xl" eager :path="profile.avatarPath" sizes="7rem" :username="profile.username" />
      <div>
        <p class="mb-2 text-xs font-medium uppercase tracking-wider text-muted-foreground">{{ isOwner ? 'Your profile' : 'Public profile' }}</p>
        <div class="flex flex-wrap items-center gap-3"><h1 class="page-heading">{{ profile.username }}</h1><ReportAction v-if="!isOwner" :login-redirect="`/users/${profile.username}`" :target-id="profile.id" target-type="user" /></div>
        <p class="mt-3 max-w-2xl text-base leading-7 text-muted-foreground">{{ profile.bio || 'This bartender has not written a bio yet.' }}</p>
        <p class="mt-3 text-sm text-muted-foreground">Member since {{ formatPublicDate(profile.createdAt) }}</p>
        <nav v-if="isOwner" class="mt-5 flex flex-wrap gap-2" aria-label="Your profile collections">
          <UiButton as-child size="sm" variant="outline"><a href="#published-recipes">Published</a></UiButton>
          <UiButton as-child size="sm" variant="outline"><a href="#my-recipes">My recipes</a></UiButton>
          <UiButton as-child size="sm" variant="outline"><a href="#favorites">Favorites</a></UiButton>
          <UiButton as-child size="sm" variant="ghost"><NuxtLink to="/settings">Settings</NuxtLink></UiButton>
        </nav>
      </div>
    </section>

    <section id="published-recipes" class="scroll-mt-24 pt-10" aria-labelledby="published-recipes-title">
      <div><h2 id="published-recipes-title" class="section-heading">Published recipes</h2><p class="section-description">Recipes visible to everyone who can view them.</p></div>
      <div v-if="publicPending" class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4" aria-label="Loading published recipes"><div v-for="index in 4" :key="index" class="aspect-[4/5] animate-pulse rounded-md bg-muted" /></div>
      <EmptyState v-else-if="publicError" class="mt-5" action-label="Browse recipes" action-to="/recipes" description="Published recipes are unavailable right now." title="Recipes could not be loaded" />
      <EmptyState v-else-if="!publicRecipes.length" class="mt-5" action-label="Browse recipes" action-to="/recipes" description="No published recipes are attached to this profile yet." title="No public recipes yet" />
      <div v-else class="mt-5 grid gap-x-5 gap-y-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <div v-for="recipe in publicRecipes" :key="recipe.slug" class="grid gap-2"><RecipeCard :recipe="recipe" /><UiButton v-if="isOwner" as-child size="sm" variant="outline"><NuxtLink :to="`/recipes/${recipe.slug}/edit`">Edit recipe</NuxtLink></UiButton></div>
      </div>
      <PaginationNav v-if="!publicPending && !publicError" aria-label="Published recipes pagination" :next-to="ownerPageTo('page', publicPagination.nextPage, '#published-recipes')" :pagination="publicPagination" :previous-to="ownerPageTo('page', publicPagination.previousPage, '#published-recipes')" />
    </section>

    <section v-if="isOwner" id="my-recipes" class="scroll-mt-24 mt-14 border-t pt-10" aria-labelledby="my-recipes-title">
      <div class="flex flex-wrap items-end justify-between gap-4"><div><h2 id="my-recipes-title" class="section-heading">My recipes</h2><p class="section-description">Manage your published recipes, private drafts, and archives.</p></div><UiButton as-child size="sm"><NuxtLink to="/recipes/new">Create recipe</NuxtLink></UiButton></div>
      <div v-if="ownedPending" class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4" aria-label="Loading your recipes"><div v-for="index in 4" :key="index" class="aspect-[4/5] animate-pulse rounded-md bg-muted" /></div>
      <EmptyState v-else-if="ownedError" class="mt-5" description="Your recipes are unavailable right now." title="Recipes could not be loaded" />
      <EmptyState v-else-if="!ownedRecipes.length" class="mt-5" action-label="Create recipe" action-to="/recipes/new" description="Create a private draft and it will appear here." title="No recipes yet" />
      <div v-else class="mt-5 grid gap-x-5 gap-y-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <article v-for="recipe in ownedRecipes" :key="recipe.slug" class="grid content-start gap-2">
          <RecipeCard :recipe="recipe" show-status />
          <FormAlert v-if="actionError[recipe.slug]" :message="actionError[recipe.slug] ?? ''" tone="error" />
          <div class="grid grid-cols-2 gap-2">
            <UiButton as-child size="sm" variant="outline"><NuxtLink :to="`/recipes/${recipe.slug}/edit`">Edit</NuxtLink></UiButton>
            <UiButton v-if="recipe.status === 'draft'" size="sm" :disabled="actionPending[recipe.slug]" @click="updateWorkflow(recipe, 'publish')">{{ actionPending[recipe.slug] ? 'Publishing...' : 'Publish' }}</UiButton>
            <UiButton v-else-if="recipe.status === 'published'" size="sm" variant="outline" :disabled="actionPending[recipe.slug]" @click="updateWorkflow(recipe, 'archive')">{{ actionPending[recipe.slug] ? 'Archiving...' : 'Archive' }}</UiButton>
            <UiButton v-else as-child size="sm" variant="ghost"><NuxtLink :to="`/recipes/${recipe.slug}`">View</NuxtLink></UiButton>
          </div>
        </article>
      </div>
      <PaginationNav v-if="!ownedPending && !ownedError" aria-label="Your recipes pagination" :next-to="ownerPageTo('recipesPage', ownedPagination.nextPage, '#my-recipes')" :pagination="ownedPagination" :previous-to="ownerPageTo('recipesPage', ownedPagination.previousPage, '#my-recipes')" />
    </section>

    <section v-if="isOwner" id="favorites" class="scroll-mt-24 mt-14 border-t pt-10" aria-labelledby="favorites-title">
      <div><h2 id="favorites-title" class="section-heading">My favorites</h2><p class="section-description">Recipes you have saved for later.</p></div>
      <div v-if="favoritesPending" class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4" aria-label="Loading favorites"><div v-for="index in 4" :key="index" class="aspect-[4/5] animate-pulse rounded-md bg-muted" /></div>
      <EmptyState v-else-if="favoritesError" class="mt-5" description="Your favorites are unavailable right now." title="Favorites could not be loaded" />
      <EmptyState v-else-if="!favorites.length" class="mt-5" action-label="Browse recipes" action-to="/recipes" description="Save a recipe and it will appear here." title="Nothing saved yet" />
      <div v-else class="mt-5 grid gap-x-5 gap-y-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"><RecipeCard v-for="recipe in favorites" :key="recipe.slug" :recipe="recipe" /></div>
      <PaginationNav v-if="!favoritesPending && !favoritesError" aria-label="Favorites pagination" :next-to="ownerPageTo('favoritesPage', favoritesPagination.nextPage, '#favorites')" :pagination="favoritesPagination" :previous-to="ownerPageTo('favoritesPage', favoritesPagination.previousPage, '#favorites')" />
    </section>
  </main>
</template>
