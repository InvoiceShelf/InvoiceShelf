<template>
  <BasePage>
    <form id="purchase-refundformview" class="space-y-5" @submit.prevent="save">
      <PurchaseFormHeader
        kind="supplier-refunds"
        :title="$t('purchases.new_refund')"
        ><template #actions
          ><BaseButton variant="white" type="button" @click="router.back()">{{
            $t('purchases.cancel')
          }}</BaseButton
          ><BaseButton
            type="submit"
            form="purchase-refundformview"
            :loading="saving"
            :disabled="saving || loading || !source"
            >{{ $t('purchases.save_refund') }}</BaseButton
          ></template
        ></PurchaseFormHeader
      >
      <p v-if="error" role="alert" class="text-danger">{{ error }}</p>
      <div
        class="grid items-start gap-5"
        :class="source ? 'lg:grid-cols-2' : ''"
      >
        <SupplierPicker
          v-if="source"
          :model-value="source.supplier_id || null"
          :supplier="source.supplier"
          disabled
        />
        <BaseCard
          v-if="!loading"
          class="relative focus-within:z-10"
          container-class="p-4 md:p-5"
        >
          <div class="grid gap-5 md:grid-cols-2">
            <PurchaseField :label="$t('purchases.refund_source')"
              ><BaseMultiselect
                v-model="sourceKind"
                :can-deselect="false"
                :options="[
                  {
                    value: 'supplier-credits',
                    label: $t('purchases.supplier-credits'),
                  },
                  {
                    value: 'supplier-payments',
                    label: $t('purchases.advance_payment'),
                  },
                ]"
                searchable
                @change="clearSource"
            /></PurchaseField>
            <BaseInputGroup :label="$t('purchases.source_record')" required
              ><BaseMultiselect
                :key="sourceKind"
                v-model="sourceId"
                :can-deselect="false"
                value-prop="id"
                label="number"
                :options="searchSources"
                :filter-results="false"
                resolve-on-load
                searchable
                :delay="250"
                @update:model-value="selectSource"
            /></BaseInputGroup>
            <div
              v-if="source"
              class="md:col-span-2 rounded-lg bg-surface-secondary p-4 text-sm text-body"
            >
              {{ source.supplier?.name }} · {{ $t('purchases.available') }}
              <BaseFormatMoney
                :amount="source.available_amount || 0"
                :currency="source.currency"
              />
            </div>
            <PurchaseField
              :label="$t('purchases.amount')"
              name="amount"
              required
              ><PurchaseMoney
                v-model="form.amount"
                :currency="source?.currency"
                :disabled="!source"
            /></PurchaseField>
            <PurchaseField
              :label="$t('purchases.refund_date')"
              name="payment_date"
              required
              ><BaseDatePicker v-model="form.payment_date" required
            /></PurchaseField>
            <PurchaseField
              :label="$t('purchases.exchange_rate')"
              name="exchange_rate"
              required
              ><BaseInput
                v-model.number="form.exchange_rate"
                type="number"
                min="0.000001"
                step="0.000001"
                required
            /></PurchaseField>
            <PurchaseField
              :label="$t('purchases.payment_method')"
              name="payment_method_id"
              ><PurchaseLookupSelect
                v-model="form.payment_method_id"
                kind="method"
                :options="options.payment_methods"
            /></PurchaseField>
            <PurchaseField :label="$t('purchases.reference')" name="reference"
              ><BaseInput v-model="form.reference" maxlength="255"
            /></PurchaseField>
            <PurchaseField
              class="md:col-span-2"
              :label="$t('purchases.notes')"
              name="notes"
              ><BaseTextarea v-model="form.notes"
            /></PurchaseField>
          </div>
        </BaseCard>
      </div>
    </form>
    <PurchaseLookupModals :context="lookups" />
  </BasePage>
</template>
<script setup lang="ts">
import { providePurchaseLookups } from '../composables/use-purchase-lookups'
import PurchaseLookupModals from '../components/PurchaseLookupModals.vue'
import PurchaseLookupSelect from '../components/PurchaseLookupSelect.vue'
const lookups = providePurchaseLookups()

import SupplierPicker from '../components/SupplierPicker.vue'
import PurchaseFormHeader from '../components/PurchaseFormHeader.vue'
import PurchaseMoney from '../components/PurchaseMoney.vue'
import PurchaseField from '../components/PurchaseField.vue'
import { usePurchaseForm } from '../composables/use-purchase-form'
const { error, setError, clearError } = usePurchaseForm()

import { ref, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { purchaseService } from '@/scripts/api/services/purchase.service'
import type {
  PurchaseKind,
  PurchaseRecord,
  PurchaseOptions,
} from '@/scripts/types/domain/purchase'
import { localDate } from '../helpers'
const route = useRoute(),
  router = useRouter()
const sourceKind = ref<PurchaseKind>(
    route.query.payment_id ? 'supplier-payments' : 'supplier-credits',
  ),
  sourceId = ref<number | null>(
    Number(route.query.payment_id || route.query.credit_id) || null,
  ),
  source = ref<PurchaseRecord | null>(null)
const options = ref<PurchaseOptions>({
    categories: [],
    currencies: [],
    taxes: [],
    payment_methods: [],
  }),
  saving = ref(false),
  loading = ref(true)
const form = reactive({
  amount: 0,
  payment_date: localDate(),
  exchange_rate: 1,
  payment_method_id: null as number | null,
  reference: '',
  notes: '',
})
function clearSource() {
  source.value = null
  sourceId.value = null
}
async function searchSources(search = '') {
  const rows = (
    await purchaseService.list(sourceKind.value, {
      search,
      status: 'OPEN',
      limit: 100,
    })
  ).data.filter((row) => (row.available_amount ?? 0) > 0)
  if (source.value && !rows.some((row) => row.id === source.value?.id))
    rows.unshift(source.value)
  return rows
}
async function selectSource(id: number | null) {
  if (!id || source.value?.id === id) return
  source.value = null
  try {
    source.value = await purchaseService.get(sourceKind.value, id)
    form.amount = source.value.available_amount ?? 0
    form.exchange_rate = source.value.exchange_rate ?? 1
  } catch (e) {
    setError(e)
  }
}
onMounted(async () => {
  try {
    options.value = await purchaseService.options()
    await selectSource(sourceId.value)
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
    const sourceKey =
      sourceKind.value === 'supplier-payments'
        ? 'supplier_payment_id'
        : 'supplier_credit_id'
    const record = await purchaseService.save('supplier-refunds', {
      ...form,
      [sourceKey]: sourceId.value,
    })
    await router.push(`/admin/supplier-refunds/${record.id}/view`)
  } catch (e) {
    setError(e)
  } finally {
    saving.value = false
  }
}
</script>
