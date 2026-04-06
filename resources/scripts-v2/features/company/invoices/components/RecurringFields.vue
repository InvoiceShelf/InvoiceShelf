<template>
  <div
    class="col-span-12 lg:col-span-6 rounded-xl shadow border border-line-light bg-surface p-5"
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
          @change="getNextInvoiceDate()"
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
          :loading="isLoadingNextDate"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('recurring_invoices.frequency.select_frequency')"
        required
        :content-loading="isLoading"
      >
        <BaseMultiselect
          v-model="recurringInvoiceStore.newRecurringInvoice.selectedFrequency"
          :content-loading="isLoading"
          :options="recurringInvoiceStore.frequencies"
          label="label"
          object
          @change="getNextInvoiceDate"
        />
      </BaseInputGroup>

      <BaseInputGroup
        v-if="isCustomFrequency"
        :label="$t('recurring_invoices.frequency.title')"
        :content-loading="isLoading"
        required
      >
        <BaseInput
          v-model="recurringInvoiceStore.newRecurringInvoice.frequency"
          :content-loading="isLoading"
          :disabled="!isCustomFrequency"
          :loading="isLoadingNextDate"
          @update:model-value="debounceNextDate"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('recurring_invoices.limit_by')"
        :content-loading="isLoading"
        required
      >
        <BaseMultiselect
          v-model="recurringInvoiceStore.newRecurringInvoice.limit_by"
          :content-loading="isLoading"
          :options="limits"
          label="label"
          value-prop="value"
        />
      </BaseInputGroup>

      <BaseInputGroup
        v-if="hasLimitBy('DATE')"
        :label="$t('recurring_invoices.limit_date')"
        :content-loading="isLoading"
        :required="hasLimitBy('DATE')"
      >
        <BaseDatePicker
          v-model="recurringInvoiceStore.newRecurringInvoice.limit_date"
          :content-loading="isLoading"
          calendar-button-icon="calendar"
        />
      </BaseInputGroup>

      <BaseInputGroup
        v-if="hasLimitBy('COUNT')"
        :label="$t('recurring_invoices.count')"
        :content-loading="isLoading"
        :required="hasLimitBy('COUNT')"
      >
        <BaseInput
          v-model="recurringInvoiceStore.newRecurringInvoice.limit_count"
          :content-loading="isLoading"
          type="number"
        />
      </BaseInputGroup>

      <BaseInputGroup
        :label="$t('recurring_invoices.status')"
        required
        :content-loading="isLoading"
      >
        <BaseMultiselect
          v-model="recurringInvoiceStore.newRecurringInvoice.status"
          :options="statusOptions"
          :content-loading="isLoading"
          :placeholder="$t('recurring_invoices.select_a_status')"
          value-prop="value"
          label="key"
        />
      </BaseInputGroup>
    </BaseInputGrid>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDebounceFn } from '@vueuse/core'
import { useRecurringInvoiceStore } from '@v2/features/company/recurring-invoices/store'
import type { FrequencyOption } from '@v2/features/company/recurring-invoices/store'

interface Props {
  isLoading?: boolean
  isEdit?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  isLoading: false,
  isEdit: false,
})

const recurringInvoiceStore = useRecurringInvoiceStore()
const { t } = useI18n()

const isLoadingNextDate = ref<boolean>(false)

interface LimitOption {
  label: string
  value: string
}

const limits = reactive<LimitOption[]>([
  { label: t('recurring_invoices.limit.none'), value: 'NONE' },
  { label: t('recurring_invoices.limit.date'), value: 'DATE' },
  { label: t('recurring_invoices.limit.count'), value: 'COUNT' },
])

interface StatusOption {
  key: string
  value: string
}

const statusOptions = computed<StatusOption[]>(() => {
  if (props.isEdit) {
    return [
      { key: t('recurring_invoices.active'), value: 'ACTIVE' },
      { key: t('recurring_invoices.on_hold'), value: 'ON_HOLD' },
      { key: t('recurring_invoices.completed'), value: 'COMPLETED' },
    ]
  }
  return [
    { key: t('recurring_invoices.active'), value: 'ACTIVE' },
    { key: t('recurring_invoices.on_hold'), value: 'ON_HOLD' },
  ]
})

const isCustomFrequency = computed<boolean>(() => {
  return (
    recurringInvoiceStore.newRecurringInvoice.selectedFrequency != null &&
    recurringInvoiceStore.newRecurringInvoice.selectedFrequency.value ===
      'CUSTOM'
  )
})

watch(
  () => recurringInvoiceStore.newRecurringInvoice.selectedFrequency,
  (newValue: FrequencyOption | null) => {
    if (!recurringInvoiceStore.isFetchingInitialSettings) {
      if (newValue && newValue.value !== 'CUSTOM') {
        recurringInvoiceStore.newRecurringInvoice.frequency = newValue.value
      } else {
        recurringInvoiceStore.newRecurringInvoice.frequency = null
      }
    }
  },
)

onMounted(() => {
  getNextInvoiceDate()
})

function hasLimitBy(limitBy: string): boolean {
  return recurringInvoiceStore.newRecurringInvoice.limit_by === limitBy
}

const debounceNextDate = useDebounceFn(() => {
  getNextInvoiceDate()
}, 500)

async function getNextInvoiceDate(): Promise<void> {
  const val = recurringInvoiceStore.newRecurringInvoice.frequency
  if (!val) return

  isLoadingNextDate.value = true

  try {
    await recurringInvoiceStore.fetchRecurringInvoiceFrequencyDate({
      starts_at: recurringInvoiceStore.newRecurringInvoice.starts_at,
      frequency: val,
    })
  } catch {
    // Error handled in store
  } finally {
    isLoadingNextDate.value = false
  }
}
</script>
