import { computed, ref, watch, type ComputedRef } from 'vue'
import lodash from 'lodash'
import { parse, format } from 'date-fns'
import { customFieldService } from '@/scripts/api/services/custom-field.service'

/**
 * A definition once it has been merged with whatever the record answered.
 *
 * `value` is what the input binds to. `custom_field_id` and `custom_field`
 * only appear on rows that came back as saved answers.
 */
export interface CustomFieldItem {
  id: number
  value: string | boolean | number | null
  default_answer: string | boolean | number | null
  label: string
  options: string[] | null
  is_required: boolean
  placeholder: string | null
  order: number | null
  type: string
  custom_field_id?: number
  custom_field?: {
    label: string
    options: string[] | null
    is_required: boolean
    placeholder: string | null
    order: number | null
    type: string
  }
}

/** The two arrays a form store keeps for one record's custom fields. */
interface CustomFieldHolder {
  customFields: CustomFieldItem[]
  fields: CustomFieldItem[]
}

interface UseCustomFieldsOptions {
  /** The store holding the record being edited. */
  store: Record<string, any>
  /** Which property of that store holds it, e.g. `newInvoice`. */
  storeProp: string
  /** Model the definitions are attached to; the endpoint reads it as `model_type`. */
  type: string
  /** Whether saved answers should be merged over the definitions. */
  isEdit: () => boolean
}

/**
 * Load a model's custom-field definitions into a form store and keep the
 * saved answers merged over them.
 *
 * Shared because the fields are rendered two ways: as their own grid on the
 * stacked forms, and as extra cells of an existing grid on the document
 * forms, where they sit beside the date and number they belong with. Only the
 * markup differs, so only the markup lives in the components.
 */
export function useCustomFields(
  options: UseCustomFieldsOptions
): ComputedRef<CustomFieldItem[]> {
  const holder = computed<CustomFieldHolder | null>(() => {
    const data = options.store[options.storeProp] as
      | CustomFieldHolder
      | undefined

    if (!data) {
      return null
    }

    if (!Array.isArray(data.customFields)) {
      data.customFields = []
    }

    if (!Array.isArray(data.fields)) {
      data.fields = []
    }

    return data
  })

  function mergeExistingValues(): void {
    const data = holder.value

    if (!options.isEdit() || !data) {
      return
    }

    data.fields.forEach((field) => {
      const existingIndex = data.customFields.findIndex(
        (f) => f.id === field.custom_field_id
      )

      if (existingIndex === -1) {
        return
      }

      let value: string | boolean | number | null = field.default_answer

      if (value && field.custom_field?.type === 'DateTime') {
        value = format(
          parse(String(field.default_answer), 'yyyy-MM-dd HH:mm:ss', new Date()),
          'yyyy-MM-dd HH:mm'
        )
      }

      data.customFields[existingIndex] = {
        ...field,
        id: field.custom_field_id ?? field.id,
        value,
        label: field.custom_field?.label ?? '',
        options: field.custom_field?.options ?? null,
        is_required: field.custom_field?.is_required ?? false,
        placeholder: field.custom_field?.placeholder ?? null,
        order: field.custom_field?.order ?? null,
        type: field.custom_field?.type ?? field.type,
      }
    })
  }

  async function load(): Promise<void> {
    if (!holder.value) {
      return
    }

    const res = await customFieldService.list({
      type: options.type,
      limit: 'all',
    })

    const definitions = res.data as unknown as CustomFieldItem[]

    definitions.forEach((definition) => {
      definition.value = definition.default_answer
    })

    holder.value.customFields = lodash.sortBy(
      definitions,
      (definition: CustomFieldItem) => definition.order
    )

    mergeExistingValues()
  }

  load()

  // The record is fetched after this runs on an edit screen, so the answers
  // arrive later than the definitions do.
  watch(() => holder.value?.fields, mergeExistingValues)

  return computed<CustomFieldItem[]>(() => holder.value?.customFields ?? [])
}

/**
 * The definitions attached to one model, without writing them into a form
 * store.
 *
 * Document lines need this rather than `useCustomFields`: the definitions are
 * shared by every line, but each line keeps its own answers, and the store
 * slot `useCustomFields` writes to is already taken by the document's own
 * fields.
 */
export function useCustomFieldDefinitions(
  type: string
): ComputedRef<CustomFieldItem[]> {
  const definitions = ref<CustomFieldItem[]>([])

  customFieldService.list({ type, limit: 'all' }).then((res) => {
    const loaded = res.data as unknown as CustomFieldItem[]

    definitions.value = lodash.sortBy(loaded, (item) => item.order)
  })

  return computed(() => definitions.value)
}

/**
 * One line's answers: every definition, carrying either what the line already
 * answered or the definition's own default.
 *
 * Shaped for `CustomFieldInput` to bind to, and for the API, which reads
 * `id` and `value` off each entry and ignores the rest.
 */
export function buildLineCustomFields(
  definitions: CustomFieldItem[],
  saved: CustomFieldItem[] = []
): CustomFieldItem[] {
  return definitions.map((definition) => {
    const answer = saved.find(
      (field) => field.custom_field_id === definition.id
    )

    return {
      ...definition,
      id: definition.id,
      value: answer ? answer.default_answer : definition.default_answer,
    }
  })
}
