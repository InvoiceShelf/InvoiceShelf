<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { InvoiceStatus, InvoicePaidStatus } from '@v2/types/domain'

interface Props {
  status?: string
}

const props = withDefaults(defineProps<Props>(), {
  status: '',
})

const { t } = useI18n()

const labelStatus = computed<string>(() => {
  switch (props.status) {
    case InvoiceStatus.DRAFT:
    case 'DRAFT':
      return t('general.draft')
    case InvoiceStatus.SENT:
    case 'SENT':
      return t('general.sent')
    case InvoiceStatus.VIEWED:
    case 'VIEWED':
      return t('invoices.viewed')
    case InvoiceStatus.COMPLETED:
    case 'COMPLETED':
      return t('invoices.completed')
    case 'DUE':
      return t('general.due')
    case 'OVERDUE':
      return t('invoices.overdue')
    case InvoicePaidStatus.UNPAID:
    case 'UNPAID':
      return t('invoices.unpaid')
    case InvoicePaidStatus.PARTIALLY_PAID:
    case 'PARTIALLY_PAID':
      return t('invoices.partially_paid')
    case InvoicePaidStatus.PAID:
    case 'PAID':
      return t('invoices.paid')
    default:
      return props.status
  }
})
</script>

<template>
  {{ labelStatus }}
</template>
