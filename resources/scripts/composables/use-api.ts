import { ref } from 'vue'
import type { Ref } from 'vue'
import { handleApiError } from '../utils/error-handling'
import type { NormalizedApiError } from '../utils/error-handling'

export interface UseApiReturn<T> {
  data: Ref<T | null>
  loading: Ref<boolean>
  error: Ref<NormalizedApiError | null>
  execute: (...args: unknown[]) => Promise<T | null>
  reset: () => void
}

/**
 * Generic API call wrapper composable.
 * Manages loading, error, and data state for an async API function.
 *
 * @param apiFn - An async function that performs the API call
 * @returns Reactive refs for data, loading, and error plus an execute function
 */
export function useApi<T>(
  apiFn: (...args: never[]) => Promise<T>
): UseApiReturn<T> {
  const data = ref<T | null>(null) as Ref<T | null>
  const loading = ref<boolean>(false)
  const error = ref<NormalizedApiError | null>(null) as Ref<NormalizedApiError | null>

  async function execute(...args: unknown[]): Promise<T | null> {
    loading.value = true
    error.value = null

    try {
      const result = await (apiFn as (...a: unknown[]) => Promise<T>)(...args)
      data.value = result
      return result
    } catch (err: unknown) {
      const normalized = handleApiError(err)
      error.value = normalized
      return null
    } finally {
      loading.value = false
    }
  }

  function reset(): void {
    data.value = null
    loading.value = false
    error.value = null
  }

  return { data, loading, error, execute, reset }
}
