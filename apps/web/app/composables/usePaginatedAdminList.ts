import type { PaginatedList, PaginationParams } from '../types/api'
import { pageFromQuery, pageLocation, paginationState } from '../utils/pagination'

export async function usePaginatedAdminList<T>(
  key: string,
  path: string,
  load: (params: PaginationParams) => Promise<PaginatedList<T>>
) {
  const route = useRoute()
  const requestedPage = computed(() => pageFromQuery(route.query))
  const { data, pending, error, refresh } = await useAsyncData(
    `${key}:${route.fullPath}`,
    () => load({ page: requestedPage.value }),
    { watch: [() => route.fullPath] }
  )
  const items = shallowRef<T[]>([])

  watch(data, (nextData) => {
    items.value = nextData?.items ? [...nextData.items] : []
  }, { immediate: true })

  const pagination = computed(() => paginationState({
    currentPage: data.value?.page ?? requestedPage.value,
    itemsOnPage: items.value.length,
    pageSize: data.value?.pageSize,
    totalItems: data.value?.totalItems ?? 0,
    totalPages: data.value?.totalPages ?? null
  }))
  const previousTo = computed(() => pageLocation(path, route.query, pagination.value.previousPage))
  const nextTo = computed(() => pageLocation(path, route.query, pagination.value.nextPage))

  return {
    error,
    items,
    nextTo,
    pagination,
    pending,
    previousTo,
    refresh
  }
}
