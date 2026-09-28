<template>
  <BasePage>
    <form
      id="purchase-recurringcostformview"
      class="space-y-5"
      @submit.prevent="save"
    >
      <PurchaseFormHeader
        kind="recurring-costs"
        :mode="form.mode"
        :title="$t(id ? 'purchases.edit_schedule' : 'purchases.new_schedule')"
        ><template #actions
          ><BaseButton variant="white" type="button" @click="router.back()">{{
            $t('purchases.cancel')
          }}</BaseButton
          ><BaseButton
            type="submit"
            form="purchase-recurringcostformview"
            :loading="saving"
            :disabled="saving || loading || !form.supplier_id"
            >{{ $t('purchases.save') }}</BaseButton
          ></template
        ></PurchaseFormHeader
      >
      <p v-if="error" role="alert" class="text-danger">{{ error }}</p>
      <div v-if="!loading" class="grid items-start gap-5 lg:grid-cols-2">
        <SupplierPicker
          v-model="form.supplier_id"
          :supplier="loadedSupplier"
          :disabled="generated"
          @selected="selected"
        />
        <BaseCard
          class="relative focus-within:z-10"
          container-class="p-4 md:p-5"
        >
          <div class="grid gap-5 md:grid-cols-2">
            <PurchaseField :label="$t('purchases.name')" name="name" required
              ><BaseInput v-model="form.name" maxlength="255" required
            /></PurchaseField>

            <PurchaseField :label="$t('purchases.generate')" name="mode"
              ><BaseMultiselect
                v-model="form.mode"
                :can-deselect="false"
                :disabled="generated"
                :options="[
                  { value: 'BILL', label: $t('purchases.unpaid_bill') },
                  ...(user.hasAbilities('create-expense')
                    ? [
                        {
                          value: 'EXPENSE',
                          label: $t('purchases.paid_expense'),
                        },
                      ]
                    : []),
                ]"
                searchable
            /></PurchaseField>
            <PurchaseField
              :label="$t('purchases.every')"
              name="interval"
              required
              ><BaseInput
                v-model.number="form.interval"
                type="number"
                min="1"
                max="120"
                :disabled="generated"
                required
            /></PurchaseField>
            <PurchaseField :label="$t('purchases.frequency')" name="frequency"
              ><BaseMultiselect
                v-model="form.frequency"
                :can-deselect="false"
                :disabled="generated"
                :options="[
                  ...['DAY', 'WEEK', 'MONTH', 'YEAR'].map((frequency) => ({
                    value: frequency,
                    label: $t(`purchases.frequency_${frequency}`),
                  })),
                ]"
                searchable
            /></PurchaseField>
            <PurchaseField
              :label="$t('purchases.starts_at')"
              name="starts_at"
              required
              ><BaseDatePicker
                v-model="form.starts_at"
                :disabled="generated"
                required
            /></PurchaseField>
            <PurchaseField :label="$t('purchases.ends_at')" name="ends_at"
              ><BaseDatePicker v-model="form.ends_at" :min="form.starts_at"
            /></PurchaseField>
            <PurchaseField
              :label="$t('purchases.max_occurrences')"
              name="max_occurrences"
              ><BaseInput
                v-model.number="form.max_occurrences"
                type="number"
                min="1"
                max="10000"
            /></PurchaseField>
            <PurchaseField
              v-if="form.mode === 'BILL'"
              :label="$t('purchases.due_days')"
              name="due_days"
              required
              ><BaseInput
                v-model.number="form.due_days"
                type="number"
                min="0"
                max="3650"
                required
            /></PurchaseField>
          </div>
          <BaseCheckbox
            v-if="form.mode === 'EXPENSE'"
            v-model="form.auto_record_paid"
            class="mt-1"
            required
            :label="$t('purchases.auto_paid_consent')"
          />
        </BaseCard>
      </div>
      <BaseCard
        v-if="!loading"
        class="relative focus-within:z-10"
        container-class="p-4 md:p-5"
      >
        <div class="mb-5 grid gap-5 md:grid-cols-3">
          <PurchaseField
            :label="$t('purchases.currency')"
            name="template.currency_id"
            required
            ><BaseMultiselect
              v-model="currencyId"
              :options="[
                ...options.currencies.map((currency) => ({
                  value: currency.id,
                  label: currency.code,
                })),
              ]"
              searchable
              :can-deselect="false"
          /></PurchaseField>
          <PurchaseField
            :label="$t('purchases.exchange_rate')"
            name="template.exchange_rate"
            required
            ><BaseInput
              v-model.number="exchangeRate"
              type="number"
              min="0.000001"
              step="0.000001"
              required
          /></PurchaseField>
          <BaseCheckbox
            v-if="form.mode === 'BILL'"
            v-model="bill.tax_included"
            :label="$t('purchases.tax_included')"
          />
        </div>
        <PurchaseLinesEditor
          v-if="form.mode === 'BILL'"
          v-model="bill.items"
          :options="options"
          :currency="options.currencies.find((c) => c.id === currencyId)"
          prefix="template.items"
        />
        <div v-else class="grid gap-5 md:grid-cols-2">
          <PurchaseField
            :label="$t('purchases.amount')"
            name="template.amount"
            required
            ><PurchaseMoney
              v-model="expense.amount"
              :currency="options.currencies.find((c) => c.id === currencyId)"
          /></PurchaseField>
          <PurchaseField
            :label="$t('purchases.category')"
            name="template.expense_category_id"
            required
            ><PurchaseLookupSelect
              v-model="expense.expense_category_id"
              kind="category"
              :options="options.categories"
              required
          /></PurchaseField>
          <PurchaseField
            :label="$t('purchases.payment_method')"
            name="template.payment_method_id"
            ><PurchaseLookupSelect
              v-model="expense.payment_method_id"
              kind="method"
              :options="options.payment_methods"
          /></PurchaseField>
          <div class="space-y-3">
            <BaseButton
              v-if="lookups.allowed.value.tax"
              type="button"
              variant="white"
              @click="newExpenseTax"
              >{{ $t('purchases.new_purchase_tax') }}</BaseButton
            >
            <PurchaseField
              v-for="tax in options.taxes"
              :key="tax.id"
              :label="`${tax.name} · ${$t('purchases.included_tax')}`"
              ><BaseMoney
                v-model="expenseTaxes[tax.id]"
                :currency="options.currencies.find((c) => c.id === currencyId)"
            /></PurchaseField>
          </div>
        </div>
        <div
          v-if="form.mode === 'BILL' && customFields.length"
          class="mt-5 grid gap-5 md:grid-cols-2"
        >
          <PurchaseCustomFieldInputs
            :fields="customFields"
            :scope="customValidation.scope"
            prefix="template.customFields"
          />
        </div>
        <PurchaseField
          class="mt-5"
          :label="$t('purchases.notes')"
          name="template.notes"
          ><BaseTextarea v-model="notes"
        /></PurchaseField>
      </BaseCard>
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

import { ref, reactive, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useUserStore } from '@/scripts/stores/user.store'
import { purchaseService } from '@/scripts/api/services/purchase.service'
import type {
  PurchaseLine,
  PurchaseOptions,
  PurchaseRecord,
} from '@/scripts/types/domain/purchase'
import { blankLine, localDate } from '../helpers'
import PurchaseCustomFieldInputs from '../components/PurchaseCustomFieldInputs.vue'
import {
  purchaseCustomFields,
  purchaseCustomFieldPayload,
  usePurchaseCustomFieldValidation,
  type PurchaseCustomFieldAnswer,
} from '../composables/use-purchase-custom-fields'
import type { CustomFieldItem } from '@/scripts/features/shared/custom-fields/use-custom-fields'
const customFields = ref<CustomFieldItem[]>([])
const customValidation = usePurchaseCustomFieldValidation()
import SupplierPicker from '../components/SupplierPicker.vue'
import PurchaseLinesEditor from '../components/PurchaseLinesEditor.vue'
const route = useRoute(),
  router = useRouter(),
  company = useCompanyStore(),
  user = useUserStore(),
  id = Number(route.params.id) || undefined
const form = reactive({
  name: '',
  supplier_id: null as number | null,
  mode: route.query.mode === 'EXPENSE' ? 'EXPENSE' : 'BILL',
  frequency: 'MONTH',
  interval: 1,
  starts_at: localDate(),
  ends_at: '',
  max_occurrences: null as number | null,
  due_days: 30,
  auto_record_paid: false,
})
const currencyId = ref(company.selectedCompanyCurrency?.id ?? null),
  exchangeRate = ref(1),
  notes = ref(''),
  generated = ref(false)
const bill = reactive({
    tax_included: false,
    items: [blankLine()] as PurchaseLine[],
  }),
  expense = reactive({
    amount: 0,
    expense_category_id: null as number | null,
    payment_method_id: null as number | null,
  }),
  expenseTaxes = ref<Record<number, number>>({})
const options = ref<PurchaseOptions>({
    categories: [],
    currencies: [],
    taxes: [],
    payment_methods: [],
  }),
  loading = ref(true),
  saving = ref(false)
const loadedSupplier = ref<PurchaseRecord | null>(null)
function selected(supplier: PurchaseRecord, initialize = false) {
  if (loading.value && !initialize) return
  currencyId.value = supplier.currency_id ?? currencyId.value
  expense.expense_category_id = supplier.expense_category_id ?? null
  form.due_days = supplier.payment_terms ?? 30
  bill.items.forEach((line) => {
    if (!line.expense_category_id)
      line.expense_category_id = supplier.expense_category_id ?? null
  })
}
onMounted(async () => {
  try {
    options.value = await purchaseService.options('Bill')
    customFields.value = purchaseCustomFields(options.value.custom_fields || [])
    if (!id && route.query.supplier_id) {
      form.supplier_id = Number(route.query.supplier_id)
      selected(await purchaseService.get('suppliers', form.supplier_id), true)
    }
    if (id) {
      const record = await purchaseService.get('recurring-costs', id)
      loadedSupplier.value = record.supplier || null
      Object.assign(form, record)
      await router.replace({ query: { ...route.query, mode: record.mode } })
      generated.value = (record.occurrence_count ?? 0) > 0
      const template = record.template ?? {}
      currencyId.value = Number(template.currency_id)
      exchangeRate.value = Number(template.exchange_rate)
      notes.value = String(template.notes ?? '')
      if (record.mode === 'BILL') {
        Object.assign(bill, template)
        customFields.value = purchaseCustomFields(
          options.value.custom_fields || [],
          (template.customFields || []) as PurchaseCustomFieldAnswer[],
        )
      } else {
        Object.assign(expense, template)
        for (const tax of (template.taxes ?? []) as Array<{
          tax_type_id: number
          amount: number
        }>)
          expenseTaxes.value[tax.tax_type_id] = tax.amount / 100
      }
    }
  } catch (e) {
    setError(e)
  } finally {
    loading.value = false
  }
})
watch(
  () => form.mode,
  (mode) => {
    if (route.query.mode !== mode)
      router.replace({ query: { ...route.query, mode } })
  },
)
function newExpenseTax() {
  lookups.open('tax', (record) => {
    options.value.taxes = [
      ...options.value.taxes.filter((tax) => tax.id !== record.id),
      record as unknown as PurchaseOptions['taxes'][number],
    ]
    expenseTaxes.value[record.id] = 0
  })
}
async function save() {
  clearError()
  if (form.mode === 'BILL' && !(await customValidation.validate())) return
  saving.value = true
  try {
    const template = {
      ...(form.mode === 'BILL'
        ? {
            ...bill,
            customFields: purchaseCustomFieldPayload(customFields.value),
          }
        : {
            ...expense,
            taxes: Object.entries(expenseTaxes.value)
              .filter(([, amount]) => Number(amount) > 0)
              .map(([id, amount]) => ({
                tax_type_id: Number(id),
                amount: Math.round(Number(amount) * 100),
              })),
          }),
      currency_id: currencyId.value,
      exchange_rate: exchangeRate.value,
      notes: notes.value,
    }
    const record = await purchaseService.save(
      'recurring-costs',
      {
        ...form,
        ends_at: form.ends_at || null,
        max_occurrences: form.max_occurrences || null,
        template,
      },
      id,
    )
    await router.push({
      path: `/admin/recurring-costs/${record.id}/view`,
      query: { mode: record.mode },
    })
  } catch (e) {
    setError(e)
  } finally {
    saving.value = false
  }
}
</script>
