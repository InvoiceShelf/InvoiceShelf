<script setup lang="ts">
import { ref, watch } from 'vue'
import lodash from 'lodash'
import moment from 'moment'
import { customFieldService } from '@v2/api/services/custom-field.service'
import SingleField from './CreateCustomFieldsSingle.vue'

interface CustomFieldItem {
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

interface StoreWithProp {
  [key: string]: {
    customFields: CustomFieldItem[]
    fields: CustomFieldItem[]
  }
}

const props = withDefaults(
  defineProps<{
    store: StoreWithProp
    storeProp: string
    isEdit?: boolean
    type?: string | null
    gridLayout?: string
    isLoading?: boolean | null
    customFieldScope: string
  }>(),
  {
    isEdit: false,
    type: null,
    gridLayout: 'two-column',
    isLoading: null,
  }
)

const customFields = ref<CustomFieldItem[]>([])

getInitialCustomFields()

function mergeExistingValues(): void {
  if (props.isEdit && props.store[props.storeProp]) {
    props.store[props.storeProp].fields.forEach((field) => {
      const existingIndex = props.store[
        props.storeProp
      ].customFields.findIndex((f) => f.id === field.custom_field_id)

      if (existingIndex > -1) {
        let value: string | boolean | number | null = field.default_answer

        if (value && field.custom_field?.type === 'DateTime') {
          value = moment(
            String(field.default_answer),
            'YYYY-MM-DD HH:mm:ss'
          ).format('YYYY-MM-DD HH:mm')
        }

        props.store[props.storeProp].customFields[existingIndex] = {
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
      }
    })
  }
}

async function getInitialCustomFields(): Promise<void> {
  const res = await customFieldService.list({
    type: props.type ?? undefined,
    limit: 'all',
  })

  const data = (res as Record<string, unknown>).data as CustomFieldItem[]
  data.forEach((d) => {
    d.value = d.default_answer
  })

  props.store[props.storeProp].customFields = lodash.sortBy(
    data,
    (_cf: CustomFieldItem) => _cf.order
  )

  mergeExistingValues()
}

watch(
  () => props.store[props.storeProp]?.fields,
  () => {
    mergeExistingValues()
  }
)
</script>

<template>
  <div
    v-if="
      store[storeProp] &&
      store[storeProp].customFields.length > 0 &&
      !isLoading
    "
  >
    <BaseInputGrid :layout="gridLayout">
      <SingleField
        v-for="(field, index) in store[storeProp].customFields"
        :key="field.id"
        :custom-field-scope="customFieldScope"
        :store="store"
        :store-prop="storeProp"
        :index="index"
        :field="field"
      />
    </BaseInputGrid>
  </div>
</template>
