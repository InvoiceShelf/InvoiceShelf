<template>
  <BaseStatusPill :tone="tone">{{
    $t(`purchases.state_${state}`)
  }}</BaseStatusPill>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import type { PurchaseKind } from '@/scripts/types/domain/purchase'
import type { StatusTone } from '@/scripts/components/base/BaseStatusPill.vue'
import { localDate } from '../helpers'
const props = defineProps<{
  kind: PurchaseKind
  record: {
    enabled?: boolean
    status?: string
    settlement_status?: string
    due_amount?: number
    due_date?: string
  }
}>()
const state = computed(() => {
  if (props.kind === 'suppliers')
    return props.record.enabled ? 'ACTIVE' : 'INACTIVE'
  if (props.kind === 'bills' && props.record.status === 'OPEN') {
    if (
      (props.record.due_amount || 0) > 0 &&
      (props.record.due_date || '') < localDate()
    )
      return 'OVERDUE'
    return props.record.settlement_status
  }
  return props.record.status
})
const tone = computed<StatusTone>(() => {
  // A schedule reads like a recurring invoice: running, held, or done.
  if (props.kind === 'recurring-costs')
    return (
      ({ ACTIVE: 'blue', ON_HOLD: 'yellow', COMPLETED: 'green' }) as Record<
        string,
        StatusTone
      >
    )[state.value || ''] ?? 'gray'
  if (['SETTLED', 'ACTIVE'].includes(state.value || '')) return 'green'
  if (state.value === 'OVERDUE') return 'red'
  if (['UNPAID', 'PARTIAL'].includes(state.value || '')) return 'yellow'
  if (state.value === 'OPEN') return 'blue'
  return 'gray'
})
</script>
