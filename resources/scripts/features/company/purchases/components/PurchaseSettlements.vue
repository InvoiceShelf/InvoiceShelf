<template>
  <BaseCard v-if="settlements.length" container-class="p-4 md:p-5"
    ><h2 class="mb-3 font-semibold text-section text-heading">
      {{ $t('purchases.settlements') }}
    </h2>
    <div
      v-for="settlement in settlements"
      :key="settlement.key"
      class="flex justify-between gap-4 border-b border-line-light py-3 text-sm"
    >
      <router-link :to="settlement.url" class="text-primary-600">{{
        settlement.label
      }}</router-link
      ><BaseFormatMoney
        :amount="settlement.amount"
        :currency="record.currency"
      /></div
  ></BaseCard>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import type {
  PurchaseKind,
  PurchaseRecord,
} from '@/scripts/types/domain/purchase'

const props = defineProps<{ kind: PurchaseKind; record: PurchaseRecord }>()

/** What settles a bill, or which bills a payment or credit settles. */
const settlements = computed(() => {
  const rows = props.record.allocations ?? []
  if (props.kind !== 'bills')
    return rows.map((row) => ({
      key: `bill-${row.id}`,
      url: `/admin/bills/${row.bill_id}/view`,
      label: row.bill?.number ?? String(row.bill_id),
      amount: row.amount,
    }))
  return [
    ...(props.record.payment_allocations ?? []).map((row) => ({
      key: `payment-${row.id}`,
      url: `/admin/supplier-payments/${row.payment?.id}/view`,
      label: row.payment?.number ?? '',
      amount: row.amount,
    })),
    ...(props.record.credit_allocations ?? []).map((row) => ({
      key: `credit-${row.id}`,
      url: `/admin/supplier-credits/${row.credit?.id}/view`,
      label: row.credit?.number ?? '',
      amount: row.amount,
    })),
  ]
})
</script>
