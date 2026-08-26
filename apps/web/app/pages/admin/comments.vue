<script setup lang="ts">
import AdminBadge from '../../components/admin/AdminBadge.vue'
import AdminShell from '../../components/admin/AdminShell.vue'
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
const commentReports = computed(() => reports.value.filter(report => report.targetType === 'comment'))
const rowPending = ref<Record<number, boolean>>({})
const rowError = ref<Record<number, string>>({})
const rowMessage = ref<Record<number, string>>({})

useSeoMeta({ title: 'Admin comments | WhatsInMyBar', description: 'Moderate comments surfaced through community reports.' })

async function updateReport(report: Report, event: Event) {
  const form = new FormData(event.currentTarget as HTMLFormElement)
  rowPending.value[report.id] = true
  rowError.value[report.id] = ''
  rowMessage.value[report.id] = ''
  try {
    const moderationStatus = String(form.get('moderationStatus') ?? '')
    const updated = await api.admin.reports.update(report.id, {
      moderationStatus: moderationStatus ? moderationStatus as ModerationStatus : undefined,
      status: String(form.get('status')) as ReportStatus
    })
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
  <AdminShell current="comments" title="Comments" description="Review reported comments and apply moderation decisions. The API does not expose a global administrator comment index, so this page truthfully shows comment targets surfaced through reports.">
    <div v-if="pending" class="grid min-h-48 place-items-center rounded-md border bg-card text-sm text-muted-foreground">Loading comment reports…</div>
    <EmptyState v-else-if="error" title="Comment reports could not be loaded" description="Try this page again in a moment." action-label="Reload" action-to="/admin/comments" />
    <section v-else class="grid gap-4" aria-label="Reported comments">
      <form v-for="report in commentReports" :key="report.id" class="rounded-md border bg-card p-4" @submit.prevent="updateReport(report, $event)">
        <div class="grid gap-4 lg:grid-cols-[minmax(16rem,1fr)_10rem_12rem_auto]">
          <div>
            <div class="flex flex-wrap items-center gap-2"><p class="font-medium">Comment #{{ report.targetId }}</p><AdminBadge :tone="report.status === 'open' ? 'warning' : 'muted'">{{ adminStatusLabel(adminReportStatusOptions, report.status) }}</AdminBadge></div>
            <p class="mt-2 text-sm text-muted-foreground">Reported by {{ report.reporterUsername }}</p>
            <p v-if="report.message" class="mt-3 rounded-md border bg-background p-3 text-sm text-muted-foreground">{{ report.message }}</p>
          </div>
          <label class="grid gap-2"><span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Report status</span><select name="status" class="h-10 rounded-md border bg-background px-3 text-sm" :value="report.status"><option v-for="option in adminReportStatusOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
          <label class="grid gap-2"><span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Moderation</span><select name="moderationStatus" class="h-10 rounded-md border bg-background px-3 text-sm"><option value="">No content change</option><option v-for="option in adminModerationStatusOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
          <div class="grid items-end"><UiButton type="submit" :disabled="rowPending[report.id]">{{ rowPending[report.id] ? 'Saving…' : 'Save' }}</UiButton></div>
        </div>
        <FormAlert v-if="rowError[report.id]" class="mt-3" :message="rowError[report.id] ?? ''" tone="error" />
        <FormAlert v-else-if="rowMessage[report.id]" class="mt-3" :message="rowMessage[report.id] ?? ''" tone="success" />
      </form>
      <EmptyState v-if="!commentReports.length" title="No reported comments on this page" description="Comment moderation becomes available here when a comment is reported. Other report types remain in Reports." />
    </section>
    <PaginationNav v-if="!pending && !error" aria-label="Comment reports pagination" :next-to="nextTo" :pagination="pagination" :previous-to="previousTo" />
  </AdminShell>
</template>
