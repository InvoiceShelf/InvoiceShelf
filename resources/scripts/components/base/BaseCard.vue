<template>
  <div class="border glass rounded-xl">
    <div
      v-if="hasHeaderSlot"
      class="px-4 py-3.5 font-semibold border-b border-solid md:px-6 text-heading border-line-light"
    >
      <slot name="header" />
    </div>
    <div :class="containerClass">
      <slot />
    </div>
    <div
      v-if="hasFooterSlot"
      class="px-4 py-3.5 border-t border-solid md:px-6 border-line-light"
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
  containerClass: 'px-4 py-5 md:px-6 md:py-6',
})

const slots = useSlots()

const hasHeaderSlot = computed<boolean>(() => {
  return !!slots.header
})
const hasFooterSlot = computed<boolean>(() => {
  return !!slots.footer
})
</script>
