<template>
  <BaseInputGroup
    :label="field.label"
    :required="field.is_required ? true : false"
    :error="v$.value.$error && v$.value.$errors[0].$message"
  >
    <component
      :is="getTypeComponent"
      v-model="field.value"
      :options="field.options"
      :invalid="v$.value.$error"
      :placeholder="field.placeholder"
    />
  </BaseInputGroup>
</template>

<script setup lang="ts">
import { defineAsyncComponent, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { helpers, requiredIf } from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'

const props = defineProps<{
  field: Record<string, any>
  customFieldScope: string
  index: number
  store: Record<string, any>
  storeProp: string
}>()

const { t } = useI18n()

const rules = {
  value: {
    required: helpers.withMessage(
      t('validation.required'),
      requiredIf(props.field.is_required)
    ),
  },
}

const v$ = useVuelidate(
  rules,
  computed(() => props.field),
  { $scope: props.customFieldScope }
)

const getTypeComponent = computed(() => {
  if (props.field.type) {
    return defineAsyncComponent(() =>
      import(`./types/${props.field.type}Type.vue`)
    )
  }

  return false
})
</script>
