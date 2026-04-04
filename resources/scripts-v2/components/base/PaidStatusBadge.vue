<script setup lang="ts">
import { computed } from 'vue'
import { InvoicePaidStatus } from '@v2/types/domain'

type PaidBadgeStatus = InvoicePaidStatus | 'OVERDUE' | string

interface Props {
  status?: PaidBadgeStatus
  defaultClass?: string
}

const props = withDefaults(defineProps<Props>(), {
  status: '',
  defaultClass: 'px-1 py-0.5 text-xs',
})

const badgeColorClasses = computed<string>(() => {
  switch (props.status) {
    case InvoicePaidStatus.PAID:
    case 'PAID':
      return 'bg-green-500/40 text-status-green uppercase font-semibold text-center'
    case InvoicePaidStatus.UNPAID:
    case 'UNPAID':
      return 'bg-yellow-500/25 text-status-yellow uppercase font-normal text-center'
    case InvoicePaidStatus.PARTIALLY_PAID:
    case 'PARTIALLY_PAID':
      return 'bg-blue-400/25 text-status-blue uppercase font-normal text-center'
    case 'OVERDUE':
      return 'bg-red-300/50 px-2 py-1 text-sm text-status-red uppercase font-normal text-center'
    default:
      return 'bg-surface-secondary0/25 text-heading uppercase font-normal text-center'
  }
})
</script>

<template>
  <span :class="[badgeColorClasses, defaultClass]">
    <slot />
  </span>
</template>
