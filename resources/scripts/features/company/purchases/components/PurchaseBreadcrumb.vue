<template>
  <BaseBreadcrumb>
    <BaseBreadcrumbItem :title="$t(label)" :to="parent" />
    <BaseBreadcrumbItem v-if="title" :title="title" to="#" active />
  </BaseBreadcrumb>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import type { PurchaseKind } from '@/scripts/types/domain/purchase'
import { purchaseParent } from '../navigation'
const props = defineProps<{
  kind: PurchaseKind
  mode?: string
  title?: string
}>()
const router = useRouter()
const parent = computed(
  () => router.resolve(purchaseParent(props.kind, props.mode)).fullPath,
)
const label = computed(() =>
  props.kind === 'recurring-costs'
    ? props.mode === 'EXPENSE'
      ? 'purchases.recurring_expenses'
      : 'purchases.recurring_bills'
    : `purchases.${props.kind}`,
)
</script>
