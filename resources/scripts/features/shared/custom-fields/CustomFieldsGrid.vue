<script setup lang="ts">
import { toRef } from 'vue'
import SingleField from './CustomFieldInput.vue'
import { useCustomFields } from './use-custom-fields'

const props = withDefaults(
  defineProps<{
    store: Record<string, any>
    storeProp: string
    isEdit?: boolean
    type: string
    gridLayout?: string
    isLoading?: boolean | null
    customFieldScope: string
  }>(),
  {
    isEdit: false,
    gridLayout: 'two-column',
    isLoading: null,
  }
)

const isEdit = toRef(props, 'isEdit')

const fields = useCustomFields({
  store: props.store,
  storeProp: props.storeProp,
  type: props.type,
  isEdit: () => isEdit.value,
})
</script>

<template>
  <div v-if="fields.length > 0 && !isLoading">
    <BaseInputGrid :layout="gridLayout">
      <SingleField
        v-for="field in fields"
        :key="field.id"
        :custom-field-scope="customFieldScope"
        :field="field"
      />
    </BaseInputGrid>
  </div>
</template>
