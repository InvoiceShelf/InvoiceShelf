<template>
  <BasePageHeader
    :title="title"
    :help="$t(purchaseHelpKeys[kind])"
    phone-actions="bar"
  >
    <PurchaseBreadcrumb :kind="kind" :title="title" />
    <template #actions><slot name="actions" /></template>
  </BasePageHeader>
</template>
<script setup lang="ts">
import { purchaseHelpKeys } from '../navigation'
import type { PurchaseKind } from '@/scripts/types/domain/purchase'
import PurchaseBreadcrumb from './PurchaseBreadcrumb.vue'
defineProps<{
  title: string
  kind: PurchaseKind
}>()
</script>
