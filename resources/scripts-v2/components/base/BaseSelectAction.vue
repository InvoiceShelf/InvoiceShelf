<script setup lang="ts">
/**
 * A simple action slot component used inside BaseMultiselect dropdowns.
 * Renders a clickable row at the bottom of the dropdown for actions like
 * "Add new item" or "Add new customer".
 */
</script>

<template>
  <div
    class="
      flex
      items-center
      justify-center
      w-full
      px-6
      py-2
      text-sm
      bg-surface-muted
      cursor-pointer
      text-primary-400
    "
  >
    <slot />
  </div>
</template>
