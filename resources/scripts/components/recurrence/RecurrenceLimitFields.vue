<template>
  <BaseInputGroup
    :label="$t('recurring_invoices.limit_by')"
    :content-loading="contentLoading"
    :error="errors?.limit_by"
    required
  >
    <BaseMultiselect
      v-model="limitBy"
      :content-loading="contentLoading"
      :options="limits"
      :disabled="disabled"
      :can-deselect="false"
      label="label"
      value-prop="value"
    />
  </BaseInputGroup>

  <BaseInputGroup
    v-if="limitBy === 'DATE'"
    :label="$t('recurring_invoices.limit_date')"
    :content-loading="contentLoading"
    :error="errors?.limit_date"
    required
  >
    <BaseDatePicker
      v-model="limitDate"
      :content-loading="contentLoading"
      :disabled="disabled"
      calendar-button-icon="calendar"
    />
  </BaseInputGroup>

  <BaseInputGroup
    v-if="limitBy === 'COUNT'"
    :label="$t('recurring_invoices.count')"
    :content-loading="contentLoading"
    :error="errors?.limit_count"
    required
  >
    <BaseInput
      v-model="limitCount"
      :content-loading="contentLoading"
      :disabled="disabled"
      type="number"
      min="1"
      :max="maxCount"
    />
  </BaseInputGroup>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

export type RecurrenceLimitBy = 'NONE' | 'DATE' | 'COUNT'

interface Props {
  contentLoading?: boolean
  disabled?: boolean
  maxCount?: number
  /** Validation errors keyed limit_by, limit_date and limit_count. */
  errors?: Partial<Record<'limit_by' | 'limit_date' | 'limit_count', string>>
}

withDefaults(defineProps<Props>(), {
  contentLoading: false,
  disabled: false,
  maxCount: 10000,
  errors: undefined,
})

const limitBy = defineModel<RecurrenceLimitBy | string>('limitBy', {
  default: 'NONE',
})
const limitCount = defineModel<number | string | null>('limitCount', {
  default: null,
})
const limitDate = defineModel<string | null>('limitDate', { default: null })

const { t } = useI18n()

const limits = computed(() => [
  { label: t('recurring_invoices.limit.none'), value: 'NONE' },
  { label: t('recurring_invoices.limit.date'), value: 'DATE' },
  { label: t('recurring_invoices.limit.count'), value: 'COUNT' },
])
</script>
