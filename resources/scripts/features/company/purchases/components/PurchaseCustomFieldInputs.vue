<template>
  <CustomFieldInput
    v-for="(field, index) in fields"
    :key="field.id"
    :field="field"
    :custom-field-scope="scope"
    :error="
      errors?.[`${prefix}.${index}.value`]?.[0] ||
      errors?.[`${prefix}.${index}.id`]?.[0]
    "
  />
</template>
<script setup lang="ts">
import { inject } from 'vue'
import CustomFieldInput from '@/scripts/features/shared/custom-fields/CustomFieldInput.vue'
import type { CustomFieldItem } from '@/scripts/features/shared/custom-fields/use-custom-fields'
import { purchaseErrors } from '../composables/use-purchase-form'
withDefaults(
  defineProps<{ fields: CustomFieldItem[]; scope: string; prefix?: string }>(),
  { prefix: 'customFields' },
)
const errors = inject(purchaseErrors)
</script>
