<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RecurringInvoiceStatus } from '@/scripts/types/domain'

interface Props {
  status?: string
}

const props = withDefaults(defineProps<Props>(), {
  status: '',
})

const { t } = useI18n()

const labelStatus = computed<string>(() => {
  switch (props.status) {
    case RecurringInvoiceStatus.COMPLETED:
    case 'COMPLETED':
      return t('recurring_invoices.complete')
    case RecurringInvoiceStatus.ON_HOLD:
    case 'ON_HOLD':
      return t('recurring_invoices.on_hold')
    case RecurringInvoiceStatus.ACTIVE:
    case 'ACTIVE':
      return t('recurring_invoices.active')
    default:
      return props.status
  }
})
</script>

<template>
  {{ labelStatus }}
</template>
