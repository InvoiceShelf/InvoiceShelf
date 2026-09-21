<template>
  <div class="grid grid-cols-12 gap-8 mt-6 mb-8">
    <BaseCustomerSelectPopup
      :valid="v.customer_id"
      :content-loading="isLoading"
      type="invoice"
      class="col-span-12 lg:col-span-6 pr-0"
    />

    <RecurringFields
      v-if="isRecurring"
      :is-loading="isLoading"
      :is-edit="isEdit"
      :custom-fields="customFields"
      :custom-field-scope="customFieldScope"
    />

    <BaseInputGrid
      v-else
      class="col-span-12 lg:col-span-6 rounded-xl shadow border border-line-light bg-surface p-5"
    >
      <BaseInputGroup
        :label="$t('invoices.invoice_date')"
        :content-loading="isLoading"
        required
        :error="v.invoice_date.$error && v.invoice_date.$errors[0].$message"
      >
        <BaseDatePicker
          v-model="invoiceStore.newInvoice.invoice_date"
          :content-loading="isLoading"
          :calendar-button="true"
          calendar-button-icon="calendar"
          :enable-time="enableTime"
          :time24hr="time24h"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('invoices.due_date')"
        :content-loading="isLoading"
      >
        <BaseDatePicker
          v-model="invoiceStore.newInvoice.due_date"
          :content-loading="isLoading"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('invoices.invoice_number')"
        :content-loading="isLoading"
        :error="v.invoice_number.$error && v.invoice_number.$errors[0].$message"
        required
      >
        <BaseInput
          v-model="invoiceStore.newInvoice.invoice_number"
          :content-loading="isLoading"
          @input="v.invoice_number.$touch()"
        />
      </BaseInputGroup>

      <ExchangeRateConverter
        :store="invoiceStore"
        store-prop="newInvoice"
        :v="v"
        :is-loading="isLoading"
        :is-edit="isEdit"
        :customer-currency="invoiceStore.newInvoice.currency_id"
      />

      <!-- Document-level custom fields sit with the number and the dates:
           they are attributes of the document, not a separate section. -->
      <CustomFieldInput
        v-for="(field, index) in customFields"
        :key="field.id"
        :custom-field-scope="customFieldScope"
        :store="invoiceStore"
        store-prop="newInvoice"
        :index="index"
        :field="field"
      />
    </BaseInputGrid>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { ExchangeRateConverter } from '../../../shared/document-form'
import { useInvoiceStore } from '../store'
import RecurringFields from './RecurringFields.vue'
import CustomFieldInput from '@/scripts/features/shared/custom-fields/CustomFieldInput.vue'
import { useCustomFields } from '@/scripts/features/shared/custom-fields/use-custom-fields'

interface ValidationField {
  $error: boolean
  $errors: Array<{ $message: string }>
  $touch: () => void
}

interface Props {
  v: Record<string, ValidationField>
  isLoading?: boolean
  isEdit?: boolean
  isRecurring?: boolean
  companySettings?: Record<string, string>
}

const props = withDefaults(defineProps<Props>(), {
  isLoading: false,
  isEdit: false,
  isRecurring: false,
  companySettings: () => ({}),
})

const invoiceStore = useInvoiceStore()
const customFieldScope = 'newInvoice'

const customFields = useCustomFields({
  store: invoiceStore,
  storeProp: 'newInvoice',
  type: 'Invoice',
  isEdit: () => props.isEdit === true,
})


const enableTime = computed<boolean>(() => {
  return props.companySettings?.invoice_use_time === 'YES'
})

const time24h = computed<boolean>(() => {
  const format = props.companySettings?.carbon_time_format ?? ''
  return format.indexOf('H') > -1
})
</script>
