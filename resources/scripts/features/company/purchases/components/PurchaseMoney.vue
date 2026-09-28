<template>
  <BaseMoney
    :key="currency?.id"
    v-model="major"
    :currency="currency"
    v-bind="$attrs"
  />
</template>
<script setup lang="ts">
import { computed } from 'vue'
import type { Currency } from '@/scripts/types/domain/currency'
defineOptions({ inheritAttrs: false })
const model = defineModel<number>({ required: true })
defineProps<{ currency?: Currency | null }>()
const major = computed({
  get: () => model.value / 100,
  set: (value: string | number) => {
    model.value = Math.round(Number(value) * 100)
  },
})
</script>
