<template>
  <div
    class="col-span-12 p-4 border lg:col-span-6 glass rounded-xl md:p-5"
  >
    <!-- Send Automatically -->
    <BaseSwitchSection
      v-model="recurringInvoiceStore.newRecurringInvoice.send_automatically"
      :title="$t('recurring_invoices.send_automatically')"
      :description="$t('recurring_invoices.send_automatically_desc')"
    />

    <BaseDivider class="my-4" />

    <!-- Schedule -->
    <BaseInputGrid>
      <BaseInputGroup
        :label="$t('recurring_invoices.starts_at')"
        :content-loading="isLoading"
        required
      >
        <BaseDatePicker
          v-model="recurringInvoiceStore.newRecurringInvoice.starts_at"
          :content-loading="isLoading"
          :calendar-button="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('recurring_invoices.next_invoice_date')"
        :content-loading="isLoading"
      >
        <BaseDatePicker
          v-model="recurringInvoiceStore.newRecurringInvoice.next_invoice_at"
          :content-loading="isLoading"
          :calendar-button="true"
          :disabled="true"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <RecurrenceFrequencyField
        v-model="recurringInvoiceStore.newRecurringInvoice.frequency"
        :starts-at="recurringInvoiceStore.newRecurringInvoice.starts_at"
        :content-loading="isLoading"
        @preview="setNextInvoiceDate"
      />

      <RecurrenceLimitFields
        v-model:limit-by="recurringInvoiceStore.newRecurringInvoice.limit_by"
        v-model:limit-count="recurringInvoiceStore.newRecurringInvoice.limit_count"
        v-model:limit-date="recurringInvoiceStore.newRecurringInvoice.limit_date"
        :content-loading="isLoading"
      />

      <RecurrenceStatusSelect
        v-model="recurringInvoiceStore.newRecurringInvoice.status"
        :include-completed="isEdit"
        :content-loading="isLoading"
      />

      <!-- Exchange Rate -->
      <ExchangeRateConverter
        :store="invoiceStore"
        store-prop="newInvoice"
        :v="{ exchange_rate: { $error: false, $errors: [], $touch: () => {} } }"
        :is-loading="isLoading"
        :is-edit="isEdit"
        :customer-currency="invoiceStore.newInvoice.currency_id"
      />
      <!-- Handed down by InvoiceBasicFields, which renders this card in
           place of the ordinary details one. -->
      <CustomFieldInput
        v-for="field in customFields"
        :key="field.id"
        :custom-field-scope="customFieldScope"
        :field="field"
      />
    </BaseInputGrid>
  </div>
</template>

<script setup lang="ts">
import { useRecurringInvoiceStore } from '@/scripts/features/company/recurring-invoices/store'
import { useInvoiceStore } from '../store'
import { ExchangeRateConverter } from '../../../shared/document-form'
import CustomFieldInput from '@/scripts/features/shared/custom-fields/CustomFieldInput.vue'
import type { CustomFieldItem } from '@/scripts/features/shared/custom-fields/use-custom-fields'
import RecurrenceFrequencyField from '@/scripts/components/recurrence/RecurrenceFrequencyField.vue'
import RecurrenceLimitFields from '@/scripts/components/recurrence/RecurrenceLimitFields.vue'
import RecurrenceStatusSelect from '@/scripts/components/recurrence/RecurrenceStatusSelect.vue'
import type { RecurrencePreview } from '@/scripts/api/services/recurrence.service'

interface Props {
  isLoading?: boolean
  isEdit?: boolean
  customFields?: CustomFieldItem[]
  customFieldScope?: string
}

withDefaults(defineProps<Props>(), {
  isLoading: false,
  isEdit: false,
  customFields: () => [],
  customFieldScope: 'newInvoice',
})

const recurringInvoiceStore = useRecurringInvoiceStore()
const invoiceStore = useInvoiceStore()

function setNextInvoiceDate(preview: RecurrencePreview | null): void {
  if (preview) {
    recurringInvoiceStore.newRecurringInvoice.next_invoice_at =
      preview.next_invoice_at
  }
}
</script>
