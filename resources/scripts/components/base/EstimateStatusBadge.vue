<script setup lang="ts">
import { computed } from 'vue'
import { EstimateStatus } from '@/scripts/types/domain'

interface Props {
  status?: EstimateStatus | string
}

const props = withDefaults(defineProps<Props>(), {
  status: '',
})

const baseClasses = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium'

const badgeColorClasses = computed<string>(() => {
  switch (props.status) {
    case EstimateStatus.DRAFT:
    case 'DRAFT':
      return `${baseClasses} bg-gray-100 text-gray-700 ring-1 ring-inset ring-gray-300/50`
    case EstimateStatus.SENT:
    case 'SENT':
      return `${baseClasses} bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-300/50`
    case EstimateStatus.VIEWED:
    case 'VIEWED':
      return `${baseClasses} bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-300/50`
    case EstimateStatus.EXPIRED:
    case 'EXPIRED':
      return `${baseClasses} bg-red-50 text-red-700 ring-1 ring-inset ring-red-300/50`
    case EstimateStatus.ACCEPTED:
    case 'ACCEPTED':
      return `${baseClasses} bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-300/50`
    case EstimateStatus.REJECTED:
    case 'REJECTED':
      return `${baseClasses} bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-300/50`
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
