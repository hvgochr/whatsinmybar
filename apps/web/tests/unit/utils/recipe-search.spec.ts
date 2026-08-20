import { describe, expect, it } from 'vitest'
import { collectionLastPage, pageFromUrl } from '../../../app/utils/api-collections'
import { paginationState } from '../../../app/utils/pagination'
import {
  activeRecipeFilters,
  cleanRecipeSearchQuery,
  recipeSearchQueryFromForm,
  recipeSearchStateFromQuery
} from '../../../app/utils/recipe-search'

describe('recipe search helpers', () => {
  it('normalizes route query into recipe search state', () => {
    expect(recipeSearchStateFromQuery({
      alcohol: 'without',
      author: 'jane_doe',
      category: 'classics',
      ingredient: 'lime',
      minFavorites: '3',
      page: '2',
      q: 'sour',
      sort: 'popular'
    })).toEqual({
      alcohol: 'without',
      author: 'jane_doe',
      category: 'classics',
      ingredient: 'lime',
      minFavorites: 3,
      page: 2,
      publishedAfter: undefined,
      publishedBefore: undefined,
      q: 'sour',
      sort: 'popular'
    })
  })

  it('cleans default and empty query values', () => {
    expect(cleanRecipeSearchQuery({
      page: 1,
      q: '',
      sort: 'newest'
    })).toEqual({})

    expect(cleanRecipeSearchQuery({
      page: 3,
      q: 'negroni',
      sort: 'popular'
    })).toEqual({
      page: '3',
      q: 'negroni',
      sort: 'popular'
    })
  })

  it('builds query values from form data and resets page implicitly', () => {
    const form = new FormData()
    form.set('q', ' lime ')
    form.set('category', 'zero-proof')
    form.set('sort', 'newest')

    expect(recipeSearchQueryFromForm(form)).toEqual({
      category: 'zero-proof',
      q: 'lime'
    })
  })

  it('summarizes active filters', () => {
    expect(activeRecipeFilters(recipeSearchStateFromQuery({
      alcohol: 'with',
      author: 'jane_doe',
      minFavorites: '2'
    }))).toEqual([
      { label: 'Alcohol', value: 'With alcohol' },
      { label: 'Author', value: 'jane_doe' },
      { label: 'Minimum saves', value: '2' }
    ])
  })

  it('builds pagination state from totals', () => {
    expect(paginationState({
      currentPage: 2,
      itemsOnPage: 12,
      totalPages: 4,
      totalItems: 42
    })).toEqual({
      currentPage: 2,
      hasNextPage: true,
      hasPreviousPage: true,
      nextPage: 3,
      previousPage: 1,
      totalPages: 4,
      resultEnd: 24,
      resultStart: 13,
      totalItems: 42
    })

    expect(paginationState({
      currentPage: 4,
      itemsOnPage: 6,
      totalPages: 4,
      totalItems: 42
    })).toEqual({
      currentPage: 4,
      hasNextPage: false,
      hasPreviousPage: true,
      nextPage: 5,
      previousPage: 3,
      totalPages: 4,
      resultEnd: 42,
      resultStart: 37,
      totalItems: 42
    })
  })

  it('reads API Platform pagination links', () => {
    expect(pageFromUrl('/api/recipes?page=4')).toBe(4)
    expect(collectionLastPage({
      'hydra:view': {
        'hydra:last': '/api/recipes?page=7'
      },
      member: []
    })).toBe(7)
  })
})
