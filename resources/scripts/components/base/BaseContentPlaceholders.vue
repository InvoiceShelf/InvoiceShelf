<template>
  <div :class="classObject">
    <slot />
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  rounded: {
    type: Boolean,
    default: false,
  },
  centered: {
    type: Boolean,
    default: false,
  },
  animated: {
    type: Boolean,
    default: true,
  },
})

const classObject = computed(() => {
  return {
    'base-content-placeholders': true,
    'base-content-placeholders-is-rounded': props.rounded,
    'base-content-placeholders-is-centered': props.centered,
    'base-content-placeholders-is-animated': props.animated,
  }
})
</script>
