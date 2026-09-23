<script setup lang="ts">
import { computed } from 'vue'
import { RecurringInvoiceStatus } from '@/scripts/types/domain'
import BaseStatusPill from './BaseStatusPill.vue'
import type { StatusTone } from './BaseStatusPill.vue'

interface Props {
  status?: RecurringInvoiceStatus | string
}

const props = withDefaults(defineProps<Props>(), {
  status: '',
})

const TONES: Record<string, StatusTone> = {
  [RecurringInvoiceStatus.ACTIVE]: 'blue',
  [RecurringInvoiceStatus.ON_HOLD]: 'yellow',
  [RecurringInvoiceStatus.COMPLETED]: 'green',
}

const tone = computed<StatusTone>(() => TONES[props.status] ?? 'gray')
</script>

<template>
  <BaseStatusPill :tone="tone">
    <slot />
  </BaseStatusPill>
</template>
