<script setup lang="ts">
import AdminBadge from '../../components/admin/AdminBadge.vue'
import AdminListToolbar from '../../components/admin/AdminListToolbar.vue'
import AdminShell from '../../components/admin/AdminShell.vue'
import AdminTableShell from '../../components/admin/AdminTableShell.vue'
import EmptyState from '../../components/common/EmptyState.vue'
import FormAlert from '../../components/common/FormAlert.vue'
import PaginationNav from '../../components/common/PaginationNav.vue'
import UiButton from '../../components/ui/button/Button.vue'
import { usePaginatedAdminList } from '../../composables/usePaginatedAdminList'
import { ApiRequestError } from '../../services/api-client'
import type { ModerationStatus, Report, ReportStatus } from '../../types/api'
import { adminModerationStatusOptions, adminReportStatusOptions, adminStatusLabel } from '../../utils/admin'

definePageMeta({ layout: 'admin' })
await useRequireAdmin()
const api = useApi()
const { error, items: reports, nextTo, pagination, pending, previousTo } = await usePaginatedAdminList<Report>('admin:comments', '/admin/comments', api.admin.reports.list)
const search = ref('')
const statusFilter = ref<'all' | ReportStatus>('all')
const rowPending = ref<Record<number, boolean>>({})
const rowError = ref<Record<number, string>>({})
const rowMessage = ref<Record<number, string>>({})
const commentReports = computed(() => { const q = search.value.trim().toLowerCase(); return reports.value.filter(report => report.targetType === 'comment' && (statusFilter.value === 'all' || report.status === statusFilter.value) && (!q || `${report.targetId} ${report.reporterUsername} ${report.message ?? ''}`.toLowerCase().includes(q))) })

useSeoMeta({ title: 'Admin comments | WhatsInMyBar', description: 'Moderate comments surfaced through community reports.' })

async function updateReport(report: Report, event: Event) {
  const form = new FormData(event.currentTarget as HTMLFormElement)
  rowPending.value[report.id] = true
  rowError.value[report.id] = ''
  rowMessage.value[report.id] = ''
  try {
    const moderationStatus = String(form.get('moderationStatus') ?? '')
    const updated = await api.admin.reports.update(report.id, { moderationStatus: moderationStatus ? moderationStatus as ModerationStatus : undefined, status: String(form.get('status')) as ReportStatus })
    reports.value = reports.value.map(item => item.id === updated.id ? updated : item)
    rowMessage.value[report.id] = 'Comment report updated.'
  } catch (caught: unknown) {
    rowError.value[report.id] = caught instanceof ApiRequestError ? caught.message : 'The comment report could not be updated.'
  } finally {
    rowPending.value[report.id] = false
  }
}
</script>

<template>
  <AdminShell current="comments" title="Comments" description="Review reported comments and apply moderation decisions.">
    <AdminListToolbar v-model:search="search" placeholder="Comment ID, reporter, or report text"><label class="grid gap-2"><span class="field-label">Report status</span><select v-model="statusFilter" class="control min-w-40"><option value="all">All statuses</option><option v-for="option in adminReportStatusOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></label></AdminListToolbar>
    <div v-if="pending" class="grid min-h-48 place-items-center rounded-md border bg-card text-sm text-muted-foreground">Loading comment reports...</div>
    <EmptyState v-else-if="error" title="Comment reports could not be loaded" description="Try this page again in a moment." action-label="Reload" action-to="/admin/comments" />
    <AdminTableShell v-else :empty="commentReports.length === 0" empty-message="No reported comments match the current filters on this page." label="Reported comments">
      <thead><tr><th>Comment</th><th>Report</th><th>Status</th><th>Moderation</th><th class="text-right">Actions</th></tr></thead>
      <tbody>
        <tr v-for="report in commentReports" :key="report.id">
          <td><p class="font-medium">Comment #{{ report.targetId }}</p><p class="mt-1 text-muted-foreground">Reported by {{ report.reporterUsername }}</p></td>
          <td class="max-w-md text-muted-foreground">{{ report.message || 'No additional details.' }}</td>
          <td><AdminBadge :tone="report.status === 'open' ? 'warning' : 'muted'">{{ adminStatusLabel(adminReportStatusOptions, report.status) }}</AdminBadge></td>
          <td colspan="2">
            <form class="flex min-w-[28rem] items-end justify-end gap-2" @submit.prevent="updateReport(report, $event)">
              <label class="grid gap-2"><span class="sr-only">Report status</span><select name="status" class="control min-w-36" :value="report.status"><option v-for="option in adminReportStatusOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
              <label class="grid gap-2"><span class="sr-only">Moderation action</span><select name="moderationStatus" class="control min-w-44"><option value="">No content change</option><option v-for="option in adminModerationStatusOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
              <UiButton type="submit" size="sm" :disabled="rowPending[report.id]">{{ rowPending[report.id] ? 'Saving...' : 'Save' }}</UiButton>
            </form>
            <FormAlert v-if="rowError[report.id]" class="mt-2" :message="rowError[report.id] ?? ''" tone="error" /><FormAlert v-else-if="rowMessage[report.id]" class="mt-2" :message="rowMessage[report.id] ?? ''" tone="success" />
          </td>
        </tr>
      </tbody>
    </AdminTableShell>
    <PaginationNav v-if="!pending && !error" aria-label="Comment reports pagination" :next-to="nextTo" :pagination="pagination" :previous-to="previousTo" />
  </AdminShell>
</template>
