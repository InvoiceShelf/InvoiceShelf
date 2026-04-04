<template>
  <span :class="badgeColorClasses">
    <slot />
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: {
    type: String,
    required: false,
    default: '',
  },
})

const badgeColorClasses = computed(() => {
  switch (props.status) {
    case 'DRAFT':
      return 'bg-yellow-300/25 px-2  py-1 text-sm  text-status-yellow uppercase font-normal text-center '
    case 'SENT':
      return ' bg-yellow-500/25 px-2 py-1 text-sm  text-status-yellow uppercase font-normal text-center '
    case 'VIEWED':
      return 'bg-blue-400/25 px-2  py-1 text-sm  text-status-blue uppercase font-normal text-center'
    case 'EXPIRED':
      return 'bg-red-300/25 px-2 py-1 text-sm  text-status-red uppercase font-normal text-center'
    case 'ACCEPTED':
      return 'bg-green-400/25 px-2 py-1 text-sm  text-status-green uppercase font-normal text-center'
    case 'REJECTED':
      return 'bg-purple-300/25 px-2 py-1 text-sm  text-status-purple uppercase font-normal text-center'
    default:
      return 'bg-surface-secondary0/25 px-2 py-1 text-sm  text-heading uppercase font-normal text-center'
  }
})
</script>
