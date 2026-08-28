import type { ModerationStatus, RecipeStatus, Report, ReportStatus } from '../types/api'
import { reportReasonOptions } from './social'

export const adminRecipeStatusOptions: Array<{ label: string, value: RecipeStatus }> = [
  { label: 'Draft', value: 'draft' },
  { label: 'Published', value: 'published' },
  { label: 'Archived', value: 'archived' }
]

export const adminModerationStatusOptions: Array<{ label: string, value: ModerationStatus }> = [
  { label: 'Visible', value: 'visible' },
  { label: 'Hidden', value: 'hidden' },
  { label: 'Pending review', value: 'pending_review' },
  { label: 'Removed', value: 'removed' }
]

export const adminReportStatusOptions: Array<{ label: string, value: ReportStatus }> = [
  { label: 'Open', value: 'open' },
  { label: 'Reviewing', value: 'reviewing' },
  { label: 'Resolved', value: 'resolved' },
  { label: 'Rejected', value: 'rejected' }
]

export const adminRoleOptions = [
  { label: 'Admin', value: 'ROLE_ADMIN' }
] as const

export function adminReportReasonLabel(value: Report['reason']): string {
  return reportReasonOptions.find(option => option.value === value)?.label ?? value
}

export function adminStatusLabel<T extends string>(options: Array<{ label: string, value: T }>, value: T): string {
  return options.find(option => option.value === value)?.label ?? value
}
