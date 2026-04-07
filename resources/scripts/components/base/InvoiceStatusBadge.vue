<script setup lang="ts">
import { computed } from 'vue'
import { InvoiceStatus, InvoicePaidStatus } from '@/scripts/types/domain'

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

const baseClasses = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium'

const badgeColorClasses = computed<string>(() => {
  switch (props.status) {
    case InvoiceStatus.DRAFT:
    case 'DRAFT':
      return `${baseClasses} bg-gray-100 text-gray-700 ring-1 ring-inset ring-gray-300/50`
    case InvoiceStatus.SENT:
    case 'SENT':
      return `${baseClasses} bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-300/50`
    case InvoiceStatus.VIEWED:
    case 'VIEWED':
      return `${baseClasses} bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-300/50`
    case InvoiceStatus.COMPLETED:
    case 'COMPLETED':
      return `${baseClasses} bg-green-50 text-green-700 ring-1 ring-inset ring-green-300/50`
    case 'DUE':
      return `${baseClasses} bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-300/50`
    case 'OVERDUE':
      return `${baseClasses} bg-red-50 text-red-700 ring-1 ring-inset ring-red-300/50`
    case InvoicePaidStatus.UNPAID:
    case 'UNPAID':
      return `${baseClasses} bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-300/50`
    case InvoicePaidStatus.PARTIALLY_PAID:
    case 'PARTIALLY_PAID':
      return `${baseClasses} bg-cyan-50 text-cyan-700 ring-1 ring-inset ring-cyan-300/50`
    case InvoicePaidStatus.PAID:
    case 'PAID':
      return `${baseClasses} bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-300/50`
    default:
      return `${baseClasses} bg-surface-secondary text-muted ring-1 ring-inset ring-line-default`
  }
})
</script>

<template>
  <span :class="badgeColorClasses">
    <slot />
  </span>
</template>
