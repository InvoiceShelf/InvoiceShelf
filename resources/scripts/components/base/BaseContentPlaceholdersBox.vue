<template>
  <div class="base-content-placeholders-box" :class="objectClass" />
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  circle: {
    type: Boolean,
    default: false,
  },
  rounded: {
    type: Boolean,
    default: false,
  },
  animated: {
    type: Boolean,
    default: false,
  },
})

const objectClass = computed(() => {
  return {
    'base-content-circle': props.circle,
    'base-content-placeholders-is-rounded': props.rounded,
    'base-content-placeholders-is-animated': props.animated,
  }
})
</script>
