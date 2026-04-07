<script setup lang="ts">
/**
 * Re-export of the base PaidStatusBadge component.
 * Use this import path within the payments feature for convenience.
 */
</script>

<template>
  <BasePaidStatusBadge v-bind="$attrs">
    <slot />
  </BasePaidStatusBadge>
</template>
