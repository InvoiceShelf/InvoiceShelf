<template>
  <div class="col-span-5 pr-0">
    <BaseCustomerSelectPopup
      v-model="recurringInvoiceStore.newRecurringInvoice.customer"
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

      <div class="ml-2">
        <p class="p-0 mb-1 leading-snug text-left text-heading">
          {{ $t('recurring_invoices.send_automatically') }}
        </p>
        <p
          class="p-0 m-0 text-xs leading-tight text-left text-muted"
          style="max-width: 480px"
        >
          {{ $t('recurring_invoices.send_automatically_desc') }}
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
        @change="getNextInvoiceDate()"
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
        :loading="isLoadingNextDate"
        calendar-button-icon="calendar"
      />
    </BaseInputGroup>

    <!-- Limit By -->
    <BaseInputGroup
      :label="$t('recurring_invoices.limit_by')"
      :content-loading="isLoading"
      class="lg:mt-0"
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

    <!-- Limit Date -->
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

    <!-- Limit Count -->
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

    <!-- Status -->
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

    <!-- Frequency -->
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

    <!-- Custom Frequency -->
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
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useDebounceFn } from '@vueuse/core'
import { useRecurringInvoiceStore } from '../store'
import type { FrequencyOption } from '../store'

interface Props {
  isLoading?: boolean
  isEdit?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  isLoading: false,
  isEdit: false,
})

const route = useRoute()
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
  if (!route.params.id) {
    getNextInvoiceDate()
  }
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
