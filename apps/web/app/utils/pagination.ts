import type { LocationQuery } from 'vue-router'
import { firstQueryValue } from './route-query'

export interface PaginationState {
  currentPage: number
  hasNextPage: boolean
  hasPreviousPage: boolean
  nextPage: number
  previousPage: number
  resultEnd: number
  resultStart: number
  totalItems: number
  totalPages: number | null
}

export function pageFromQuery(query: LocationQuery, key = 'page'): number {
  const value = Number(firstQueryValue(query, key))

  return Number.isSafeInteger(value) && value > 0 ? value : 1
}

export function pageLocation(path: string, query: LocationQuery, page: number, key = 'page') {
  const nextQuery = Object.fromEntries(Object.entries(query).filter(([queryKey]) => queryKey !== key))

  if (page > 1) {
    nextQuery[key] = String(page)
  }

  return { path, query: nextQuery }
}

export function paginationState(options: {
  currentPage: number
  itemsOnPage: number
  pageSize?: number
  totalItems: number
  totalPages: number | null
}): PaginationState {
  const isLastKnownPage = options.totalPages !== null && options.currentPage === options.totalPages
  const resultStart = options.totalItems === 0 || options.itemsOnPage === 0
    ? 0
    : options.pageSize
      ? ((options.currentPage - 1) * options.pageSize) + 1
      : isLastKnownPage
        ? Math.max(1, options.totalItems - options.itemsOnPage + 1)
        : ((options.currentPage - 1) * options.itemsOnPage) + 1
  const resultEnd = resultStart === 0 ? 0 : Math.min(options.totalItems, resultStart + options.itemsOnPage - 1)

  return {
    currentPage: options.currentPage,
    hasNextPage: options.totalPages === null ? resultEnd < options.totalItems : options.currentPage < options.totalPages,
    hasPreviousPage: options.currentPage > 1,
    nextPage: options.currentPage + 1,
    previousPage: options.totalPages !== null && options.totalPages > 0 && options.currentPage > options.totalPages
      ? options.totalPages
      : Math.max(1, options.currentPage - 1),
    resultEnd,
    resultStart,
    totalItems: options.totalItems,
    totalPages: options.totalPages
  }
}
