<script setup lang="ts">
/**
 * Re-export of the base InvoiceStatusBadge component.
 * Use this import path within the invoices feature for convenience.
 */
</script>

<template>
  <BaseInvoiceStatusBadge v-bind="$attrs">
    <slot />
  </BaseInvoiceStatusBadge>
</template>
