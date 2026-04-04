<script setup lang="ts">
import { computed } from 'vue'
import { EstimateStatus } from '../../types/domain'

interface Props {
  status?: EstimateStatus | string
}

const props = withDefaults(defineProps<Props>(), {
  status: '',
})

const badgeColorClasses = computed<string>(() => {
  switch (props.status) {
    case EstimateStatus.DRAFT:
    case 'DRAFT':
      return 'bg-yellow-300/25 px-2 py-1 text-sm text-status-yellow uppercase font-normal text-center'
    case EstimateStatus.SENT:
    case 'SENT':
      return 'bg-green-500/25 px-2 py-1 text-sm text-status-green uppercase font-normal text-center'
    case EstimateStatus.VIEWED:
    case 'VIEWED':
      return 'bg-blue-400/25 px-2 py-1 text-sm text-status-blue uppercase font-normal text-center'
    case EstimateStatus.EXPIRED:
    case 'EXPIRED':
      return 'bg-red-300/25 px-2 py-1 text-sm text-status-red uppercase font-normal text-center'
    case EstimateStatus.ACCEPTED:
    case 'ACCEPTED':
      return 'bg-green-400/25 px-2 py-1 text-sm text-status-green uppercase font-normal text-center'
    case EstimateStatus.REJECTED:
    case 'REJECTED':
      return 'bg-purple-300/25 px-2 py-1 text-sm text-status-purple uppercase font-normal text-center'
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
