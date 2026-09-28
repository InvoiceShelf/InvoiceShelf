import { computed, ref } from 'vue'
import { useModalStore } from '@/scripts/stores/modal.store'
import { extractValidationErrors } from '@/scripts/utils/error-handling'
import { focusFirstInvalid } from '@/scripts/composables/use-form-field'
import { isAxiosError } from 'axios'

export interface LookupDialogProps {
  show?: boolean
  title?: string
  data?: unknown
}
/** A locally owned dialog, with the existing settings-modal store as a fallback. */
export function useLookupDialog(
  props: LookupDialogProps,
  componentName: string,
  emit: { close: () => void; saved: (record: unknown) => void },
) {
  const store = useModalStore()
  const controlled = computed(() => props.show !== undefined)
  const active = computed(() =>
    controlled.value
      ? props.show === true
      : store.active && store.componentName === componentName,
  )
  const data = computed(() => (controlled.value ? props.data : store.data))
  const title = computed(() =>
    controlled.value ? props.title || '' : store.title,
  )
  const errors = ref<Record<string, string[]>>({})
  const error = ref('')
  function clearErrors() {
    errors.value = {}
    error.value = ''
  }
  function fail(value: unknown) {
    errors.value = extractValidationErrors(value)
    error.value = isAxiosError(value)
      ? value.response?.data?.message || value.message
      : String(value)
    focusFirstInvalid()
  }
  function close() {
    if (controlled.value) emit.close()
    else store.closeModal()
  }
  function saved(record?: unknown) {
    if (controlled.value) emit.saved(record)
    else store.refreshData?.(...(record ? [record] : []))
  }
  return {
    active,
    data,
    title,
    controlled,
    error,
    errors,
    clearErrors,
    fail,
    close,
    saved,
  }
}
