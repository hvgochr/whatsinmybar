import { describe, expect, it } from 'vitest'
import { collectionItems, collectionTotal } from '../../../app/utils/api-collections'

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
