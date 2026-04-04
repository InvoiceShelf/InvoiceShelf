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
        case 'COMPLETED':
          return 'bg-green-500/25 px-2  py-1 text-sm  text-status-green uppercase font-normal text-center'
        case 'ON_HOLD':
          return 'bg-yellow-500/25 px-2  py-1 text-sm  text-status-yellow uppercase font-normal text-center'
        case 'ACTIVE':
          return 'bg-blue-400/25 px-2  py-1 text-sm  text-status-blue uppercase font-normal text-center'
        default:
          return 'bg-surface-secondary0/25 px-2 py-1 text-sm  text-heading uppercase font-normal text-center'
      }
    })
    return { badgeColorClasses }
  },
}
</script>
