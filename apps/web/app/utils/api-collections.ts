import type { ApiCollection } from '../types/api'

export function collectionItems<T>(collection: ApiCollection<T> | null | undefined): T[] {
  return collection?.member ?? collection?.['hydra:member'] ?? collection?.items ?? []
}

export function collectionTotal<T>(collection: ApiCollection<T> | null | undefined): number {
  return collection?.totalItems ?? collection?.['hydra:totalItems'] ?? collectionItems(collection).length
}
