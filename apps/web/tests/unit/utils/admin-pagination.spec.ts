import { describe, expect, it } from 'vitest'
import { adminPageFromQuery, adminPageTo } from '../../../app/utils/admin-pagination'

describe('admin pagination', () => {
  it('reads only positive safe integer pages', () => {
    expect(adminPageFromQuery({})).toBe(1)
    expect(adminPageFromQuery({ page: '3' })).toBe(3)
    expect(adminPageFromQuery({ page: ['4', '5'] })).toBe(4)
    expect(adminPageFromQuery({ page: '0' })).toBe(1)
    expect(adminPageFromQuery({ page: 'invalid' })).toBe(1)
    expect(adminPageFromQuery({ page: String(Number.MAX_SAFE_INTEGER + 1) })).toBe(1)
  })

  it('keeps the first page out of clean admin URLs', () => {
    expect(adminPageTo('/admin/users', 1)).toEqual({ path: '/admin/users', query: {} })
    expect(adminPageTo('/admin/users', 2)).toEqual({ path: '/admin/users', query: { page: '2' } })
  })
})
