<template>
  <BasePage>
    <form
      id="purchase-paymentformview"
      class="space-y-5"
      @submit.prevent="save"
    >
      <PurchaseFormHeader
        kind="supplier-payments"
        :title="$t('purchases.new_payment')"
        ><template #actions
          ><BaseButton variant="white" type="button" @click="router.back()">{{
            $t('purchases.cancel')
          }}</BaseButton
          ><BaseButton
            type="submit"
            form="purchase-paymentformview"
            :loading="saving"
            :disabled="saving || loading || !form.supplier_id || available < 0"
            >{{ $t('purchases.save_payment') }}</BaseButton
          ></template
        ></PurchaseFormHeader
      >
      <p v-if="error" role="alert" class="text-danger">{{ error }}</p>
      <div v-if="!loading" class="grid items-start gap-5 lg:grid-cols-2">
        <SupplierPicker
          v-model="form.supplier_id"
          :supplier="loadedSupplier"
          @cleared="loadBills"
          @selected="selected"
        />
        <BaseCard
          class="relative focus-within:z-10"
          container-class="p-4 md:p-5"
        >
          <div class="grid gap-5 md:grid-cols-2">
            <PurchaseField
              :label="$t('purchases.currency')"
              name="currency_id"
              required
              ><BaseMultiselect
                v-model="form.currency_id"
                :options="[
                  ...options.currencies.map((option) => ({
                    value: option.id,
                    label: option.code,
                  })),
                ]"
                searchable
                :can-deselect="false"
                @change="loadBills"
            /></PurchaseField>
            <PurchaseField
              :label="$t('purchases.amount')"
              name="amount"
              required
              ><PurchaseMoney v-model="form.amount" :currency="currency"
            /></PurchaseField>
            <PurchaseField
              :label="$t('purchases.payment_date')"
              name="payment_date"
              required
              ><BaseDatePicker v-model="form.payment_date" required
            /></PurchaseField>
            <PurchaseField
              :label="$t('purchases.payment_method')"
              name="payment_method_id"
              ><PurchaseLookupSelect
                v-model="form.payment_method_id"
                kind="method"
                :options="options.payment_methods"
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
              class="md:col-span-2"
              :label="$t('purchases.reference')"
              name="reference"
              ><BaseInput v-model="form.reference" maxlength="255"
            /></PurchaseField>
          </div>
        </BaseCard>
      </div>
      <BaseCard
        v-if="!loading"
        class="relative focus-within:z-10"
        container-class="p-4 md:p-5"
      >
        <h2 class="mb-4 font-medium text-heading">
          {{ $t('purchases.apply_to_bills') }}
        </h2>
        <p v-if="!bills.length" class="text-sm text-muted">
          {{
            $t(
              form.supplier_id
                ? 'purchases.no_open_bills'
                : 'purchases.choose_supplier_first',
            )
          }}
        </p>
        <div
          v-for="bill in bills"
          :key="bill.id"
          class="flex flex-wrap items-center justify-between gap-4 border-b border-line-light py-3"
        >
          <div>
            <p class="font-medium text-heading">{{ bill.number }}</p>
            <p class="text-sm text-muted">
              {{ $t('purchases.due') }}
              <BaseFormatMoney
                :amount="bill.due_amount || 0"
                :currency="bill.currency"
              />
            </p>
          </div>
          <PurchaseField class="w-44" :label="$t('purchases.allocate')"
            ><BaseMoney
              v-model="allocations[bill.id]"
              :max="(bill.due_amount || 0) / 100"
              :currency="currency"
          /></PurchaseField>
        </div>
        <p
          class="mt-4 text-sm"
          :class="available < 0 ? 'text-danger' : 'text-muted'"
        >
          {{ $t('purchases.remaining_advance') }}
          <BaseFormatMoney :amount="available" :currency="currency" />
        </p>
      </BaseCard>
      <BaseCard
        v-if="!loading"
        class="relative focus-within:z-10"
        container-class="p-4 md:p-5"
        ><PurchaseField :label="$t('purchases.notes')" name="notes"
          ><BaseTextarea
            v-model="form.notes"
            maxlength="10000" /></PurchaseField
      ></BaseCard>
    </form>
    <PurchaseLookupModals :context="lookups" />
  </BasePage>
</template>
<script setup lang="ts">
import { providePurchaseLookups } from '../composables/use-purchase-lookups'
import PurchaseLookupModals from '../components/PurchaseLookupModals.vue'
import PurchaseLookupSelect from '../components/PurchaseLookupSelect.vue'
const lookups = providePurchaseLookups()

import PurchaseFormHeader from '../components/PurchaseFormHeader.vue'
import PurchaseMoney from '../components/PurchaseMoney.vue'
import PurchaseField from '../components/PurchaseField.vue'
import { usePurchaseForm } from '../composables/use-purchase-form'
const { error, setError, clearError } = usePurchaseForm()

import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { purchaseService } from '@/scripts/api/services/purchase.service'
import type {
  PurchaseOptions,
  PurchaseRecord,
} from '@/scripts/types/domain/purchase'
import SupplierPicker from '../components/SupplierPicker.vue'
import { localDate } from '../helpers'
const route = useRoute(),
  router = useRouter(),
  company = useCompanyStore()
const form = reactive({
  supplier_id: null as number | null,
  currency_id: company.selectedCompanyCurrency?.id ?? (null as number | null),
  amount: 0,
  payment_date: localDate(),
  payment_method_id: null as number | null,
  exchange_rate: 1,
  reference: '',
  notes: '',
})
const options = ref<PurchaseOptions>({
    categories: [],
    currencies: [],
    taxes: [],
    payment_methods: [],
  }),
  bills = ref<PurchaseRecord[]>([]),
  allocations = ref<Record<number, number>>({}),
  saving = ref(false),
  loading = ref(true)
const loadedSupplier = ref<PurchaseRecord | null>(null)
let billRequest = 0
onUnmounted(() => billRequest++)
const currency = computed(
  () =>
    options.value.currencies.find((c) => c.id === form.currency_id) ??
    company.selectedCompanyCurrency,
)
const available = computed(
  () =>
    form.amount -
    Object.values(allocations.value).reduce(
      (sum, value) => sum + Math.round(Number(value || 0) * 100),
      0,
    ),
)
async function loadBills() {
  const request = ++billRequest,
    supplierId = form.supplier_id,
    currencyId = form.currency_id
  allocations.value = {}
  bills.value = []
  if (!form.supplier_id) return
  try {
    let page = 1,
      last = 1
    do {
      const result = await purchaseService.list('bills', {
        supplier_id: supplierId,
        status: 'OPEN',
        outstanding: true,
        limit: 100,
        page,
      })
      if (
        request !== billRequest ||
        supplierId !== form.supplier_id ||
        currencyId !== form.currency_id
      )
        return
      bills.value.push(
        ...result.data.filter((bill) => bill.currency_id === currencyId),
      )
      last = result.meta.last_page
      page++
    } while (page <= last)
  } catch (e) {
    if (request === billRequest) setError(e)
  }
}
async function selected(supplier: PurchaseRecord) {
  if (loading.value) return
  form.currency_id = supplier.currency_id ?? form.currency_id
  await loadBills()
}
onMounted(async () => {
  try {
    options.value = await purchaseService.options()
    if (route.query.bill_id) {
      const bill = await purchaseService.get(
        'bills',
        Number(route.query.bill_id),
      )
      loadedSupplier.value = bill.supplier || null
      form.supplier_id = bill.supplier_id ?? null
      form.currency_id = bill.currency_id ?? null
      form.amount = bill.due_amount ?? 0
      await loadBills()
      allocations.value[bill.id] = form.amount / 100
    } else if (route.query.supplier_id) {
      form.supplier_id = Number(route.query.supplier_id)
      const supplier = await purchaseService.get('suppliers', form.supplier_id)
      loadedSupplier.value = supplier
      form.currency_id = supplier.currency_id ?? null
      await loadBills()
    }
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
    const record = await purchaseService.save('supplier-payments', {
      ...form,
      allocations: Object.entries(allocations.value)
        .filter(([, amount]) => Number(amount) > 0)
        .map(([id, amount]) => ({
          bill_id: Number(id),
          amount: Math.round(Number(amount) * 100),
        })),
    })
    await router.push(`/admin/supplier-payments/${record.id}/view`)
  } catch (e) {
    setError(e)
  } finally {
    saving.value = false
  }
}
</script>
