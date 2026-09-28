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
  title?: string
}>()
const router = useRouter()
const parent = computed(
  () => router.resolve(purchaseParent(props.kind)).fullPath,
)
const label = computed(() => `purchases.${props.kind}`)
</script>
