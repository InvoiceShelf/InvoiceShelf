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

    <BaseSwitchSection
      v-model="recurringInvoiceStore.newRecurringInvoice.notify_creator"
      class="mt-4"
      :title="$t('recurring_invoices.notify_creator')"
      :description="$t('recurring_invoices.notify_creator_help')"
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
        :include-completed="isCompleted"
        :disabled="isCompleted"
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
import { computed, watch } from 'vue'
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

const props = withDefaults(defineProps<Props>(), {
  isLoading: false,
  isEdit: false,
  customFields: () => [],
  customFieldScope: 'newInvoice',
})

const recurringInvoiceStore = useRecurringInvoiceStore()

/** A completed schedule's status is shown, not chosen; raising its limit restarts it. */
const isCompleted = computed(
  () => recurringInvoiceStore.newRecurringInvoice.status === 'COMPLETED',
)
const invoiceStore = useInvoiceStore()

/**
 * The schedule as it was loaded. An edited schedule keeps its stored next
 * invoice date until its frequency or start date changes, as the server does.
 */
let loaded: { frequency: string | null; starts_at: string } | null = null

// Taken when the saved record arrives, which can be after the loading flag
// settles; a new schedule has no id and never keeps a stored date.
watch(
  () => recurringInvoiceStore.newRecurringInvoice.id,
  (id) => {
    if (id && props.isEdit) {
      const { frequency, starts_at } = recurringInvoiceStore.newRecurringInvoice
      loaded = { frequency, starts_at: String(starts_at ?? '').slice(0, 10) }
    }
  },
  { immediate: true },
)

function setNextInvoiceDate(preview: RecurrencePreview | null): void {
  const current = recurringInvoiceStore.newRecurringInvoice
  const unchanged =
    loaded !== null &&
    loaded.frequency === current.frequency &&
    loaded.starts_at === String(current.starts_at ?? '').slice(0, 10)

  if (preview && !unchanged) {
    current.next_invoice_at = preview.next_invoice_at
  }
}
</script>
