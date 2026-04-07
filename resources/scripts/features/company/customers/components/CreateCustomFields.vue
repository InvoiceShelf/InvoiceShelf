<script setup lang="ts">
import { computed, watch } from 'vue'
import lodash from 'lodash'
import moment from 'moment'
import { customFieldService } from '@/scripts/api/services/custom-field.service'
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

const storeData = computed(() => {
  const data = props.store[props.storeProp]

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

getInitialCustomFields()

function mergeExistingValues(): void {
  if (props.isEdit && storeData.value) {
    storeData.value.fields.forEach((field) => {
      const existingIndex = storeData.value?.customFields.findIndex(
        (f) => f.id === field.custom_field_id
      ) ?? -1

      if (existingIndex > -1) {
        let value: string | boolean | number | null = field.default_answer

        if (value && field.custom_field?.type === 'DateTime') {
          value = moment(
            String(field.default_answer),
            'YYYY-MM-DD HH:mm:ss'
          ).format('YYYY-MM-DD HH:mm')
        }

        storeData.value.customFields[existingIndex] = {
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
  if (!storeData.value) {
    return
  }

  const res = await customFieldService.list({
    type: props.type ?? undefined,
    limit: 'all',
  })

  const data = (res as Record<string, unknown>).data as CustomFieldItem[]
  data.forEach((d) => {
    d.value = d.default_answer
  })

  storeData.value.customFields = lodash.sortBy(
    data,
    (_cf: CustomFieldItem) => _cf.order
  )

  mergeExistingValues()
}

watch(
  () => storeData.value?.fields,
  () => {
    mergeExistingValues()
  }
)
</script>

<template>
  <div
    v-if="
      storeData &&
      storeData.customFields.length > 0 &&
      !isLoading
    "
  >
    <BaseInputGrid :layout="gridLayout">
      <SingleField
        v-for="(field, index) in storeData.customFields"
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
