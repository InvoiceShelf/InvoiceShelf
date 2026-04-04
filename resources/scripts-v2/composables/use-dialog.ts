import { ref, readonly } from 'vue'
import type { DeepReadonly, Ref } from 'vue'
import { DIALOG_VARIANT } from '@v2/config/constants'
import type { DialogVariant } from '@v2/config/constants'

export interface DialogState {
  active: boolean
  title: string
  message: string
  variant: DialogVariant
  yesLabel: string
  noLabel: string
  hideNoButton: boolean
  data: unknown
}

export interface OpenConfirmOptions {
  title: string
  message: string
  variant?: DialogVariant
  yesLabel?: string
  noLabel?: string
  hideNoButton?: boolean
  data?: unknown
}

export interface UseDialogReturn {
  dialogState: DeepReadonly<Ref<DialogState>>
  openConfirm: (options: OpenConfirmOptions) => Promise<boolean>
  closeDialog: () => void
  confirmDialog: () => void
  cancelDialog: () => void
}

const DEFAULT_STATE: DialogState = {
  active: false,
  title: '',
  message: '',
  variant: DIALOG_VARIANT.DANGER,
  yesLabel: 'Yes',
  noLabel: 'No',
  hideNoButton: false,
  data: null,
}

const dialogState = ref<DialogState>({ ...DEFAULT_STATE })

let resolvePromise: ((value: boolean) => void) | null = null

/**
 * Composable for managing confirmation dialogs.
 * Returns a promise that resolves to true (confirmed) or false (cancelled).
 */
export function useDialog(): UseDialogReturn {
  /**
   * Open a confirmation dialog and await the user's response.
   *
   * @param options - Dialog configuration
   * @returns Promise that resolves to true if confirmed, false if cancelled
   */
  function openConfirm(options: OpenConfirmOptions): Promise<boolean> {
    dialogState.value = {
      active: true,
      title: options.title,
      message: options.message,
      variant: options.variant ?? DIALOG_VARIANT.DANGER,
      yesLabel: options.yesLabel ?? 'Yes',
      noLabel: options.noLabel ?? 'No',
      hideNoButton: options.hideNoButton ?? false,
      data: options.data ?? null,
    }

    return new Promise<boolean>((resolve) => {
      resolvePromise = resolve
    })
  }

  function confirmDialog(): void {
    if (resolvePromise) {
      resolvePromise(true)
      resolvePromise = null
    }
    resetDialog()
  }

  function cancelDialog(): void {
    if (resolvePromise) {
      resolvePromise(false)
      resolvePromise = null
    }
    resetDialog()
  }

  function closeDialog(): void {
    cancelDialog()
  }

  function resetDialog(): void {
    dialogState.value.active = false

    setTimeout(() => {
      dialogState.value = { ...DEFAULT_STATE }
    }, 300)
  }

  return {
    dialogState: readonly(dialogState),
    openConfirm,
    closeDialog,
    confirmDialog,
    cancelDialog,
  }
}
