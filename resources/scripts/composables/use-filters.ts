import { reactive, ref, watch } from 'vue'
import type { Ref } from 'vue'

export interface UseFiltersOptions<T extends Record<string, unknown>> {
  initialFilters: T
  debounceMs?: number
  onChange?: (filters: T) => void
}

export interface UseFiltersReturn<T extends Record<string, unknown>> {
  filters: T
  activeFilterCount: Ref<number>
  setFilter: <K extends keyof T>(key: K, value: T[K]) => void
  clearFilters: () => void
  clearFilter: <K extends keyof T>(key: K) => void
  getFiltersSnapshot: () => T
}

/**
 * Composable for managing list filter state with optional debounced apply.
 *
 * @param options - Configuration including initial filter values, debounce delay, and change callback
 */
export function useFilters<T extends Record<string, unknown>>(
  options: UseFiltersOptions<T>
): UseFiltersReturn<T> {
  const { initialFilters, debounceMs = 300, onChange } = options

  const filters = reactive<T>({ ...initialFilters }) as T

  const activeFilterCount = ref<number>(0)

  let debounceTimer: ReturnType<typeof setTimeout> | null = null

  function updateActiveFilterCount(): void {
    let count = 0
    const initialKeys = Object.keys(initialFilters) as Array<keyof T>

    for (const key of initialKeys) {
      const current = filters[key]
      const initial = initialFilters[key]

      if (current !== initial && current !== '' && current !== null && current !== undefined) {
        count++
      }
    }

    activeFilterCount.value = count
  }

  function debouncedApply(): void {
    if (debounceTimer) {
      clearTimeout(debounceTimer)
    }

    debounceTimer = setTimeout(() => {
      updateActiveFilterCount()
      onChange?.({ ...filters })
    }, debounceMs)
  }

  function setFilter<K extends keyof T>(key: K, value: T[K]): void {
    filters[key] = value
    debouncedApply()
  }

  function clearFilter<K extends keyof T>(key: K): void {
    filters[key] = initialFilters[key]
    debouncedApply()
  }

  function clearFilters(): void {
    const keys = Object.keys(initialFilters) as Array<keyof T>
    for (const key of keys) {
      filters[key] = initialFilters[key]
    }
    updateActiveFilterCount()
    onChange?.({ ...filters })
  }

  function getFiltersSnapshot(): T {
    return { ...filters }
  }

  // Watch for external reactive changes and apply debounce
  watch(
    () => ({ ...filters }),
    () => {
      debouncedApply()
    },
    { deep: true }
  )

  // Initialize the active count
  updateActiveFilterCount()

  return {
    filters,
    activeFilterCount,
    setFilter,
    clearFilters,
    clearFilter,
    getFiltersSnapshot,
  }
}
