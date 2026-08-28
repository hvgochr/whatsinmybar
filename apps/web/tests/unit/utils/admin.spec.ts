import { describe, expect, it } from 'vitest'
import { adminReportReasonLabel, adminStatusLabel, adminReportStatusOptions } from '../../../app/utils/admin'

describe('admin helpers', () => {
  it('labels report reasons and statuses', () => {
    expect(adminReportReasonLabel('wrong_alcohol_classification')).toBe('Wrong alcohol classification')
    expect(adminStatusLabel(adminReportStatusOptions, 'reviewing')).toBe('Reviewing')
  })
})
