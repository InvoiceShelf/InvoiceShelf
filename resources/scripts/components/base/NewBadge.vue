<script setup lang="ts">

interface Props {
  /** Note: original prop name was "sucess" (sic) — preserved for backwards compatibility */
  sucess?: boolean
}

withDefaults(defineProps<Props>(), {
  sucess: false,
})
</script>

<template>
  <span
    :class="[
      sucess ? 'bg-green-500/25 text-status-green' : 'bg-red-500/25 text-status-red',
      'px-2 py-1 text-sm font-normal text-center uppercase',
    ]"
  >
    <slot />
  </span>
</template>
