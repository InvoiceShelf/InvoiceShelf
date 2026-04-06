<script setup lang="ts">
import { computed } from 'vue'
import { InvoicePaidStatus } from '@v2/types/domain'

type PaidBadgeStatus = InvoicePaidStatus | 'OVERDUE' | string

interface Props {
  status?: PaidBadgeStatus
}

const props = withDefaults(defineProps<Props>(), {
  status: '',
})

const baseClasses = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium'

const badgeColorClasses = computed<string>(() => {
  switch (props.status) {
    case InvoicePaidStatus.PAID:
    case 'PAID':
      return `${baseClasses} bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-300/50`
    case InvoicePaidStatus.UNPAID:
    case 'UNPAID':
      return `${baseClasses} bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-300/50`
    case InvoicePaidStatus.PARTIALLY_PAID:
    case 'PARTIALLY_PAID':
      return `${baseClasses} bg-cyan-50 text-cyan-700 ring-1 ring-inset ring-cyan-300/50`
    case 'OVERDUE':
      return `${baseClasses} bg-red-50 text-red-700 ring-1 ring-inset ring-red-300/50`
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
