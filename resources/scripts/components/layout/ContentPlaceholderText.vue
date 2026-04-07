<template>
  <div class="base-content-placeholders-text">
    <div
      v-for="n in lines"
      :key="n"
      :class="lineClass"
      class="w-full h-full base-content-placeholders-text__line"
    />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  lines?: number
  rounded?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  lines: 4,
  rounded: false,
})

const lineClass = computed<Record<string, boolean>>(() => {
  return {
    'base-content-placeholders-is-rounded': props.rounded,
  }
})
</script>
