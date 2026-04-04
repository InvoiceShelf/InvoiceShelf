import { ref } from 'vue'
import type { Ref } from 'vue'
import * as ls from '../utils/local-storage'
import { LS_KEYS } from '@v2/config/constants'

export interface UseSidebarReturn {
  isCollapsed: Ref<boolean>
  isSidebarOpen: Ref<boolean>
  toggleCollapse: () => void
  setSidebarVisibility: (visible: boolean) => void
}

const isCollapsed = ref<boolean>(
  ls.get<string>(LS_KEYS.SIDEBAR_COLLAPSED) === 'true'
)

const isSidebarOpen = ref<boolean>(false)

/**
 * Composable for managing sidebar state.
 * Handles both the mobile sidebar open/close and the desktop collapsed toggle.
 * Persists the collapsed state to localStorage.
 */
export function useSidebar(): UseSidebarReturn {
  /**
   * Toggle the sidebar collapsed/expanded state (desktop).
   * Persists the new value to localStorage.
   */
  function toggleCollapse(): void {
    isCollapsed.value = !isCollapsed.value
    ls.set(LS_KEYS.SIDEBAR_COLLAPSED, isCollapsed.value ? 'true' : 'false')
  }

  /**
   * Set the mobile sidebar visibility.
   *
   * @param visible - Whether the sidebar should be visible
   */
  function setSidebarVisibility(visible: boolean): void {
    isSidebarOpen.value = visible
  }

  return {
    isCollapsed,
    isSidebarOpen,
    toggleCollapse,
    setSidebarVisibility,
  }
}
