<script setup lang="ts">
import { computed } from 'vue'
import { InvoiceStatus, InvoicePaidStatus } from '../../types/domain'

type InvoiceBadgeStatus =
  | InvoiceStatus
  | InvoicePaidStatus
  | 'DUE'
  | 'OVERDUE'

interface Props {
  status?: InvoiceBadgeStatus | string
}

const props = withDefaults(defineProps<Props>(), {
  status: '',
})

const badgeColorClasses = computed<string>(() => {
  switch (props.status) {
    case InvoiceStatus.DRAFT:
    case 'DRAFT':
      return 'bg-yellow-300/25 px-2 py-1 text-sm text-status-yellow uppercase font-normal text-center'
    case InvoiceStatus.SENT:
    case 'SENT':
      return 'bg-green-500/25 px-2 py-1 text-sm text-status-green uppercase font-normal text-center'
    case InvoiceStatus.VIEWED:
    case 'VIEWED':
      return 'bg-blue-400/25 px-2 py-1 text-sm text-status-blue uppercase font-normal text-center'
    case InvoiceStatus.COMPLETED:
    case 'COMPLETED':
      return 'bg-green-500/25 px-2 py-1 text-sm text-status-green uppercase font-normal text-center'
    case 'DUE':
      return 'bg-yellow-500/25 px-2 py-1 text-sm text-status-yellow uppercase font-normal text-center'
    case 'OVERDUE':
      return 'bg-red-300/50 px-2 py-1 text-sm text-status-red uppercase font-normal text-center'
    case InvoicePaidStatus.UNPAID:
    case 'UNPAID':
      return 'bg-yellow-500/25 px-2 py-1 text-sm text-status-yellow uppercase font-normal text-center'
    case InvoicePaidStatus.PARTIALLY_PAID:
    case 'PARTIALLY_PAID':
      return 'bg-blue-400/25 px-2 py-1 text-sm text-status-blue uppercase font-normal text-center'
    case InvoicePaidStatus.PAID:
    case 'PAID':
      return 'bg-green-500/40 px-2 py-1 text-sm text-status-green uppercase font-semibold text-center'
    default:
      return 'bg-surface-secondary0/25 px-2 py-1 text-sm text-heading uppercase font-normal text-center'
  }
})
</script>

<template>
  <span :class="badgeColorClasses">
    <slot />
  </span>
</template>
