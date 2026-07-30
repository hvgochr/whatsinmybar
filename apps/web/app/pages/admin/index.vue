<script setup lang="ts">
import AdminBadge from '../../components/admin/AdminBadge.vue'
import AdminMetric from '../../components/admin/AdminMetric.vue'
import AdminShell from '../../components/admin/AdminShell.vue'
import EmptyState from '../../components/common/EmptyState.vue'
import UiButton from '../../components/ui/button/Button.vue'
import { adminDashboardStats, adminReportReasonLabel } from '../../utils/admin'
import { formatPublicDate } from '../../utils/public-content'

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

const users = computed(() => usersData.value?.items ?? [])
const recipes = computed(() => recipesData.value?.items ?? [])
const categories = computed(() => categoriesData.value?.items ?? [])
const ingredients = computed(() => ingredientsData.value?.items ?? [])
const reports = computed(() => reportsData.value?.items ?? [])
const loading = computed(() => usersPending.value || recipesPending.value || reportsPending.value)
const failed = computed(() => Boolean(usersError.value || recipesError.value || reportsError.value))
const stats = computed(() => adminDashboardStats({
  categories: categories.value,
  ingredients: ingredients.value,
  recipes: recipes.value,
  reports: reports.value,
  users: users.value
}))
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
    <div v-if="loading" class="loading-panel">
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

      <section class="grid gap-6 lg:grid-cols-2">
        <div class="content-panel p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h2 class="section-title">
                Recent reports
              </h2>
              <p class="section-copy">
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
            <article v-for="report in recentReports" :key="report.id" class="rounded-lg border border-border bg-background p-4">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="font-black">
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

        <div class="content-panel p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h2 class="section-title">
                Recent recipes
              </h2>
              <p class="section-copy">
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
            <article v-for="recipe in recentRecipes" :key="recipe.slug" class="rounded-lg border border-border bg-background p-4">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <NuxtLink class="font-black text-foreground hover:text-primary" :to="`/recipes/${recipe.slug}`">
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
