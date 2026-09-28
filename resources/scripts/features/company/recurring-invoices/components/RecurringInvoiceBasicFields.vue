<template>
  <div class="col-span-6 pe-0">
    <BaseCustomerSelectPopup
      :content-loading="isLoading"
      type="recurring-invoice"
    />

    <div class="flex mt-7">
      <div class="relative w-20 mt-8">
        <BaseSwitch
          v-model="recurringInvoiceStore.newRecurringInvoice.send_automatically"
          class="absolute -top-4"
        />
      </div>

      <div class="ms-2">
        <p class="p-0 mb-1 leading-snug text-start text-heading">
          {{ $t('recurring_invoices.send_automatically') }}
        </p>
        <p
          class="p-0 m-0 text-xs leading-tight text-start text-muted"
          style="max-width: 480px"
        >
          {{ $t('recurring_invoices.send_automatically_desc') }}
        </p>
      </div>
    </div>

    <div class="flex mt-5">
      <div class="relative w-20 mt-8">
        <BaseSwitch
          v-model="recurringInvoiceStore.newRecurringInvoice.notify_creator"
          class="absolute -top-4"
        />
      </div>

      <div class="ms-2">
        <p class="p-0 mb-1 leading-snug text-start text-heading">
          {{ $t('recurring_invoices.notify_creator') }}
        </p>
        <p
          class="p-0 m-0 text-xs leading-tight text-start text-muted"
          style="max-width: 480px"
        >
          {{ $t('recurring_invoices.notify_creator_help') }}
        </p>
      </div>
    </div>
  </div>

  <div
    class="grid grid-cols-1 col-span-7 gap-4 mt-8 lg:gap-6 lg:mt-0 lg:grid-cols-2 rounded-xl shadow border border-line-light bg-surface p-5"
  >
    <!-- Starts At -->
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

    <!-- Next Invoice Date -->
    <BaseInputGroup
      :label="$t('recurring_invoices.next_invoice_date')"
      :content-loading="isLoading"
      required
    >
      <BaseDatePicker
        v-model="recurringInvoiceStore.newRecurringInvoice.next_invoice_at"
        :content-loading="isLoading"
        :calendar-button="true"
        :disabled="true"
        calendar-button-icon="calendar"
      />
    </BaseInputGroup>

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

    <RecurrenceFrequencyField
      v-model="recurringInvoiceStore.newRecurringInvoice.frequency"
      :starts-at="recurringInvoiceStore.newRecurringInvoice.starts_at"
      :content-loading="isLoading"
      @preview="setNextInvoiceDate"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRecurringInvoiceStore } from '../store'
import RecurrenceFrequencyField from '@/scripts/components/recurrence/RecurrenceFrequencyField.vue'
import RecurrenceLimitFields from '@/scripts/components/recurrence/RecurrenceLimitFields.vue'
import RecurrenceStatusSelect from '@/scripts/components/recurrence/RecurrenceStatusSelect.vue'
import type { RecurrencePreview } from '@/scripts/api/services/recurrence.service'

interface Props {
  isLoading?: boolean
  isEdit?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  isLoading: false,
  isEdit: false,
})

const recurringInvoiceStore = useRecurringInvoiceStore()

/** A completed schedule's status is shown, not chosen; raising its limit restarts it. */
const isCompleted = computed(
  () => recurringInvoiceStore.newRecurringInvoice.status === 'COMPLETED',
)

/**
 * The schedule as it was loaded. An edited schedule keeps its stored next
 * invoice date until its frequency or start date changes.
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
