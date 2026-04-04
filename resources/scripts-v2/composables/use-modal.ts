import { ref, computed, readonly } from 'vue'
import type { DeepReadonly, Ref, ComputedRef } from 'vue'
import type { ModalSize } from '../config/constants'

export interface ModalState {
  active: boolean
  componentName: string
  title: string
  content: string
  id: string
  size: ModalSize
  data: unknown
  refreshData: (() => void) | null
  variant: string
}

export interface OpenModalOptions {
  componentName: string
  title?: string
  content?: string
  id?: string
  size?: ModalSize
  data?: unknown
  refreshData?: () => void
  variant?: string
}

export interface UseModalReturn {
  modalState: DeepReadonly<Ref<ModalState>>
  isEdit: ComputedRef<boolean>
  openModal: (options: OpenModalOptions) => void
  closeModal: () => void
  resetModalData: () => void
}

const DEFAULT_STATE: ModalState = {
  active: false,
  componentName: '',
  title: '',
  content: '',
  id: '',
  size: 'md',
  data: null,
  refreshData: null,
  variant: '',
}

const modalState = ref<ModalState>({ ...DEFAULT_STATE })

/**
 * Composable for managing a global modal.
 * Supports opening modals by component name with props, and tracks edit vs create mode.
 */
export function useModal(): UseModalReturn {
  const isEdit = computed<boolean>(() => modalState.value.id !== '')

  /**
   * Open a modal with the specified options.
   *
   * @param options - Modal configuration including component name and optional props
   */
  function openModal(options: OpenModalOptions): void {
    modalState.value = {
      active: true,
      componentName: options.componentName,
      title: options.title ?? '',
      content: options.content ?? '',
      id: options.id ?? '',
      size: options.size ?? 'md',
      data: options.data ?? null,
      refreshData: options.refreshData ?? null,
      variant: options.variant ?? '',
    }
  }

  /**
   * Close the modal with a brief delay for animation.
   */
  function closeModal(): void {
    modalState.value.active = false

    setTimeout(() => {
      resetModalData()
    }, 300)
  }

  /**
   * Reset modal data back to defaults.
   */
  function resetModalData(): void {
    modalState.value = { ...DEFAULT_STATE }
  }

  return {
    modalState: readonly(modalState),
    isEdit,
    openModal,
    closeModal,
    resetModalData,
  }
}
