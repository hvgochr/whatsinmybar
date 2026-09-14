import { paginationState } from '../../../app/utils/pagination'
import { describe, expect, it } from 'vitest'
import { collectionItems, collectionLastPage, collectionTotal } from '../../../app/utils/api-collections'

describe('api collection helpers', () => {
  it('reads API Platform JSON-LD collections', () => {
    const collection = {
      'hydra:member': [{ slug: 'negroni' }],
      'hydra:totalItems': 12
    }

    expect(collectionItems(collection)).toEqual([{ slug: 'negroni' }])
    expect(collectionTotal(collection)).toBe(12)
  })

  it('reads simple JSON collections', () => {
    const collection = {
      items: [{ slug: 'classics' }]
    }

    expect(collectionItems(collection)).toEqual([{ slug: 'classics' }])
    expect(collectionTotal(collection)).toBe(1)
  })

  it('reads bare JSON arrays returned by API Platform json format', () => {
    const collection = [{ slug: 'classics' }, { slug: 'zero-proof' }]

    expect(collectionItems(collection)).toEqual(collection)
    expect(collectionTotal(collection)).toBe(2)
  })
})

// Shapes verified against API Platform with 65 visible recipes and 30 per page.
describe('JSON-LD pagination', () => {
  it.each([
    [1, 30, 65, 3, 1, 30, false, true],
    [2, 30, 65, 3, 31, 60, true, true],
    [3, 5, 65, 3, 61, 65, true, false],
    [4, 0, 65, 3, 0, 0, true, false],
    [1, 0, 0, null, 0, 0, false, false],
    [1, 5, 5, null, 1, 5, false, false]
  ])('computes ranges and navigation for page %s with %s items of %s', (page, count, total, last, start, end, previous, next) => {
    const collection = {
      member: Array.from({ length: Number(count) }, (_, id) => ({ id })),
      totalItems: Number(total),
      view: last ? { last: `/api/recipes?page=${last}` } : undefined
    }
    expect(paginationState({
      currentPage: Number(page),
      itemsOnPage: collectionItems(collection).length,
      totalItems: collectionTotal(collection),
      totalPages: collectionLastPage(collection)
    })).toMatchObject({
      resultStart: start, resultEnd: end,
      hasPreviousPage: previous, hasNextPage: next,
      totalItems: total
    })
  })
})
