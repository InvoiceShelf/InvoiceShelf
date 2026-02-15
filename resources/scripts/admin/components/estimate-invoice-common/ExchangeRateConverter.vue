<template>
  <BaseInputGroup
    v-if="store.showExchangeRate && selectedCurrency"
    :content-loading="isFetching && !isEdit"
    :label="$t('settings.exchange_rate.exchange_rate')"
    :error="v.exchange_rate.$error && v.exchange_rate.$errors[0].$message"
    required
  >
    <template #labelRight>
      <div v-if="hasActiveProvider" class="flex items-center gap-2">
        <BaseIcon
          v-if="date"
          v-tooltip="{ content: $t('settings.exchange_rate.fetch_historical_rate', { date }) }"
          name="CalendarIcon"
          :class="`h-4 w-4 text-primary-500 cursor-pointer outline-none ${
            isFetching
              ? ' cursor-not-allowed pointer-events-none opacity-50'
              : ''
          }`"
          @click="getCurrentExchangeRate(customerCurrency, date)"
        />
        <BaseIcon
          v-tooltip="{ content: $t('settings.exchange_rate.fetch_latest_rate') }"
          name="ArrowPathIcon"
          :class="`h-4 w-4 text-primary-500 cursor-pointer outline-none ${
            isFetching
              ? ' animate-spin rotate-180 cursor-not-allowed pointer-events-none '
              : ''
          }`"
          @click="getCurrentExchangeRate(customerCurrency, null)"
        />
      </div>
      <div v-else-if="!hasActiveProvider && isCurrencyDiffrent" class="flex items-center gap-2">
        <router-link to="/admin/settings/exchange-rate-provider" target="_blank">
          <BaseIcon
            v-tooltip="{ content: $t('settings.exchange_rate.configure_provider_tooltip') }"
            name="ExclamationTriangleIcon"
            class="h-4 w-4 text-yellow-500 cursor-pointer outline-none"
          />
        </router-link>
      </div>
    </template>
    <BaseInput
      v-model="store[storeProp].exchange_rate"
      :content-loading="isFetching && !isEdit"
      :addon="`1 ${selectedCurrency.code} =`"
      :disabled="isFetching"
      @input="v.exchange_rate.$touch()"
    >
      <template #right>
        <span class="text-gray-500 sm:text-sm">
          {{ companyCurrency.code }}
        </span>
      </template>
    </BaseInput>
    <span class="text-gray-400 text-xs mt-2 font-light">
      {{
        $t('settings.exchange_rate.exchange_help_text', {
          currency: selectedCurrency.code,
          baseCurrency: companyCurrency.code,
        })
      }}
    </span>
  </BaseInputGroup>
</template>

<script setup>
import { watch, computed, ref, onBeforeUnmount } from 'vue'
import { useGlobalStore } from '@/scripts/admin/stores/global'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { useExchangeRateStore } from '@/scripts/admin/stores/exchange-rate'

const props = defineProps({
  v: {
    type: Object,
    default: null,
  },
  isLoading: {
    type: Boolean,
    default: false,
  },
  store: {
    type: Object,
    default: null,
  },
  storeProp: {
    type: String,
    default: '',
  },
  isEdit: {
    type: Boolean,
    default: false,
  },
  customerCurrency: {
    type: [String, Number],
    default: null,
  },
  date: {
    type: String,
    default: null,
  },
})
const globalStore = useGlobalStore()
const companyStore = useCompanyStore()
const exchangeRateStore = useExchangeRateStore()
const hasActiveProvider = ref(false)
let isFetching = ref(false)

globalStore.fetchCurrencies()

const companyCurrency = computed(() => {
  return companyStore.selectedCompanyCurrency
})

const selectedCurrency = computed(() => {
  return globalStore.currencies.find(
    (c) => c.id === props.store[props.storeProp].currency_id
  )
})

const isCurrencyDiffrent = computed(() => {
  return companyCurrency.value.id !== props.customerCurrency
})

watch(
  () => props.store[props.storeProp].customer,
  (v) => {
    setCustomerCurrency(v)
  },
  { deep: true }
)

watch(
  () => props.store[props.storeProp].currency_id,
  (v) => {
    onChangeCurrency(v)
  },
  { immediate: true }
)
watch(
  () => props.customerCurrency,
  (v) => {
    if (v && props.isEdit) {
      checkForActiveProvider(v)
    }
  },
  { immediate: true }
)

// Watch for date changes in create mode to auto-fetch exchange rate
watch(
  () => props.date,
  (newDate, oldDate) => {
    // Only auto-fetch if:
    // 1. Not in edit mode (create mode)
    // 2. Currency is already selected and different from company currency
    // 3. Date actually changed and is not null
    if (!props.isEdit && props.customerCurrency && newDate && newDate !== oldDate) {
      const currencyId = props.store[props.storeProp].currency_id
      if (currencyId && currencyId !== companyCurrency.value.id) {
        getCurrentExchangeRate(currencyId, newDate)
      }
    }
  }
)

function checkForActiveProvider() {
  if (isCurrencyDiffrent.value) {
    exchangeRateStore
      .checkForActiveProvider(props.customerCurrency)
      .then((res) => {
        if (res.data.success) {
          hasActiveProvider.value = true
        }
      })
  }
}

function setCustomerCurrency(v) {
  if (v) {
    props.store[props.storeProp].currency_id = v.currency.id
  } else {
    props.store[props.storeProp].currency_id = companyCurrency.value.id
  }
}

async function onChangeCurrency(v) {
  if (v !== companyCurrency.value.id) {
    if (!props.isEdit && v) {
      await getCurrentExchangeRate(v, props.date)
    }

    props.store.showExchangeRate = true
  } else {
    props.store.showExchangeRate = false
  }
}

function getCurrentExchangeRate(v, date = null) {
  isFetching.value = true
  exchangeRateStore
    .getCurrentExchangeRate(v, date)
    .then((res) => {
      if (res.data && !res.data.error) {
        props.store[props.storeProp].exchange_rate = res.data.exchangeRate[0]
      } else {
        props.store[props.storeProp].exchange_rate = ''
      }
      isFetching.value = false
    })
    .catch((err) => {
      isFetching.value = false
    })
}

onBeforeUnmount(() => {
  props.store.showExchangeRate = false
})
</script>
