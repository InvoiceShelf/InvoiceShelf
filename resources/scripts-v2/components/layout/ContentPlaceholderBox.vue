<template>
  <div class="base-content-placeholders-box" :class="circleClass" />
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  circle?: boolean
  rounded?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  circle: false,
  rounded: false,
})

const circleClass = computed<Record<string, boolean>>(() => {
  return {
    'base-content-circle': props.circle,
    'base-content-placeholders-is-rounded': props.rounded,
  }
})
</script>
