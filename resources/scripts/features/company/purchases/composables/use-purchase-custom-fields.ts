import { useId } from 'vue'
import useVuelidate from '@vuelidate/core'
import { focusFirstInvalid } from '@/scripts/composables/use-form-field'
import type {
  CustomField,
  CustomFieldValue,
} from '@/scripts/types/domain/custom-field'
import type { CustomFieldItem } from '@/scripts/features/shared/custom-fields/use-custom-fields'

export interface PurchaseCustomFieldAnswer {
  id: number
  value: string | number | boolean | null
}

/** Merge only when a form loads, so late lookups cannot overwrite a user's draft. */
export function purchaseCustomFields(
  definitions: CustomField[],
  saved: Array<PurchaseCustomFieldAnswer | CustomFieldValue> = [],
): CustomFieldItem[] {
  const values = new Map(
    saved.map((answer) =>
      'custom_field_id' in answer
        ? [answer.custom_field_id, answer.default_answer]
        : [answer.id, answer.value],
    ),
  )
  return [...definitions]
    .sort((a, b) => Number(a.order) - Number(b.order) || a.id - b.id)
    .map((field) => {
      let value = values.has(field.id)
        ? values.get(field.id)!
        : field.default_answer
      if (field.type === 'Switch' && value !== null)
        value = value === true || value === 1 || value === '1' ? 1 : 0
      if (field.type === 'DateTime' && typeof value === 'string')
        value = value.replace('T', ' ').slice(0, 16)
      return { ...field, value }
    })
}

export function purchaseCustomFieldPayload(
  fields: CustomFieldItem[],
): PurchaseCustomFieldAnswer[] {
  return fields.map(({ id, value }) => ({ id, value }))
}

/** Each modal owns a scope; its fields never join the surrounding bill's validation. */
export function usePurchaseCustomFieldValidation() {
  const scope = `purchase-custom-fields-${useId()}`
  const validation = useVuelidate(
    {},
    {},
    { $scope: scope, $stopPropagation: true },
  )
  async function validate() {
    const valid = await validation.value.$validate()
    if (!valid) await focusFirstInvalid()
    return valid
  }
  return { scope, validate, reset: () => validation.value.$reset() }
}
