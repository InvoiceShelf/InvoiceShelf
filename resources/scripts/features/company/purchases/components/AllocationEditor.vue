<template>
  <div class="space-y-4">
    <p class="text-sm text-muted">{{ $t('purchases.allocation_help') }}</p>
    <p v-if="error" role="alert" class="text-danger">{{ error }}</p>
    <p v-if="loading" role="status" class="text-muted">
      {{ $t('purchases.loading') }}
    </p>
    <div
      v-for="bill in bills"
      :key="bill.id"
      class="flex flex-wrap items-center justify-between gap-3 border-b border-line-light py-3"
    >
      <router-link
        :to="`/admin/bills/${bill.id}/view`"
        class="text-primary-600"
        >{{ bill.number }}</router-link
      >
      <PurchaseField class="w-48" :label="$t('purchases.allocate')"
        ><BaseMoney
          v-model="amounts[bill.id]"
          :max="((bill.due_amount || 0) + (existing[bill.id] || 0)) / 100"
          :currency="record.currency"
      /></PurchaseField>
    </div>
    <p class="text-sm text-body">
      {{ $t('purchases.available_after') }}
      <BaseFormatMoney :amount="remaining" :currency="record.currency" />
    </p>
    <BaseButton
      :loading="saving"
      :disabled="saving || loading || remaining < 0"
      @click="save"
      >{{ $t('purchases.save_allocations') }}</BaseButton
    >
  </div>
</template>
<script setup lang="ts">
import PurchaseField from '../components/PurchaseField.vue'
import { usePurchaseForm } from '../composables/use-purchase-form'
const { error, setError, clearError } = usePurchaseForm()

import { ref, computed, onMounted } from 'vue'
import { purchaseService } from '@/scripts/api/services/purchase.service'
import type {
  PurchaseKind,
  PurchaseRecord,
} from '@/scripts/types/domain/purchase'
const props = defineProps<{ record: PurchaseRecord; kind: PurchaseKind }>(),
  emit = defineEmits<{ saved: [] }>()
const bills = ref<PurchaseRecord[]>([]),
  amounts = ref<Record<number, number>>({}),
  existing = ref<Record<number, number>>({}),
  loading = ref(true),
  saving = ref(false)
const remaining = computed(
  () =>
    (props.record.available_amount || 0) +
    Object.values(existing.value).reduce((a, b) => a + b, 0) -
    Object.values(amounts.value).reduce(
      (sum, value) => sum + Math.round(Number(value || 0) * 100),
      0,
    ),
)
onMounted(async () => {
  try {
    for (const row of props.record.allocations ?? []) {
      existing.value[row.bill_id] = row.amount
      amounts.value[row.bill_id] = row.amount / 100
    }
    let page = 1,
      last = 1
    do {
      const response = await purchaseService.list('bills', {
        supplier_id: props.record.supplier_id,
        status: 'OPEN',
        limit: 100,
        page,
      })
      bills.value.push(
        ...response.data.filter(
          (bill) =>
            bill.currency_id === props.record.currency_id &&
            ((bill.due_amount ?? 0) > 0 || existing.value[bill.id]),
        ),
      )
      last = response.meta.last_page
      page++
    } while (page <= last)
  } catch (e) {
    setError(e)
  } finally {
    loading.value = false
  }
})
async function save() {
  saving.value = true
  clearError()
  try {
    await purchaseService.allocate(
      props.kind,
      props.record.id,
      Object.entries(amounts.value)
        .filter(([, value]) => Number(value) > 0)
        .map(([bill, amount]) => ({
          bill_id: Number(bill),
          amount: Math.round(Number(amount) * 100),
        })),
    )
    emit('saved')
  } catch (e) {
    setError(e)
  } finally {
    saving.value = false
  }
}
</script>
