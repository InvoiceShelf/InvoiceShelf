<template>
  <span :class="badgeColorClasses">
    <slot />
  </span>
</template>

<script>
import { computed } from 'vue'

export default {
  props: {
    status: {
      type: String,
      required: false,
      default: '',
    },
  },

  setup(props) {
    const badgeColorClasses = computed(() => {
      switch (props.status) {
        case 'DRAFT':
          return 'bg-yellow-300/25 px-2  py-1 text-sm  text-status-yellow uppercase font-normal text-center'
        case 'SENT':
          return 'bg-green-500/25 px-2 py-1 text-sm text-status-green uppercase font-normal text-center'
        case 'VIEWED':
          return 'bg-blue-400/25 px-2  py-1 text-sm  text-status-blue uppercase font-normal text-center'
        case 'COMPLETED':
          return 'bg-green-500/25 px-2  py-1 text-sm  text-status-green uppercase font-normal text-center'
        case 'DUE':
          return 'bg-yellow-500/25 px-2  py-1 text-sm  text-status-yellow uppercase font-normal text-center'
        case 'OVERDUE':
          return 'bg-red-300/50 px-2  py-1 text-sm  text-status-red uppercase font-normal text-center'
        case 'UNPAID':
          return 'bg-yellow-500/25 px-2 py-1 text-sm  text-status-yellow uppercase font-normal text-center'
        case 'PARTIALLY_PAID':
          return 'bg-blue-400/25 px-2  py-1 text-sm  text-status-blue uppercase font-normal text-center'
        case 'PAID':
          return 'bg-green-500/40 px-2 py-1 text-sm text-status-green uppercase font-semibold text-center'
        default:
          return 'bg-surface-secondary0/25 px-2 py-1 text-sm  text-heading uppercase font-normal text-center'
      }
    })
    return { badgeColorClasses }
  },
}
</script>
