import { createPinia } from 'pinia'
import type { Pinia } from 'pinia'

/**
 * Create and return the Pinia store instance.
 */
export function createAppPinia(): Pinia {
  return createPinia()
}
