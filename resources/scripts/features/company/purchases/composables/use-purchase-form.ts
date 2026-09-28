import { provide, ref, type InjectionKey, type Ref } from 'vue'
import { extractValidationErrors } from '@/scripts/utils/error-handling'
import { focusFirstInvalid } from '@/scripts/composables/use-form-field'
import { purchaseError } from '../helpers'
export const purchaseErrors: InjectionKey<Ref<Record<string, string[]>>> =
  Symbol('purchaseErrors')
export function usePurchaseForm() {
  const error = ref('')
  const errors = ref<Record<string, string[]>>({})
  provide(purchaseErrors, errors)
  function setError(value: unknown) {
    error.value = purchaseError(value)
    errors.value = extractValidationErrors(value)
    focusFirstInvalid()
  }
  function clearError() {
    error.value = ''
    errors.value = {}
  }
  return { error, errors, setError, clearError }
}
