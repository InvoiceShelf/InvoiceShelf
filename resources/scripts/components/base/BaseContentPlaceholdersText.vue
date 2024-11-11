<template>
  <div :class="objectClass">
    <div
      v-for="n in lines"
      :key="n"
      :class="lineClass"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  lines: {
    type: Number,
    default: 4,
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

const objectClass = computed(() => ({
  'base-content-placeholders-text': true,
  'base-content-placeholders-is-rounded': props.rounded,
  'base-content-placeholders-is-animated': props.animated,
}))

const lineClass = computed(() => ({
  'w-full h-full base-content-placeholders-text__line': true,
  'flex flex-col': props.lines > 1
}))
</script>
