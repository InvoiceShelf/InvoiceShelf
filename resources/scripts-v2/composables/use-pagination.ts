import { ref, computed } from 'vue'
import type { Ref, ComputedRef } from 'vue'
import { PAGINATION_DEFAULTS } from '../config/constants'

export interface UsePaginationOptions {
  initialPage?: number
  initialLimit?: number
}

export interface UsePaginationReturn {
  page: Ref<number>
  limit: Ref<number>
  totalCount: Ref<number>
  totalPages: ComputedRef<number>
  hasNextPage: ComputedRef<boolean>
  hasPrevPage: ComputedRef<boolean>
  nextPage: () => void
  prevPage: () => void
  goToPage: (target: number) => void
  setTotalCount: (count: number) => void
  reset: () => void
}

/**
 * Composable for managing pagination state.
 * Tracks page, limit, total count, and provides navigation helpers.
 *
 * @param options - Optional initial page and limit values
 */
export function usePagination(
  options?: UsePaginationOptions
): UsePaginationReturn {
  const initialPage = options?.initialPage ?? PAGINATION_DEFAULTS.PAGE
  const initialLimit = options?.initialLimit ?? PAGINATION_DEFAULTS.LIMIT

  const page = ref<number>(initialPage)
  const limit = ref<number>(initialLimit)
  const totalCount = ref<number>(0)

  const totalPages = computed<number>(() => {
    if (totalCount.value === 0 || limit.value === 0) {
      return 0
    }
    return Math.ceil(totalCount.value / limit.value)
  })

  const hasNextPage = computed<boolean>(() => page.value < totalPages.value)

  const hasPrevPage = computed<boolean>(() => page.value > 1)

  function nextPage(): void {
    if (hasNextPage.value) {
      page.value += 1
    }
  }

  function prevPage(): void {
    if (hasPrevPage.value) {
      page.value -= 1
    }
  }

  function goToPage(target: number): void {
    if (target >= 1 && target <= totalPages.value) {
      page.value = target
    }
  }

  function setTotalCount(count: number): void {
    totalCount.value = count
  }

  function reset(): void {
    page.value = initialPage
    totalCount.value = 0
  }

  return {
    page,
    limit,
    totalCount,
    totalPages,
    hasNextPage,
    hasPrevPage,
    nextPage,
    prevPage,
    goToPage,
    setTotalCount,
    reset,
  }
}
