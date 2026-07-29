import type { ApiCollection } from '../types/api'

export function collectionItems<T>(collection: ApiCollection<T> | null | undefined): T[] {
  return collection?.member ?? collection?.['hydra:member'] ?? collection?.items ?? []
}

export function collectionTotal<T>(collection: ApiCollection<T> | null | undefined): number {
  return collection?.totalItems ?? collection?.['hydra:totalItems'] ?? collectionItems(collection).length
}

export function collectionView<T>(collection: ApiCollection<T> | null | undefined) {
  return collection?.view ?? collection?.['hydra:view'] ?? null
}

export function collectionLastPage<T>(collection: ApiCollection<T> | null | undefined): number | null {
  const lastUrl = collectionView(collection)?.last ?? collectionView(collection)?.['hydra:last']

  return lastUrl ? pageFromUrl(lastUrl) : null
}

export function pageFromUrl(url: string): number | null {
  const queryStart = url.indexOf('?')
  const searchParams = new URLSearchParams(queryStart >= 0 ? url.slice(queryStart + 1) : url)
  const page = Number(searchParams.get('page'))

  return Number.isInteger(page) && page > 0 ? page : null
}
