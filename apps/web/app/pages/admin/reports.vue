<script setup lang="ts">
import AdminBadge from '../../components/admin/AdminBadge.vue'
import AdminPagination from '../../components/admin/AdminPagination.vue'
import AdminShell from '../../components/admin/AdminShell.vue'
import EmptyState from '../../components/common/EmptyState.vue'
import FormAlert from '../../components/common/FormAlert.vue'
import UiButton from '../../components/ui/button/Button.vue'
import { ApiRequestError } from '../../services/api-client'
import type { ModerationStatus, Report, ReportStatus } from '../../types/api'
import {
  adminModerationStatusOptions,
  adminReportReasonLabel,
  adminReportStatusOptions,
  adminStatusLabel
} from '../../utils/admin'
import { adminPageFromQuery } from '../../utils/admin-pagination'

await useRequireAdmin()

const api = useApi()
const route = useRoute()
const page = computed(() => adminPageFromQuery(route.query))
const { data, pending, error } = await useAsyncData(`admin:reports:${route.fullPath}`, () => api.admin.reports.list({ page: page.value }), { watch: [() => route.fullPath] })
const reports = ref<Report[]>([])
const rowPending = ref<Record<number, boolean>>({})
const rowError = ref<Record<number, string>>({})
const rowMessage = ref<Record<number, string>>({})

const userModerationOptions = computed(() => adminModerationStatusOptions.filter(option => option.value === 'visible' || option.value === 'removed'))
const openReports = computed(() => reports.value.filter(report => report.status === 'open'))

watch(data, (nextData) => {
  reports.value = nextData?.items ?? []
}, { immediate: true })

useSeoMeta({
  title: 'Admin reports | What\'s In My Bar',
  description: 'Review reports and apply moderation decisions.'
})

async function updateReport(report: Report, event: Event) {
  const form = new FormData(event.currentTarget as HTMLFormElement)
  rowPending.value[report.id] = true
  rowError.value[report.id] = ''
  rowMessage.value[report.id] = ''

  try {
    const moderationStatus = stringValue(form.get('moderationStatus'))
    const updatedReport = await api.admin.reports.update(report.id, {
      moderationStatus: moderationStatus ? moderationStatus as ModerationStatus : undefined,
      status: stringValue(form.get('status')) as ReportStatus
    })
    reports.value = reports.value.map(currentReport => currentReport.id === updatedReport.id ? updatedReport : currentReport)
    rowMessage.value[report.id] = 'Report updated.'
  } catch (error: unknown) {
    rowError.value[report.id] = error instanceof ApiRequestError ? error.message : 'Report could not be updated.'
  } finally {
    rowPending.value[report.id] = false
  }
}

function moderationOptions(report: Report) {
  return report.targetType === 'user' ? userModerationOptions.value : adminModerationStatusOptions
}

function stringValue(value: FormDataEntryValue | null): string {
  return typeof value === 'string' ? value : ''
}
</script>

<template>
  <AdminShell
    current="reports"
    description="Triage community reports and apply moderation to recipes, comments, or users."
    title="Reports and moderation"
  >
    <section class="mb-6 grid gap-4 sm:grid-cols-3">
      <article class="rounded-lg border border-border bg-card p-4">
        <p class="text-sm font-bold text-muted-foreground">
          Total reports
        </p>
        <p class="mt-2 text-3xl font-black">
          {{ data?.totalItems ?? 0 }}
        </p>
      </article>
      <article class="rounded-lg border border-border bg-card p-4">
        <p class="text-sm font-bold text-muted-foreground">
          Open on this page
        </p>
        <p class="mt-2 text-3xl font-black">
          {{ openReports.length }}
        </p>
      </article>
      <article class="rounded-lg border border-border bg-card p-4">
        <p class="text-sm font-bold text-muted-foreground">
          Reviewed on this page
        </p>
        <p class="mt-2 text-3xl font-black">
          {{ reports.length - openReports.length }}
        </p>
      </article>
    </section>

    <div v-if="pending" class="loading-panel">
      Loading reports...
    </div>

    <EmptyState
      v-else-if="error"
      action-label="Reload"
      action-to="/admin/reports"
      description="Reports are unavailable right now."
      title="Reports could not be loaded"
    />

    <section v-else class="grid gap-4" aria-label="Moderation reports">
      <form v-for="report in reports" :key="report.id" class="content-panel p-4" @submit.prevent="updateReport(report, $event)">
        <div class="grid gap-4 lg:grid-cols-[minmax(260px,1fr)_minmax(150px,0.35fr)_minmax(180px,0.45fr)_auto]">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <p class="font-black text-foreground">
                {{ report.targetType }} #{{ report.targetId }}
              </p>
              <AdminBadge :tone="report.status === 'open' ? 'warning' : report.status === 'resolved' ? 'success' : 'muted'">
                {{ adminStatusLabel(adminReportStatusOptions, report.status) }}
              </AdminBadge>
            </div>
            <p class="mt-2 text-sm text-muted-foreground">
              {{ adminReportReasonLabel(report.reason) }} by {{ report.reporterUsername }}
            </p>
            <p v-if="report.message" class="mt-3 rounded-lg border border-border bg-background p-3 text-sm text-muted-foreground">
              {{ report.message }}
            </p>
            <p class="mt-2 text-xs font-bold uppercase tracking-wide text-muted-foreground">
              Submitted {{ new Date(report.createdAt).toLocaleDateString('en') }}
              <span v-if="report.reviewedByUsername"> · reviewed by {{ report.reviewedByUsername }}</span>
            </p>
          </div>

          <label class="grid gap-2">
            <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">Report status</span>
            <select name="status" class="min-h-11 rounded-lg border border-input bg-background px-3 py-2 text-foreground" :disabled="rowPending[report.id]" :value="report.status">
              <option v-for="option in adminReportStatusOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label class="grid gap-2">
            <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">Apply moderation</span>
            <select name="moderationStatus" class="min-h-11 rounded-lg border border-input bg-background px-3 py-2 text-foreground" :disabled="rowPending[report.id]">
              <option value="">
                No content change
              </option>
              <option v-for="option in moderationOptions(report)" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <div class="grid items-end">
            <UiButton type="submit" :disabled="rowPending[report.id]">
              {{ rowPending[report.id] ? 'Saving...' : 'Save' }}
            </UiButton>
          </div>
        </div>

        <div class="mt-3">
          <FormAlert v-if="rowError[report.id]" :message="rowError[report.id] ?? ''" tone="error" />
          <FormAlert v-else-if="rowMessage[report.id]" :message="rowMessage[report.id] ?? ''" tone="success" />
        </div>
      </form>

      <p v-if="reports.length === 0" class="text-muted-foreground">
        No reports found.
      </p>
    </section>

    <AdminPagination
      v-if="!pending && !error && data"
      :page="data.page"
      path="/admin/reports"
      :total-items="data.totalItems"
      :total-pages="data.totalPages"
    />
  </AdminShell>
</template>
