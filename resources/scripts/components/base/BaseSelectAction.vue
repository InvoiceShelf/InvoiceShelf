<script setup lang="ts">
/**
 * An action at the foot of a BaseMultiselect dropdown, such as "Add new item"
 * or "Add new customer". A real button, so a keyboard user can tab to it from
 * the open list.
 */
</script>

<template>
  <button
    type="button"
    class="
      flex items-center justify-center w-full px-6 py-2 text-sm font-medium bg-surface-muted
      text-primary-600 hover:bg-hover-strong focus:outline-hidden focus-visible:bg-hover-strong
      focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-focus
    "
  >
    <slot />
  </button>
</template>
