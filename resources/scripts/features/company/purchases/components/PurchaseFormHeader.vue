<template>
  <BasePageHeader :title="title" :help="help" phone-actions="bar">
    <PurchaseBreadcrumb :kind="kind" :mode="mode" :title="title" />
    <template #actions><slot name="actions" /></template>
  </BasePageHeader>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { purchaseHelpKeys, recurringLabel } from '../navigation'
import type { PurchaseKind } from '@/scripts/types/domain/purchase'
import PurchaseBreadcrumb from './PurchaseBreadcrumb.vue'
const props = defineProps<{
  title: string
  kind: PurchaseKind
  /** For recurring costs: BILL or EXPENSE. */
  mode?: string | null
}>()
const { t } = useI18n()
const help = computed(() =>
  props.kind === 'recurring-costs'
    ? [
        t(recurringLabel(props.mode, 'help')),
        t('purchases.schedule_help'),
        t('purchases.saved_rate_help'),
      ].join('\n\n')
    : t(purchaseHelpKeys[props.kind]),
)
</script>
