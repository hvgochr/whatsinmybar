import type { LocationQuery } from 'vue-router'
import { firstQueryValue } from './route-query'

export function adminPageFromQuery(query: LocationQuery): number {
  const value = Number(firstQueryValue(query, 'page'))

  return Number.isSafeInteger(value) && value > 0 ? value : 1
}

export function adminPageTo(path: string, page: number) {
  return {
    path,
    query: page > 1 ? { page: String(page) } : {}
  }
}
