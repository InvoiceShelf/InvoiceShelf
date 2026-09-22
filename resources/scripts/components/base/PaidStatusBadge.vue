<script setup lang="ts">
import { computed } from 'vue'
import { InvoicePaidStatus } from '@/scripts/types/domain'
import BaseStatusPill from './BaseStatusPill.vue'
import type { StatusTone } from './BaseStatusPill.vue'

type PaidBadgeStatus = InvoicePaidStatus | 'OVERDUE' | string

interface Props {
  status?: PaidBadgeStatus
}

const props = withDefaults(defineProps<Props>(), {
  status: '',
})

const TONES: Record<string, StatusTone> = {
  [InvoicePaidStatus.PAID]: 'green',
  [InvoicePaidStatus.UNPAID]: 'yellow',
  [InvoicePaidStatus.PARTIALLY_PAID]: 'purple',
  OVERDUE: 'red',
}

const tone = computed<StatusTone>(() => TONES[props.status] ?? 'gray')
</script>

<template>
  <BaseStatusPill :tone="tone">
    <slot />
  </BaseStatusPill>
</template>
