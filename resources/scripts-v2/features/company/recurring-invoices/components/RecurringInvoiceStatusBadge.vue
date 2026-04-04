<script setup lang="ts">
/**
 * Re-export of the base RecurringInvoiceStatusBadge component.
 * Use this import path within the recurring-invoices feature for convenience.
 */
</script>

<template>
  <BaseRecurringInvoiceStatusBadge v-bind="$attrs">
    <slot />
  </BaseRecurringInvoiceStatusBadge>
</template>
