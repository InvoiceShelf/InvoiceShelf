import { ref, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  customFieldService,
  type CustomFieldModelOption,
} from '@/scripts/api/services/custom-field.service'

/**
 * Module-scoped so the settings list and the editor share one fetch: they
 * render on the same screen and used to keep two hand-written copies of this
 * list, which is exactly how they came to disagree.
 */
const models: Ref<CustomFieldModelOption[]> = ref([])
let request: Promise<void> | null = null

function load(): Promise<void> {
  if (models.value.length > 0) {
    return Promise.resolve()
  }

  request ??= customFieldService
    .modelTypes()
    .then((options) => {
      models.value = options
    })
    .finally(() => {
      request = null
    })

  return request
}

export function useCustomFieldModels() {
  const { t } = useI18n()

  load()

  /**
   * The label to show for a stored `model_type`.
   *
   * Built-in entries carry an i18n key; a module may carry a plain string,
   * which `t()` hands back untouched. A value the catalogue no longer offers
   * -- a field created before a module was removed -- shows as itself rather
   * than as a blank.
   */
  function labelFor(value: string): string {
    const known = models.value.find((model) => model.value === value)

    return known ? t(known.label) : value
  }

  return { models, labelFor }
}
