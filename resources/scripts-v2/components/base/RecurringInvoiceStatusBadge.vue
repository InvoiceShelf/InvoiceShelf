<script setup lang="ts">
import { computed } from 'vue'
import { RecurringInvoiceStatus } from '@v2/types/domain'

interface Props {
  status?: RecurringInvoiceStatus | string
}

const props = withDefaults(defineProps<Props>(), {
  status: '',
})

const baseClasses = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium'

const badgeColorClasses = computed<string>(() => {
  switch (props.status) {
    case RecurringInvoiceStatus.ACTIVE:
    case 'ACTIVE':
      return `${baseClasses} bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-300/50`
    case RecurringInvoiceStatus.ON_HOLD:
    case 'ON_HOLD':
      return `${baseClasses} bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-300/50`
    case RecurringInvoiceStatus.COMPLETED:
    case 'COMPLETED':
      return `${baseClasses} bg-green-50 text-green-700 ring-1 ring-inset ring-green-300/50`
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
