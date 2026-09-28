<template>
  <BasePage>
    <form id="purchase-billformview" class="space-y-5" @submit.prevent="save">
      <PurchaseFormHeader
        :kind="kind"
        :title="
          $t(
            credit
              ? 'purchases.new_credit'
              : id
                ? 'purchases.edit_bill'
                : 'purchases.new_bill',
          )
        "
        ><template #actions
          ><BaseButton type="button" variant="white" @click="router.back()">{{
            $t('purchases.cancel')
          }}</BaseButton
          ><BaseButton
            type="submit"
            form="purchase-billformview"
            :loading="saving"
            :disabled="saving || loading || !form.supplier_id"
            >{{
              $t(credit ? 'purchases.save_credit' : 'purchases.save')
            }}</BaseButton
          ></template
        ></PurchaseFormHeader
      >
      <p v-if="error" role="alert" class="text-danger">{{ error }}</p>
      <p v-if="locked" class="text-sm text-muted">
        {{ $t('purchases.locked_bill') }}
      </p>
      <p v-if="sourceNumber" class="text-sm text-muted">
        {{ $t('purchases.credit_source', { number: sourceNumber }) }}
      </p>
      <div v-if="!loading" class="grid items-start gap-5 lg:grid-cols-2">
        <SupplierPicker
          v-model="form.supplier_id"
          :supplier="loadedSupplier"
          :snapshot="supplierSnapshot"
          :disabled="locked || linked"
          @selected="selectSupplier"
        />
        <BaseCard
          class="relative focus-within:z-10"
          container-class="p-4 md:p-5"
        >
          <div class="grid gap-5 md:grid-cols-2">
            <PurchaseField
              :label="$t('purchases.supplier_reference')"
              name="reference"
              ><BaseInput v-model="form.reference" maxlength="255"
            /></PurchaseField>
            <PurchaseField
              :label="$t('purchases.document_date')"
              name="document_date"
              required
              ><BaseDatePicker
                v-model="form.document_date"
                required
                :disabled="locked"
            /></PurchaseField>
            <PurchaseField
              v-if="!credit"
              :label="$t('purchases.due_date')"
              name="due_date"
              required
              ><BaseDatePicker
                v-model="form.due_date"
                :min="form.document_date"
                required
            /></PurchaseField>
            <PurchaseField
              :label="$t('purchases.currency')"
              name="currency_id"
              required
              ><BaseMultiselect
                v-model="form.currency_id"
                :disabled="locked || linked"
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
              name="exchange_rate"
              required
              ><BaseInput
                v-model.number="form.exchange_rate"
                type="number"
                min="0.000001"
                step="0.000001"
                required
                :disabled="locked || linked"
            /></PurchaseField>
            <PurchaseField
              v-if="!credit && !locked"
              :label="$t('purchases.status')"
              name="status"
              ><BaseMultiselect
                v-model="form.status"
                :can-deselect="false"
                :options="[
                  ...(!id || originalStatus === 'DRAFT'
                    ? [{ value: 'DRAFT', label: $t('purchases.state_DRAFT') }]
                    : []),
                  { value: 'OPEN', label: $t('purchases.state_OPEN') },
                ]"
                searchable
            /></PurchaseField>
            <PurchaseCustomFieldInputs
              v-if="!credit"
              :fields="customFields"
              :scope="customValidation.scope"
            />
            <BaseCheckbox
              v-model="form.tax_included"
              :disabled="locked || linked"
              :label="$t('purchases.tax_included')"
            />
          </div>
        </BaseCard>
      </div>
      <BaseCard
        v-if="!loading"
        class="relative focus-within:z-10"
        container-class="p-4 md:p-5"
      >
        <h2 class="mb-4 font-medium text-heading">
          {{ $t('purchases.line_items') }}
        </h2>
        <PurchaseField
          v-if="form.source_expense_id"
          class="max-w-sm"
          :label="$t('purchases.credit_amount')"
          name="source_amount"
          required
          ><PurchaseMoney
            v-model="form.source_amount"
            :currency="
              options.currencies.find((c) => c.id === form.currency_id)
            "
        /></PurchaseField>
        <PurchaseLinesEditor
          v-else
          v-model="form.items"
          :options="options"
          :currency="options.currencies.find((c) => c.id === form.currency_id)"
          :locked="locked"
          :linked="linked"
        />
        <p class="mt-4 text-sm text-muted">
          {{ $t('purchases.totals_calculated') }}
        </p>
      </BaseCard>
      <BaseCard
        v-if="!loading"
        class="relative focus-within:z-10"
        container-class="p-4 md:p-5"
      >
        <PurchaseField :label="$t('purchases.notes')" name="notes"
          ><BaseTextarea v-model="form.notes" maxlength="10000"
        /></PurchaseField>
        <PurchaseField class="mt-4" :label="$t('purchases.attachments')"
          ><PurchaseAttachments v-model="files"
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

import PurchaseAttachments from '../components/PurchaseAttachments.vue'
import PurchaseFormHeader from '../components/PurchaseFormHeader.vue'
import PurchaseMoney from '../components/PurchaseMoney.vue'
import PurchaseField from '../components/PurchaseField.vue'
import { usePurchaseForm } from '../composables/use-purchase-form'
const { error, setError, clearError } = usePurchaseForm()

import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { purchaseService } from '@/scripts/api/services/purchase.service'
import { expenseService } from '@/scripts/api/services/expense.service'
import type {
  PurchaseKind,
  PurchaseLine,
  PurchaseRecord,
  PurchaseOptions,
  SupplierSnapshot,
} from '@/scripts/types/domain/purchase'
import { blankLine, localDate } from '../helpers'
import PurchaseCustomFieldInputs from '../components/PurchaseCustomFieldInputs.vue'
import {
  purchaseCustomFields,
  purchaseCustomFieldPayload,
  usePurchaseCustomFieldValidation,
} from '../composables/use-purchase-custom-fields'
import type { CustomFieldItem } from '@/scripts/features/shared/custom-fields/use-custom-fields'
const customFields = ref<CustomFieldItem[]>([])
const customValidation = usePurchaseCustomFieldValidation()
import PurchaseLinesEditor from '../components/PurchaseLinesEditor.vue'
import SupplierPicker from '../components/SupplierPicker.vue'
const props = defineProps<{ kind: PurchaseKind }>()
const route = useRoute(),
  router = useRouter(),
  company = useCompanyStore()
const id = Number(route.params.id) || undefined,
  credit = props.kind === 'supplier-credits'
const form = reactive({
  supplier_id: null as number | null,
  reference: '',
  document_date: localDate(),
  due_date: localDate(),
  currency_id: company.selectedCompanyCurrency?.id ?? (null as number | null),
  exchange_rate: 1,
  tax_included: false,
  notes: '',
  status: credit ? 'OPEN' : 'DRAFT',
  items: [blankLine()] as PurchaseLine[],
  source_bill_id: null as number | null,
  source_expense_id: null as number | null,
  source_amount: 0,
})
const options = ref<PurchaseOptions>({
    categories: [],
    currencies: [],
    taxes: [],
    payment_methods: [],
  }),
  locked = ref(false),
  originalStatus = ref('DRAFT'),
  sourceNumber = ref(''),
  saving = ref(false),
  loading = ref(true),
  files = ref<File[]>([])
const loadedSupplier = ref<PurchaseRecord | null>(null),
  supplierSnapshot = ref<SupplierSnapshot | null>(null)
const linked = computed(() => !!(form.source_bill_id || form.source_expense_id))
function selectSupplier(supplier: PurchaseRecord, initialize = false) {
  if ((loading.value && !initialize) || locked.value || linked.value) return
  form.currency_id = supplier.currency_id ?? form.currency_id
  form.items.forEach((line) => {
    if (!line.expense_category_id)
      line.expense_category_id = supplier.expense_category_id ?? null
  })
  const due = new Date(`${form.document_date}T12:00:00`)
  due.setDate(due.getDate() + (supplier.payment_terms ?? 30))
  form.due_date = due.toLocaleDateString('en-CA')
}
onMounted(async () => {
  try {
    options.value = await purchaseService.options(credit ? undefined : 'Bill')
    if (!credit)
      customFields.value = purchaseCustomFields(
        options.value.custom_fields || [],
      )
    if (id) {
      const record = await purchaseService.get('bills', id)
      loadedSupplier.value = record.supplier || null
      supplierSnapshot.value = record.supplier_snapshot || null
      Object.assign(form, record)
      customFields.value = purchaseCustomFields(
        options.value.custom_fields || [],
        record.fields || [],
      )
      locked.value = !!record.financial_locked_at
      originalStatus.value = record.status ?? 'DRAFT'
    }
    if (credit && route.query.bill_id) {
      const bill = await purchaseService.get(
        'bills',
        Number(route.query.bill_id),
      )
      loadedSupplier.value = bill.supplier || null
      supplierSnapshot.value = bill.supplier_snapshot || null
      sourceNumber.value = bill.number ?? ''
      Object.assign(form, {
        supplier_id: bill.supplier_id,
        currency_id: bill.currency_id,
        exchange_rate: bill.exchange_rate,
        tax_included: bill.tax_included,
        source_bill_id: bill.id,
      })
      form.items = (bill.items ?? [])
        .map((item) => ({
          ...item,
          source_bill_item_id: item.id,
          quantity:
            bill.creditable_quantities?.[String(item.id)] ?? item.quantity,
        }))
        .filter((item) => Number(item.quantity) > 0)
    }
    if (credit && route.query.expense_id) {
      const expense = (await expenseService.get(Number(route.query.expense_id)))
        .data
      Object.assign(form, {
        supplier_id: expense.supplier_id,
        currency_id: expense.currency_id,
        exchange_rate: expense.exchange_rate,
        tax_included: true,
        source_expense_id: expense.id,
        source_amount: expense.amount,
      })
      form.items = [
        {
          ...blankLine(),
          description: expense.expense_number || 'Expense',
          price: expense.amount,
          expense_category_id: expense.expense_category_id,
        },
      ]
      sourceNumber.value = expense.expense_number || String(expense.id)
    }
    if (!form.supplier_id && route.query.supplier_id) {
      form.supplier_id = Number(route.query.supplier_id)
      selectSupplier(
        await purchaseService.get('suppliers', form.supplier_id),
        true,
      )
    }
  } catch (e) {
    setError(e)
  } finally {
    loading.value = false
  }
})
async function save() {
  clearError()
  if (!credit && !(await customValidation.validate())) return
  saving.value = true
  let saved: PurchaseRecord | undefined
  try {
    saved = await purchaseService.save(
      props.kind,
      {
        ...form,
        ...(!credit
          ? { customFields: purchaseCustomFieldPayload(customFields.value) }
          : {}),
        source_amount: form.source_expense_id ? form.source_amount : undefined,
        status: credit ? 'OPEN' : form.status,
      },
      id,
    )
    for (const file of files.value)
      await purchaseService.upload(props.kind, saved.id, file)
    await router.push(`/admin/${props.kind}/${saved.id}/view`)
  } catch (e) {
    if (saved) {
      await router.push({
        path: `/admin/${props.kind}/${saved.id}/view`,
        query: { attachment_error: '1' },
      })
    } else setError(e)
  } finally {
    saving.value = false
  }
}
</script>
