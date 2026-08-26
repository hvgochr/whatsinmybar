import type { LocationQuery } from 'vue-router'

export function firstQueryValue(query: LocationQuery, key: string): string | undefined {
  const value = query[key]

  if (Array.isArray(value)) {
    return value[0] ?? undefined
  }

  return value ?? undefined
}
