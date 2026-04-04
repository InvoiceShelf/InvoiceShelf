<script setup lang="ts">
import { computed } from 'vue'
import { RecurringInvoiceStatus } from '../../types/domain'

interface Props {
  status?: RecurringInvoiceStatus | string
}

const props = withDefaults(defineProps<Props>(), {
  status: '',
})

const badgeColorClasses = computed<string>(() => {
  switch (props.status) {
    case RecurringInvoiceStatus.COMPLETED:
    case 'COMPLETED':
      return 'bg-green-500/25 px-2 py-1 text-sm text-status-green uppercase font-normal text-center'
    case RecurringInvoiceStatus.ON_HOLD:
    case 'ON_HOLD':
      return 'bg-yellow-500/25 px-2 py-1 text-sm text-status-yellow uppercase font-normal text-center'
    case RecurringInvoiceStatus.ACTIVE:
    case 'ACTIVE':
      return 'bg-blue-400/25 px-2 py-1 text-sm text-status-blue uppercase font-normal text-center'
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
