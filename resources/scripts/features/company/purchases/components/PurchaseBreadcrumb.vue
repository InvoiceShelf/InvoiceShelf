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
import { purchaseParent, recurringLabel } from '../navigation'
const props = defineProps<{
  kind: PurchaseKind
  /** For recurring costs: BILL or EXPENSE. */
  mode?: string | null
  title?: string
}>()
const router = useRouter()
const parent = computed(
  () => router.resolve(purchaseParent(props.kind, props.mode)).fullPath,
)
const label = computed(() =>
  props.kind === 'recurring-costs'
    ? recurringLabel(props.mode, 'title')
    : `purchases.${props.kind}`,
)
</script>
