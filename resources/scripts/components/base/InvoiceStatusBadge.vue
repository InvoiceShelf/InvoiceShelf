<script setup lang="ts">
import { computed } from 'vue'
import { InvoiceStatus, InvoicePaidStatus } from '@/scripts/types/domain'
import BaseStatusPill from './BaseStatusPill.vue'
import type { StatusTone } from './BaseStatusPill.vue'

type InvoiceBadgeStatus =
  | InvoiceStatus
  | InvoicePaidStatus
  | 'DUE'
  | 'OVERDUE'

interface Props {
  status?: InvoiceBadgeStatus | string
}

const props = withDefaults(defineProps<Props>(), {
  status: '',
})

const TONES: Record<string, StatusTone> = {
  [InvoiceStatus.DRAFT]: 'gray',
  [InvoiceStatus.SENT]: 'blue',
  [InvoiceStatus.VIEWED]: 'blue',
  [InvoiceStatus.COMPLETED]: 'green',
  DUE: 'yellow',
  OVERDUE: 'red',
  [InvoicePaidStatus.UNPAID]: 'yellow',
  [InvoicePaidStatus.PARTIALLY_PAID]: 'purple',
  [InvoicePaidStatus.PAID]: 'green',
}

const tone = computed<StatusTone>(() => TONES[props.status] ?? 'gray')
</script>

<template>
  <BaseStatusPill :tone="tone">
    <slot />
  </BaseStatusPill>
</template>
