<template>
  <div class="bg-surface rounded-xl shadow-sm border border-line-default">
    <div
      v-if="hasHeaderSlot"
      class="px-5 py-4 text-heading border-b border-line-light border-solid"
    >
      <slot name="header" />
    </div>
    <div :class="containerClass">
      <slot />
    </div>
    <div
      v-if="hasFooterSlot"
      class="px-5 py-4 border-t border-line-light border-solid sm:px-6"
    >
      <slot name="footer" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, useSlots } from 'vue'

interface Props {
  containerClass?: string
}

withDefaults(defineProps<Props>(), {
  containerClass: 'px-4 py-5 sm:px-8 sm:py-8',
})

const slots = useSlots()

const hasHeaderSlot = computed<boolean>(() => {
  return !!slots.header
})
const hasFooterSlot = computed<boolean>(() => {
  return !!slots.footer
})
</script>
