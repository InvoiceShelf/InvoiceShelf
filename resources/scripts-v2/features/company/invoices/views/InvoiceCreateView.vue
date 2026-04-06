<template>
  <BasePage class="relative invoice-create-page">
    <form @submit.prevent="submitForm">
      <BasePageHeader :title="pageTitle">
        <BaseBreadcrumb>
          <BaseBreadcrumbItem :title="$t('general.home')" to="/admin/dashboard" />
          <BaseBreadcrumbItem :title="$t('invoices.invoice', 2)" to="/admin/invoices" />
          <BaseBreadcrumbItem
            v-if="isEdit"
            :title="$t('invoices.edit_invoice')"
            to="#"
            active
          />
          <BaseBreadcrumbItem v-else :title="$t('invoices.new_invoice')" to="#" active />
        </BaseBreadcrumb>

        <template #actions>
          <!-- Make Recurring Toggle -->
          <div v-if="!isEdit" class="flex items-center mr-4">
            <BaseSwitch v-model="isRecurring" class="mr-2" />
            <span class="text-sm font-medium text-heading whitespace-nowrap">{{ $t('recurring_invoices.make_recurring') }}</span>
          </div>

          <router-link
            v-if="isEdit"
            :to="`/invoices/pdf/${invoiceStore.newInvoice.unique_hash}`"
            target="_blank"
          >
            <BaseButton class="mr-3" variant="primary-outline" type="button">
              <span class="flex">
                {{ $t('general.view_pdf') }}
              </span>
            </BaseButton>
          </router-link>

          <BaseButton
            :loading="isSaving"
            :disabled="isSaving"
            variant="primary"
            type="submit"
          >
            <template #left="slotProps">
              <BaseIcon
                v-if="!isSaving"
                name="ArrowDownOnSquareIcon"
                :class="slotProps.class"
              />
            </template>
            {{ isRecurring ? $t('recurring_invoices.save_invoice') : $t('invoices.save_invoice') }}
          </BaseButton>
        </template>
      </BasePageHeader>

      <!-- Select Customer & Basic Fields -->
      <InvoiceBasicFields
        :v="v$"
        :is-loading="isLoadingContent"
        :is-edit="isEdit"
        :is-recurring="isRecurring"
      />

      <BaseScrollPane>
        <!-- Invoice Items -->
        <DocumentItemsTable
          :currency="invoiceStore.newInvoice.selectedCurrency"
          :is-loading="isLoadingContent"
          :item-validation-scope="invoiceValidationScope"
          :store="invoiceStore"
          store-prop="newInvoice"
        />

        <!-- Invoice Footer Section -->
        <div
          class="block mt-10 invoice-foot lg:flex lg:justify-between lg:items-start"
        >
          <div class="relative w-full lg:w-1/2 lg:mr-4">
            <!-- Invoice Custom Notes -->
            <DocumentNotes
              :store="invoiceStore"
              store-prop="newInvoice"
              :fields="invoiceNoteFieldList"
              type="Invoice"
            />

            <!-- Invoice Template Button -->
            <TemplateSelectButton
              :store="invoiceStore"
              store-prop="newInvoice"
              :is-mark-as-default="isMarkAsDefault"
            />
            <SelectTemplateModal />
          </div>

          <DocumentTotals
            :currency="invoiceStore.newInvoice.selectedCurrency"
            :is-loading="isLoadingContent"
            :store="invoiceStore"
            store-prop="newInvoice"
            tax-popup-type="invoice"
          />
        </div>
      </BaseScrollPane>
    </form>
  </BasePage>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import cloneDeep from 'lodash/cloneDeep'
import {
  required,
  maxLength,
  helpers,
  requiredIf,
  decimal,
} from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useInvoiceStore } from '../store'
import { useRecurringInvoiceStore } from '@v2/features/company/recurring-invoices/store'
import InvoiceBasicFields from '../components/InvoiceBasicFields.vue'
import {
  DocumentItemsTable,
  DocumentTotals,
  DocumentNotes,
  TemplateSelectButton,
  SelectTemplateModal,
} from '../../../shared/document-form'

const invoiceStore = useInvoiceStore()
const recurringInvoiceStore = useRecurringInvoiceStore()
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const invoiceValidationScope = 'newInvoice'
const isSaving = ref<boolean>(false)
const isMarkAsDefault = ref<boolean>(false)
const isRecurring = ref<boolean>(false)

const invoiceNoteFieldList = ref<string[]>(['customer', 'company', 'invoice'])

const isLoadingContent = computed<boolean>(
  () => invoiceStore.isFetchingInvoice || invoiceStore.isFetchingInitialSettings,
)

const pageTitle = computed<string>(() => {
  if (isRecurringEdit.value) return t('recurring_invoices.edit_invoice')
  if (isEdit.value) return t('invoices.edit_invoice')
  return t('invoices.new_invoice')
})

const isEdit = computed<boolean>(() =>
  route.name === 'invoices.edit' || route.name === 'recurring-invoices.edit',
)

const isRecurringEdit = computed<boolean>(() =>
  route.name === 'recurring-invoices.edit',
)

const rules = computed(() => {
  if (isRecurring.value) {
    return {
      customer_id: {
        required: helpers.withMessage(t('validation.required'), required),
      },
    }
  }
  return {
    invoice_date: {
      required: helpers.withMessage(t('validation.required'), required),
    },
    reference_number: {
      maxLength: helpers.withMessage(t('validation.price_maxlength'), maxLength(255)),
    },
    customer_id: {
      required: helpers.withMessage(t('validation.required'), required),
    },
    invoice_number: {
      required: helpers.withMessage(t('validation.required'), required),
    },
    exchange_rate: {
      required: requiredIf(() => invoiceStore.showExchangeRate),
    },
  }
})

const v$ = useVuelidate(
  rules,
  computed(() => invoiceStore.newInvoice),
  { $scope: invoiceValidationScope },
)

// Initialization
invoiceStore.resetCurrentInvoice()
v$.value.$reset

// Check for recurring mode
if (route.query.recurring === '1' || isRecurringEdit.value) {
  isRecurring.value = true
}

// Initialize recurring store
recurringInvoiceStore.initFrequencies(t)

if (isRecurringEdit.value) {
  // Editing a recurring invoice — load its data into both stores
  recurringInvoiceStore.resetCurrentRecurringInvoice()
  recurringInvoiceStore.fetchRecurringInvoiceInitialSettings(
    true,
    { id: route.params.id as string, query: route.query as Record<string, string> },
  ).then(() => {
    // Sync recurring data to invoice store for the shared form fields
    const ri = recurringInvoiceStore.newRecurringInvoice
    invoiceStore.newInvoice.customer = ri.customer
    invoiceStore.newInvoice.customer_id = ri.customer_id
    invoiceStore.newInvoice.items = ri.items as typeof invoiceStore.newInvoice.items
    invoiceStore.newInvoice.taxes = ri.taxes as typeof invoiceStore.newInvoice.taxes
    invoiceStore.newInvoice.notes = ri.notes
    invoiceStore.newInvoice.discount = ri.discount
    invoiceStore.newInvoice.discount_type = ri.discount_type as typeof invoiceStore.newInvoice.discount_type
    invoiceStore.newInvoice.discount_val = ri.discount_val
    invoiceStore.newInvoice.discount_per_item = ri.discount_per_item
    invoiceStore.newInvoice.tax_per_item = ri.tax_per_item
    invoiceStore.newInvoice.tax_included = ri.tax_included
    invoiceStore.newInvoice.template_name = ri.template_name
    invoiceStore.newInvoice.currency_id = ri.currency_id as typeof invoiceStore.newInvoice.currency_id
    invoiceStore.newInvoice.exchange_rate = ri.exchange_rate as typeof invoiceStore.newInvoice.exchange_rate
    invoiceStore.newInvoice.customFields = ri.customFields as typeof invoiceStore.newInvoice.customFields
  })
} else if (!isRecurring.value) {
  // Normal invoice create/edit
  invoiceStore.fetchInvoiceInitialSettings(
    isEdit.value,
    { id: route.params.id as string, query: route.query as Record<string, string> },
  )
} else {
  // New recurring invoice — just initialize
  recurringInvoiceStore.resetCurrentRecurringInvoice()
  invoiceStore.fetchInvoiceInitialSettings(
    false,
    { query: route.query as Record<string, string> },
  )
}

// Initialize recurring store when toggled on manually
watch(isRecurring, (newVal) => {
  if (newVal && !isRecurringEdit.value) {
    recurringInvoiceStore.resetCurrentRecurringInvoice()
  }
})

watch(
  () => invoiceStore.newInvoice.customer,
  (newVal) => {
    if (newVal && (newVal as Record<string, unknown>).currency) {
      invoiceStore.newInvoice.selectedCurrency = (
        newVal as Record<string, unknown>
      ).currency as Record<string, unknown>
    }
  },
)

async function submitForm(): Promise<void> {
  v$.value.$touch()

  if (v$.value.$invalid) {
    console.log('Invoice form invalid. Errors:', JSON.stringify(
      v$.value.$errors.map((e: { $property: string; $message: string }) => `${e.$property}: ${e.$message}`)
    ))
    return
  }

  isSaving.value = true

  try {
    if (isRecurring.value) {
      const recurringData = recurringInvoiceStore.newRecurringInvoice
      const invoiceData = invoiceStore.newInvoice

      // Build clean payload with only backend-expected fields
      const data: Record<string, unknown> = {
        // Recurring-specific fields
        starts_at: recurringData.starts_at,
        send_automatically: recurringData.send_automatically ? 1 : 0,
        frequency: recurringData.frequency,
        status: recurringData.status || 'ACTIVE',
        limit_by: recurringData.limit_by || 'NONE',
        limit_count: recurringData.limit_count,
        limit_date: recurringData.limit_date,

        // Shared fields from invoice form
        customer_id: invoiceData.customer_id,
        discount: invoiceData.discount,
        discount_type: invoiceData.discount_type,
        discount_val: invoiceData.discount_val,
        discount_per_item: invoiceData.discount_per_item,
        tax_per_item: invoiceData.tax_per_item,
        tax_included: invoiceData.tax_included,
        sales_tax_type: invoiceData.sales_tax_type,
        sales_tax_address_type: invoiceData.sales_tax_address_type,
        notes: invoiceData.notes,
        template_name: invoiceData.template_name,
        items: cloneDeep(invoiceData.items),
        taxes: cloneDeep(invoiceData.taxes),
        currency_id: invoiceData.currency_id,
        exchange_rate: invoiceData.exchange_rate,
        customFields: invoiceData.customFields,

        // Calculated totals
        sub_total: invoiceStore.getSubTotal,
        total: invoiceStore.getTotal,
        tax: invoiceStore.getTotalTax,
      }

      let response
      if (isRecurringEdit.value) {
        data.id = recurringData.id
        response = await recurringInvoiceStore.updateRecurringInvoice(data)
      } else {
        response = await recurringInvoiceStore.addRecurringInvoice(data)
      }
      router.push(`/admin/recurring-invoices/${response.data.data.id}/view`)
    } else {
      const data: Record<string, unknown> = {
        ...cloneDeep(invoiceStore.newInvoice),
        sub_total: invoiceStore.getSubTotal,
        total: invoiceStore.getTotal,
        tax: invoiceStore.getTotalTax,
      }

      const items = data.items as Array<Record<string, unknown>>
      if (data.discount_per_item === 'YES') {
        items.forEach((item, index) => {
          if (item.discount_type === 'fixed') {
            items[index].discount = (item.discount as number) * 100
          }
        })
      } else {
        if (data.discount_type === 'fixed') {
          data.discount = (data.discount as number) * 100
        }
      }

      const taxes = data.taxes as Array<Record<string, unknown>>
      if (data.tax_per_item !== 'YES' && taxes.length) {
        data.tax_type_ids = taxes.map((tax) => tax.tax_type_id)
      }

      const action = isEdit.value
        ? invoiceStore.updateInvoice
        : invoiceStore.addInvoice

      const response = await action(data)
      router.push(`/admin/invoices/${response.data.data.id}/view`)
    }
  } catch (err) {
    console.error(err)
  }

  isSaving.value = false
}
</script>
