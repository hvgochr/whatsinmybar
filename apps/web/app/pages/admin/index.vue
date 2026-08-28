<script setup lang="ts">
import AdminBadge from '../../components/admin/AdminBadge.vue'
import AdminMetric from '../../components/admin/AdminMetric.vue'
import AdminShell from '../../components/admin/AdminShell.vue'
import EmptyState from '../../components/common/EmptyState.vue'
import UiButton from '../../components/ui/button/Button.vue'
import { adminReportReasonLabel } from '../../utils/admin'
import { formatPublicDate } from '../../utils/public-content'

definePageMeta({ layout: 'admin' })

await useRequireAdmin()

const api = useApi()

const [
  { data: usersData, pending: usersPending, error: usersError },
  { data: recipesData, pending: recipesPending, error: recipesError },
  { data: categoriesData },
  { data: ingredientsData },
  { data: reportsData, pending: reportsPending, error: reportsError }
] = await Promise.all([
  useAsyncData('admin:dashboard:users', () => api.admin.users.list()),
  useAsyncData('admin:dashboard:recipes', () => api.admin.recipes.list()),
  useAsyncData('admin:dashboard:categories', () => api.admin.categories.list()),
  useAsyncData('admin:dashboard:ingredients', () => api.admin.ingredients.list()),
  useAsyncData('admin:dashboard:reports', () => api.admin.reports.list())
])

const recipes = computed(() => recipesData.value?.items ?? [])
const reports = computed(() => reportsData.value?.items ?? [])
const loading = computed(() => usersPending.value || recipesPending.value || reportsPending.value)
const failed = computed(() => Boolean(usersError.value || recipesError.value || reportsError.value))
const stats = computed(() => [
  { label: 'Users', value: usersData.value?.totalItems ?? 0 },
  { label: 'Recipes', value: recipesData.value?.totalItems ?? 0 },
  { label: 'Open reports on page', value: reports.value.filter(report => report.status === 'open').length },
  { label: 'Taxonomy entries', value: (categoriesData.value?.totalItems ?? 0) + (ingredientsData.value?.totalItems ?? 0) }
])
const recentReports = computed(() => reports.value.slice(0, 5))
const recentRecipes = computed(() => recipes.value.slice(0, 5))

useSeoMeta({
  title: 'Admin dashboard | What\'s In My Bar',
  description: 'Operational dashboard for What\'s In My Bar administrators.'
})
</script>

<template>
  <AdminShell
    current="dashboard"
    description="Monitor current activity and jump into moderation, catalog, and account workflows."
    title="Dashboard"
  >
    <div v-if="loading" class="grid min-h-48 place-items-center rounded-md border bg-card text-sm text-muted-foreground">
      Loading admin dashboard...
    </div>

    <EmptyState
      v-else-if="failed"
      action-label="Reload"
      action-to="/admin"
      description="Admin data is unavailable right now."
      title="Dashboard could not be loaded"
    />

    <div v-else class="grid gap-6">
      <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Admin metrics">
        <AdminMetric v-for="stat in stats" :key="stat.label" :label="stat.label" :value="stat.value" />
      </section>

      <section class="rounded-md border bg-card p-5" aria-labelledby="admin-shortcuts-title">
        <h2 id="admin-shortcuts-title" class="text-lg font-semibold">Management shortcuts</h2>
        <div class="mt-4 flex flex-wrap gap-2">
          <UiButton as-child variant="outline" size="sm"><NuxtLink to="/admin/reports">Moderation queue</NuxtLink></UiButton>
          <UiButton as-child variant="outline" size="sm"><NuxtLink to="/admin/comments">Reported comments</NuxtLink></UiButton>
          <UiButton as-child variant="outline" size="sm"><NuxtLink to="/admin/users">Manage users</NuxtLink></UiButton>
          <UiButton as-child variant="outline" size="sm"><NuxtLink to="/admin/recipes">Manage recipes</NuxtLink></UiButton>
          <UiButton as-child variant="outline" size="sm"><NuxtLink to="/admin/ingredients">Edit ingredients</NuxtLink></UiButton>
        </div>
      </section>

      <section class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-md border bg-card p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h2 class="text-lg font-semibold">
                Recent reports
              </h2>
              <p class="mt-1 text-sm text-muted-foreground">
                Latest moderation queue items.
              </p>
            </div>
            <UiButton as-child variant="outline" size="sm">
              <NuxtLink to="/admin/reports">
                View all
              </NuxtLink>
            </UiButton>
          </div>

          <div v-if="recentReports.length > 0" class="mt-5 grid gap-3">
            <article v-for="report in recentReports" :key="report.id" class="rounded-md border bg-background p-4">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="font-medium">
                  {{ report.targetType }} #{{ report.targetId }}
                </p>
                <AdminBadge :tone="report.status === 'open' ? 'warning' : 'muted'">
                  {{ report.status }}
                </AdminBadge>
              </div>
              <p class="mt-2 text-sm text-muted-foreground">
                {{ adminReportReasonLabel(report.reason) }} by {{ report.reporterUsername }}
              </p>
            </article>
          </div>
          <p v-else class="mt-5 text-muted-foreground">
            No reports in the queue.
          </p>
        </div>

        <div class="rounded-md border bg-card p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h2 class="text-lg font-semibold">
                Recent recipes
              </h2>
              <p class="mt-1 text-sm text-muted-foreground">
                Latest recipe records across all statuses.
              </p>
            </div>
            <UiButton as-child variant="outline" size="sm">
              <NuxtLink to="/admin/recipes">
                View all
              </NuxtLink>
            </UiButton>
          </div>

          <div v-if="recentRecipes.length > 0" class="mt-5 grid gap-3">
            <article v-for="recipe in recentRecipes" :key="recipe.slug" class="rounded-md border bg-background p-4">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <NuxtLink class="font-medium text-foreground underline-offset-4 hover:underline" :to="`/recipes/${recipe.slug}`">
                  {{ recipe.title }}
                </NuxtLink>
                <AdminBadge :tone="recipe.deleted ? 'danger' : recipe.status === 'published' ? 'success' : 'muted'">
                  {{ recipe.deleted ? 'deleted' : recipe.status }}
                </AdminBadge>
              </div>
              <p class="mt-2 text-sm text-muted-foreground">
                {{ recipe.authorUsername || 'Unknown author' }}<span v-if="recipe.createdAt"> · {{ formatPublicDate(recipe.createdAt) }}</span>
              </p>
            </article>
          </div>
          <p v-else class="mt-5 text-muted-foreground">
            No recipes found.
          </p>
        </div>
      </section>
    </div>
  </AdminShell>
</template>
