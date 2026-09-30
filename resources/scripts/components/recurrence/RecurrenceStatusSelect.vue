<template>
  <BaseInputGroup
    :label="$t('recurring_invoices.status')"
    :content-loading="contentLoading"
    :error="error"
    required
  >
    <BaseMultiselect
      v-model="status"
      :options="options"
      :content-loading="contentLoading"
      :disabled="disabled"
      :can-deselect="false"
      :placeholder="$t('recurring_invoices.select_a_status')"
      value-prop="value"
      label="label"
    />
  </BaseInputGroup>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

interface Props {
  /** Offer Completed too; a new schedule starts active or on hold. */
  includeCompleted?: boolean
  contentLoading?: boolean
  disabled?: boolean
  error?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  includeCompleted: false,
  contentLoading: false,
  disabled: false,
  error: null,
})

const status = defineModel<string | null>({ default: 'ACTIVE' })

const { t } = useI18n()

const options = computed(() => [
  { label: t('recurring_invoices.active'), value: 'ACTIVE' },
  { label: t('recurring_invoices.on_hold'), value: 'ON_HOLD' },
  ...(props.includeCompleted
    ? [{ label: t('recurring_invoices.completed'), value: 'COMPLETED' }]
    : []),
])
</script>
