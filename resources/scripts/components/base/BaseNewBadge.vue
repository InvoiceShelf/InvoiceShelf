<template>
  <span
    :class="[
      sucess ? 'bg-green-500/25 text-status-green ' : 'bg-red-500/25 text-status-red',
      'px-2 py-1 text-sm font-normal text-center uppercase',
    ]"
  >
    <slot />
  </span>
</template>

<script setup>
const props = defineProps({
  sucess: {
    type: Boolean,
    default: false,
  },
})
</script>
