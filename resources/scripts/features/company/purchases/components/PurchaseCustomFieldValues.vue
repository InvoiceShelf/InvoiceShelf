<template>
  <dl v-if="answered.length" class="grid gap-4 text-sm sm:grid-cols-2">
    <div v-for="field in answered" :key="field.custom_field_id">
      <dt class="text-muted">{{ field.custom_field?.label }}</dt>
      <dd class="mt-1 break-words whitespace-pre-wrap text-heading">
        {{ display(field) }}
      </dd>
    </div>
  </dl>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { CustomFieldValue } from '@/scripts/types/domain/custom-field'
const props = defineProps<{ fields?: CustomFieldValue[] }>()
const { t } = useI18n()
const answered = computed(() =>
  (props.fields || [])
    .filter(
      (field) =>
        field.custom_field &&
        field.default_answer !== null &&
        field.default_answer !== '',
    )
    .sort(
      (a, b) =>
        Number(a.custom_field?.order) - Number(b.custom_field?.order) ||
        a.custom_field_id - b.custom_field_id,
    ),
)
function display(field: CustomFieldValue) {
  if (field.type === 'Switch')
    return t(
      field.default_answer === true || Number(field.default_answer) === 1
        ? 'general.yes'
        : 'general.no',
    )
  return field.default_formatted_answer ?? field.default_answer
}
</script>
