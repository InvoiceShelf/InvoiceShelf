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
            {{ $t('invoices.save_invoice') }}
          </BaseButton>
        </template>
      </BasePageHeader>

      <!-- Select Customer & Basic Fields -->
      <InvoiceBasicFields
        :v="v$"
        :is-loading="isLoadingContent"
        :is-edit="isEdit"
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
import {
  required,
  maxLength,
  helpers,
  requiredIf,
  decimal,
} from '@vuelidate/validators'
import useVuelidate from '@vuelidate/core'
import { useInvoiceStore } from '../store'
import InvoiceBasicFields from '../components/InvoiceBasicFields.vue'
import {
  DocumentItemsTable,
  DocumentTotals,
  DocumentNotes,
  TemplateSelectButton,
} from '../../../shared/document-form'

const invoiceStore = useInvoiceStore()
const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const invoiceValidationScope = 'newInvoice'
const isSaving = ref<boolean>(false)
const isMarkAsDefault = ref<boolean>(false)

const invoiceNoteFieldList = ref<Record<string, unknown> | null>(null)

const isLoadingContent = computed<boolean>(
  () => invoiceStore.isFetchingInvoice || invoiceStore.isFetchingInitialSettings,
)

const pageTitle = computed<string>(() =>
  isEdit.value ? t('invoices.edit_invoice') : t('invoices.new_invoice'),
)

const isEdit = computed<boolean>(() => route.name === 'invoices.edit')

const rules = {
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
    required: requiredIf(() => {
      helpers.withMessage(t('validation.required'), required)
      return invoiceStore.showExchangeRate
    }),
    decimal: helpers.withMessage(t('validation.valid_exchange_rate'), decimal),
  },
}

const v$ = useVuelidate(
  rules,
  computed(() => invoiceStore.newInvoice),
  { $scope: invoiceValidationScope },
)

// Initialization
invoiceStore.resetCurrentInvoice()
v$.value.$reset
invoiceStore.fetchInvoiceInitialSettings(
  isEdit.value,
  { id: route.params.id as string, query: route.query as Record<string, string> },
)

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
    return
  }

  isSaving.value = true

  const data: Record<string, unknown> = {
    ...structuredClone(invoiceStore.newInvoice),
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

  try {
    const action = isEdit.value
      ? invoiceStore.updateInvoice
      : invoiceStore.addInvoice

    const response = await action(data)
    router.push(`/admin/invoices/${response.data.data.id}/view`)
  } catch (err) {
    console.error(err)
  }

  isSaving.value = false
}
</script>
