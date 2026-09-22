<script setup lang="ts">
import { computed } from 'vue'
import { EstimateStatus } from '@/scripts/types/domain'
import BaseStatusPill from './BaseStatusPill.vue'
import type { StatusTone } from './BaseStatusPill.vue'

interface Props {
  status?: EstimateStatus | string
}

const props = withDefaults(defineProps<Props>(), {
  status: '',
})

const TONES: Record<string, StatusTone> = {
  [EstimateStatus.DRAFT]: 'gray',
  [EstimateStatus.SENT]: 'blue',
  [EstimateStatus.VIEWED]: 'blue',
  [EstimateStatus.EXPIRED]: 'red',
  [EstimateStatus.ACCEPTED]: 'green',
  [EstimateStatus.REJECTED]: 'red',
}

const tone = computed<StatusTone>(() => TONES[props.status] ?? 'gray')
</script>

<template>
  <BaseStatusPill :tone="tone">
    <slot />
  </BaseStatusPill>
</template>
