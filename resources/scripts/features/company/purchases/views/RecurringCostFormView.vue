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
        :title="$t(recurringLabel(form.mode, id ? 'edit' : 'new'))"
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
      <p v-if="generated" class="text-sm text-muted">
        {{ $t('purchases.recurring_locked') }}
      </p>
      <div v-if="!loading" class="grid items-start gap-5 lg:grid-cols-2">
        <div class="space-y-5">
          <SupplierPicker
            v-model="form.supplier_id"
            :supplier="loadedSupplier"
            :disabled="generated"
            @selected="selectSupplier"
          />
          <BaseCard
            class="relative focus-within:z-10"
            container-class="p-4 md:p-5"
          >
            <div class="grid gap-5 md:grid-cols-2">
              <PurchaseField
                :label="$t('purchases.name')"
                name="name"
                required
                class="md:col-span-2"
                ><BaseInput v-model="form.name" maxlength="255" required
              /></PurchaseField>
              <PurchaseField
                :label="$t('purchases.generate')"
                name="mode"
                required
                ><BaseMultiselect
                  v-model="form.mode"
                  :can-deselect="false"
                  :disabled="generated"
                  :options="modeOptions"
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
            <p
              v-if="form.mode === 'EXPENSE'"
              class="mt-4 text-sm text-muted"
            >
              {{ $t('purchases.paid_expense_help') }}
            </p>
            <div class="mt-5 space-y-4">
              <div v-if="form.mode === 'BILL'">
                <BaseSwitch
                  v-model="form.create_as_draft"
                  class="text-sm font-medium text-body"
                  :label-right="$t('purchases.create_as_draft')"
                  :aria-describedby="`${uid}-draft-help`"
                />
                <p
                  :id="`${uid}-draft-help`"
                  class="ms-15 mt-1 text-sm text-muted"
                >
                  {{ $t('purchases.create_as_draft_help') }}
                </p>
              </div>
              <div>
                <BaseSwitch
                  v-model="form.notify_creator"
                  class="text-sm font-medium text-body"
                  :label-right="$t('purchases.notify_creator')"
                  :aria-describedby="`${uid}-notify-help`"
                />
                <p
                  :id="`${uid}-notify-help`"
                  class="ms-15 mt-1 text-sm text-muted"
                >
                  {{ $t('purchases.notify_creator_help') }}
                </p>
              </div>
            </div>
          </BaseCard>
        </div>
        <BaseCard
          class="relative focus-within:z-10"
          container-class="p-4 md:p-5"
        >
          <h2 class="mb-4 font-semibold text-section text-heading">
            {{ $t('purchases.schedule') }}
          </h2>
          <div class="grid gap-5 md:grid-cols-2">
            <PurchaseField
              :label="$t('purchases.starts_at')"
              name="starts_at"
              required
              ><BaseDatePicker v-model="form.starts_at" required
            /></PurchaseField>
            <RecurrenceStatusSelect
              v-if="status !== 'COMPLETED'"
              v-model="form.status"
              :error="errors.status?.[0]"
            />
            <RecurrenceFrequencyField
              v-model="form.frequency"
              :starts-at="previewStart"
              :inclusive-start="true"
              :sub-daily="false"
              :error="errors.frequency?.[0] ? purchaseMessage(errors.frequency[0]) : null"
            />
            <RecurrenceLimitFields
              v-model:limit-by="form.limit_by"
              v-model:limit-count="form.limit_count"
              v-model:limit-date="form.limit_date"
              :errors="{
                limit_by: errors.limit_by?.[0],
                limit_count: errors.limit_count?.[0],
                limit_date: errors.limit_date?.[0],
              }"
            />
          </div>
        </BaseCard>
      </div>
      <BaseCard
        v-if="!loading"
        class="relative focus-within:z-10"
        container-class="p-4 md:p-5"
      >
        <h2 class="mb-4 font-semibold text-section text-heading">
          {{
            $t(
              form.mode === 'BILL'
                ? 'purchases.line_items'
                : 'purchases.expense_details',
            )
          }}
        </h2>
        <div class="mb-5 grid gap-5 md:grid-cols-3">
          <PurchaseField
            :label="$t('purchases.currency')"
            name="currency_id"
            required
            ><BaseMultiselect
              v-model="currencyId"
              :options="currencyOptions"
              searchable
              :can-deselect="false"
          /></PurchaseField>
          <PurchaseField
            :label="$t('purchases.exchange_rate')"
            name="exchange_rate"
            required
            ><BaseInput
              v-model.number="exchangeRate"
              type="number"
              min="0.000001"
              step="0.000001"
              required
          /></PurchaseField>
          <PurchaseField
            v-if="form.mode === 'BILL'"
            :label="$t('purchases.supplier_reference')"
            name="reference"
            ><BaseInput v-model="bill.reference" maxlength="255"
          /></PurchaseField>
        </div>
        <template v-if="form.mode === 'BILL'">
          <BaseSwitch
            v-model="bill.tax_included"
            class="mb-5 text-sm font-medium text-body"
            :label-right="$t('purchases.tax_included')"
          />
          <PurchaseLinesEditor
            v-model="bill.items"
            :options="options"
            :currency="currency"
          />
          <p class="mt-4 text-sm text-muted">
            {{ $t('purchases.totals_calculated') }}
          </p>
          <div
            v-if="customFields.length"
            class="mt-5 grid gap-5 md:grid-cols-2"
          >
            <PurchaseCustomFieldInputs
              :fields="customFields"
              :scope="customValidation.scope"
              prefix="template.customFields"
            />
          </div>
        </template>
        <div v-else class="grid gap-5 md:grid-cols-2">
          <PurchaseField :label="$t('purchases.amount')" name="amount" required
            ><PurchaseMoney v-model="expense.amount" :currency="currency"
          /></PurchaseField>
          <PurchaseField
            :label="$t('purchases.category')"
            name="expense_category_id"
            required
            ><PurchaseLookupSelect
              v-model="expense.expense_category_id"
              kind="category"
              :options="options.categories"
              required
          /></PurchaseField>
          <PurchaseField
            :label="$t('purchases.payment_method')"
            name="payment_method_id"
            ><PurchaseLookupSelect
              v-model="expense.payment_method_id"
              kind="method"
              :options="options.payment_methods"
          /></PurchaseField>
          <div class="space-y-3 md:col-span-2">
            <div class="grid gap-5 md:grid-cols-2">
              <PurchaseField
                v-for="tax in options.taxes"
                :key="tax.id"
                :label="`${tax.name} · ${$t('purchases.included_tax')}`"
                name="template.taxes"
                ><PurchaseMoney
                  v-model="expenseTaxes[tax.id]"
                  :currency="currency"
              /></PurchaseField>
            </div>
            <BaseButton
              v-if="lookups.allowed.value.tax"
              type="button"
              variant="white"
              @click="newExpenseTax"
              >{{ $t('purchases.new_purchase_tax') }}</BaseButton
            >
          </div>
          <PurchaseCustomFieldInputs
            v-if="expenseCustomFields.length"
            :fields="expenseCustomFields"
            :scope="customValidation.scope"
            prefix="template.customFields"
          />
        </div>
        <PurchaseField
          class="mt-5"
          :label="$t('purchases.notes')"
          name="notes"
          ><BaseTextarea v-model="notes" maxlength="10000"
        /></PurchaseField>
      </BaseCard>
    </form>
    <PurchaseLookupModals :context="lookups" />
  </BasePage>
</template>
<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useUserStore } from '@/scripts/stores/user.store'
import { purchaseService } from '@/scripts/api/services/purchase.service'
import { getErrorTranslationKey } from '@/scripts/utils/error-handling'
import type {
  PurchaseLine,
  PurchaseOptions,
  PurchaseRecord,
  RecurringCostMode,
} from '@/scripts/types/domain/purchase'
import type { CustomFieldItem } from '@/scripts/features/shared/custom-fields/use-custom-fields'
import RecurrenceFrequencyField from '@/scripts/components/recurrence/RecurrenceFrequencyField.vue'
import RecurrenceLimitFields from '@/scripts/components/recurrence/RecurrenceLimitFields.vue'
import RecurrenceStatusSelect from '@/scripts/components/recurrence/RecurrenceStatusSelect.vue'
import { providePurchaseLookups } from '../composables/use-purchase-lookups'
import { usePurchaseForm } from '../composables/use-purchase-form'
import {
  purchaseCustomFields,
  purchaseCustomFieldPayload,
  usePurchaseCustomFieldValidation,
  type PurchaseCustomFieldAnswer,
} from '../composables/use-purchase-custom-fields'
import { blankLine, localDate } from '../helpers'
import { recurringLabel } from '../navigation'
import PurchaseLookupModals from '../components/PurchaseLookupModals.vue'
import PurchaseLookupSelect from '../components/PurchaseLookupSelect.vue'
import PurchaseFormHeader from '../components/PurchaseFormHeader.vue'
import PurchaseMoney from '../components/PurchaseMoney.vue'
import PurchaseField from '../components/PurchaseField.vue'
import PurchaseCustomFieldInputs from '../components/PurchaseCustomFieldInputs.vue'
import SupplierPicker from '../components/SupplierPicker.vue'
import PurchaseLinesEditor from '../components/PurchaseLinesEditor.vue'

const lookups = providePurchaseLookups()
const { error, errors, setError, clearError } = usePurchaseForm()
const customFields = ref<CustomFieldItem[]>([])
/** The expense form's custom fields, for a schedule that records expenses. */
const expenseCustomFields = ref<CustomFieldItem[]>([])
const customValidation = usePurchaseCustomFieldValidation()
const uid = useId()
const { t } = useI18n()

const route = useRoute(),
  router = useRouter(),
  company = useCompanyStore(),
  user = useUserStore(),
  id = Number(route.params.id) || undefined

const form = reactive({
  name: '',
  supplier_id: null as number | null,
  mode: (route.query.mode === 'EXPENSE' ? 'EXPENSE' : 'BILL') as RecurringCostMode,
  frequency: '0 0 1 * *' as string | null,
  starts_at: localDate(),
  status: 'ACTIVE' as string | null,
  limit_by: 'NONE' as string,
  limit_count: null as number | string | null,
  limit_date: null as string | null,
  due_days: 30,
  create_as_draft: false,
  notify_creator: false,
})
const currencyId = ref<number | null>(
    company.selectedCompanyCurrency?.id ?? null,
  ),
  exchangeRate = ref(1),
  notes = ref(''),
  status = ref<string>('ACTIVE'),
  generated = ref(false)
const bill = reactive({
    tax_included: false,
    reference: '',
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

const modeOptions = computed(() => [
  ...(user.hasAbilities('create-bill') || form.mode === 'BILL'
    ? [{ value: 'BILL', label: t('purchases.unpaid_bill') }]
    : []),
  ...(user.hasAbilities('create-expense') || form.mode === 'EXPENSE'
    ? [{ value: 'EXPENSE', label: t('purchases.paid_expense') }]
    : []),
])
const currencyOptions = computed(() =>
  options.value.currencies.map((currency) => ({
    value: currency.id,
    label: currency.code,
  })),
)
const currency = computed(() =>
  options.value.currencies.find((c) => c.id === currencyId.value),
)
/**
 * The first run falls on or after the start date, or after today when the
 * start has passed; the preview is asked from the same day.
 */
const previewStart = computed(() => {
  const today = localDate()
  return form.starts_at && form.starts_at > today ? form.starts_at : today
})

function purchaseMessage(message: string): string {
  const key = getErrorTranslationKey(message)
  return key ? t(key) : message
}

function selectSupplier(supplier: PurchaseRecord, initialize = false) {
  if ((loading.value && !initialize) || generated.value) return
  currencyId.value = supplier.currency_id ?? currencyId.value
  if (!expense.expense_category_id)
    expense.expense_category_id = supplier.expense_category_id ?? null
  form.due_days = supplier.payment_terms ?? 30
  if (!form.name) form.name = supplier.name ?? ''
  bill.items.forEach((line) => {
    if (!line.expense_category_id)
      line.expense_category_id = supplier.expense_category_id ?? null
  })
}

function resetExpenseTaxes() {
  for (const tax of options.value.taxes)
    if (expenseTaxes.value[tax.id] === undefined)
      expenseTaxes.value[tax.id] = 0
}

onMounted(async () => {
  try {
    const [billOptions, expenseOptions] = await Promise.all([
      purchaseService.options('Bill'),
      purchaseService.options('Expense'),
    ])
    options.value = billOptions
    customFields.value = purchaseCustomFields(billOptions.custom_fields || [])
    expenseCustomFields.value = purchaseCustomFields(
      expenseOptions.custom_fields || [],
    )
    resetExpenseTaxes()
    if (!id && route.query.supplier_id) {
      form.supplier_id = Number(route.query.supplier_id)
      selectSupplier(
        await purchaseService.get('suppliers', form.supplier_id),
        true,
      )
    }
    if (id) {
      const record = await purchaseService.get('recurring-costs', id)
      loadedSupplier.value = record.supplier || null
      Object.assign(form, {
        name: record.name ?? '',
        supplier_id: record.supplier_id ?? null,
        mode: record.mode ?? 'BILL',
        frequency: record.frequency ?? null,
        starts_at: record.starts_at ?? localDate(),
        status: record.status ?? 'ACTIVE',
        limit_by: record.limit_by ?? 'NONE',
        limit_count: record.limit_count ?? null,
        limit_date: record.limit_date ?? null,
        due_days: record.due_days ?? 0,
        create_as_draft: !!record.create_as_draft,
        notify_creator: !!record.notify_creator,
      })
      status.value = record.status ?? 'ACTIVE'
      generated.value = (record.occurrences?.length ?? 0) > 0
      if (route.query.mode !== record.mode)
        await router.replace({ query: { ...route.query, mode: record.mode } })
      const template = record.template ?? {}
      currencyId.value = Number(template.currency_id) || currencyId.value
      exchangeRate.value = Number(template.exchange_rate) || 1
      notes.value = String(template.notes ?? '')
      if (record.mode === 'EXPENSE') {
        expense.amount = Number(template.amount ?? 0)
        expense.expense_category_id = template.expense_category_id ?? null
        expense.payment_method_id = template.payment_method_id ?? null
        for (const tax of template.taxes ?? [])
          expenseTaxes.value[tax.tax_type_id] = tax.amount
        expenseCustomFields.value = purchaseCustomFields(
          expenseOptions.custom_fields || [],
          (template.customFields || []) as PurchaseCustomFieldAnswer[],
        )
      } else {
        bill.tax_included = !!template.tax_included
        bill.reference = template.reference ?? ''
        bill.items = template.items?.length
          ? template.items.map((line) => ({
              ...blankLine(),
              ...line,
              tax_type_ids: [...(line.tax_type_ids ?? [])],
            }))
          : [blankLine()]
        customFields.value = purchaseCustomFields(
          options.value.custom_fields || [],
          (template.customFields || []) as PurchaseCustomFieldAnswer[],
        )
      }
    }
  } catch (e) {
    setError(e)
  } finally {
    loading.value = false
  }
})

// The URL names the mode, so the breadcrumb and menu follow a change.
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

function template() {
  const shared = {
    currency_id: currencyId.value,
    exchange_rate: exchangeRate.value,
    notes: notes.value || null,
  }
  if (form.mode === 'EXPENSE')
    return {
      ...shared,
      amount: expense.amount,
      expense_category_id: expense.expense_category_id,
      payment_method_id: expense.payment_method_id,
      taxes: Object.entries(expenseTaxes.value)
        .filter(
          ([taxId, amount]) =>
            Number(amount) > 0 &&
            options.value.taxes.some((tax) => tax.id === Number(taxId)),
        )
        .map(([taxId, amount]) => ({
          tax_type_id: Number(taxId),
          amount: Number(amount),
        })),
      customFields: purchaseCustomFieldPayload(expenseCustomFields.value),
    }
  return {
    ...shared,
    tax_included: bill.tax_included,
    reference: bill.reference || null,
    items: bill.items.map((line) => ({
      description: line.description,
      expense_category_id: line.expense_category_id,
      quantity: line.quantity,
      price: line.price,
      discount: line.discount || 0,
      tax_type_ids: line.tax_type_ids,
    })),
    customFields: purchaseCustomFieldPayload(customFields.value),
  }
}

async function save() {
  if (saving.value || loading.value) return
  clearError()
  if (!(await customValidation.validate())) return
  saving.value = true
  try {
    const record = await purchaseService.save(
      'recurring-costs',
      {
        ...form,
        status: status.value === 'COMPLETED' ? undefined : form.status,
        limit_count: form.limit_by === 'COUNT' ? Number(form.limit_count) : null,
        limit_date: form.limit_by === 'DATE' ? form.limit_date : null,
        due_days: form.mode === 'BILL' ? form.due_days : null,
        create_as_draft: form.mode === 'BILL' && form.create_as_draft,
        template: template(),
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
