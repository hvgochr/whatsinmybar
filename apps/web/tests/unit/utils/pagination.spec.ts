import { describe, expect, it } from 'vitest'
import { pageFromQuery, pageLocation, paginationState } from '../../../app/utils/pagination'

describe('pagination', () => {
  it('reads only positive safe integer pages', () => {
    expect(pageFromQuery({})).toBe(1)
    expect(pageFromQuery({ page: '3' })).toBe(3)
    expect(pageFromQuery({ page: ['4', '5'] })).toBe(4)
    expect(pageFromQuery({ page: '0' })).toBe(1)
    expect(pageFromQuery({ page: 'invalid' })).toBe(1)
    expect(pageFromQuery({ page: String(Number.MAX_SAFE_INTEGER + 1) })).toBe(1)
  })

  it('supports independent named pagination query parameters', () => {
    expect(pageFromQuery({ ownedPage: '2', savedPage: '4' }, 'savedPage')).toBe(4)
    expect(pageLocation('/recipes', { ownedPage: '2', savedPage: '4' }, 3, 'ownedPage')).toEqual({
      path: '/recipes',
      query: { ownedPage: '3', savedPage: '4' }
    })
  })

  it('builds page locations while preserving filters and clean first-page URLs', () => {
    expect(pageLocation('/recipes', { alcohol: 'without', page: '3' }, 1)).toEqual({
      path: '/recipes',
      query: { alcohol: 'without' }
    })
    expect(pageLocation('/recipes', { alcohol: 'without' }, 2)).toEqual({
      path: '/recipes',
      query: { alcohol: 'without', page: '2' }
    })
  })

  it('uses an explicit page size and handles out-of-range pages', () => {
    expect(paginationState({
      currentPage: 2,
      itemsOnPage: 5,
      pageSize: 5,
      totalItems: 12,
      totalPages: 3
    })).toMatchObject({ resultStart: 6, resultEnd: 10 })

    expect(paginationState({
      currentPage: 4,
      itemsOnPage: 0,
      pageSize: 5,
      totalItems: 12,
      totalPages: 3
    })).toMatchObject({
      hasNextPage: false,
      hasPreviousPage: true,
      previousPage: 3,
      resultEnd: 0,
      resultStart: 0
    })
  })
})
