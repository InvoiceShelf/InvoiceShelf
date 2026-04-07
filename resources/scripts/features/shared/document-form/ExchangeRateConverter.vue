<template>
  <BaseInputGroup
    v-if="showExchangeRate && selectedCurrency"
    :content-loading="isFetching && !isEdit"
    :label="$t('settings.exchange_rate.exchange_rate')"
    :error="
      v.exchange_rate.$error && v.exchange_rate.$errors[0].$message
    "
    required
  >
    <template #labelRight>
      <div v-if="hasActiveProvider && isEdit">
        <BaseIcon
          v-tooltip="{ content: 'Fetch Latest Exchange rate' }"
          name="ArrowPathIcon"
          :class="`h-4 w-4 text-primary-500 cursor-pointer outline-hidden ${
            isFetching
              ? ' animate-spin rotate-180 cursor-not-allowed pointer-events-none '
              : ''
          }`"
          @click="getCurrentExchangeRate(customerCurrency)"
        />
      </div>
    </template>

    <BaseInput
      v-model="exchangeRate"
      :content-loading="isFetching && !isEdit"
      :addon="`1 ${selectedCurrency.code} =`"
      :disabled="isFetching"
      @input="v.exchange_rate.$touch()"
    >
      <template #right>
        <span class="text-muted sm:text-sm">
          {{ companyCurrency?.code ?? '' }}
        </span>
      </template>
    </BaseInput>

    <span class="text-subtle text-xs mt-2 font-light">
      {{
        $t('settings.exchange_rate.exchange_help_text', {
          currency: selectedCurrency.code,
          baseCurrency: companyCurrency?.code ?? '',
        })
      }}
    </span>
  </BaseInputGroup>
</template>

<script setup lang="ts">
import { watch, computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { exchangeRateService } from '../../../api/services/exchange-rate.service'
import { useCompanyStore } from '../../../stores/company.store'
import { useGlobalStore } from '../../../stores/global.store'
import type { Currency } from '../../../types/domain/currency'
import type { DocumentFormData } from './use-document-calculations'

interface Props {
  v: Record<string, { $error: boolean; $errors: Array<{ $message: string }>; $touch: () => void }>
  isLoading?: boolean
  store: Record<string, unknown> & {
    showExchangeRate: boolean
  }
  storeProp: string
  isEdit?: boolean
  customerCurrency?: number | string | null
}

const props = withDefaults(defineProps<Props>(), {
  isLoading: false,
  isEdit: false,
  customerCurrency: null,
})

const companyStore = useCompanyStore()
const globalStore = useGlobalStore()

const hasActiveProvider = ref<boolean>(false)
const isFetching = ref<boolean>(false)

onMounted(() => {
  globalStore.fetchCurrencies()
})

const formData = computed<DocumentFormData>(() => {
  return props.store[props.storeProp] as DocumentFormData
})

const showExchangeRate = computed<boolean>(() => {
  return props.store.showExchangeRate
})

const exchangeRate = computed<number | null | string>({
  get: () => formData.value.exchange_rate ?? null,
  set: (value) => {
    formData.value.exchange_rate = value as number
  },
})

const companyCurrency = computed<Currency | null>(() => {
  return companyStore.selectedCompanyCurrency
})

const selectedCurrency = computed<Currency | null>(() => {
  return (
    globalStore.currencies.find((c: Currency) => c.id === formData.value.currency_id) ?? null
  )
})

const isCurrencyDifferent = computed<boolean>(() => {
  return companyCurrency.value?.id !== props.customerCurrency
})

watch(
  () => (formData.value as Record<string, unknown>).customer,
  (v) => {
    setCustomerCurrency(v as Record<string, unknown> | null)
  },
  { deep: true },
)

watch(
  () => formData.value.currency_id,
  (v) => {
    onChangeCurrency(v)
  },
  { immediate: true },
)

watch(
  () => props.customerCurrency,
  (v) => {
    if (v && props.isEdit) {
      checkForActiveProvider()
    }
  },
  { immediate: true },
)

function checkForActiveProvider(): void {
  if (isCurrencyDifferent.value && props.customerCurrency) {
    exchangeRateService
      .getActiveProvider(Number(props.customerCurrency))
      .then((res) => {
        hasActiveProvider.value = res.hasActiveProvider
      })
      .catch(() => {
        hasActiveProvider.value = false
      })
  }
}

function setCustomerCurrency(v: Record<string, unknown> | null): void {
  if (v) {
    const currency = v.currency as Currency | undefined
    if (currency) {
      formData.value.currency_id = currency.id
    }
  } else if (companyCurrency.value) {
    formData.value.currency_id = companyCurrency.value.id
  }
}

async function onChangeCurrency(v: number | undefined): Promise<void> {
  if (v !== companyCurrency.value?.id) {
    if (!props.isEdit && v) {
      await getCurrentExchangeRate(v)
    }
    props.store.showExchangeRate = true
  } else {
    props.store.showExchangeRate = false
  }
}

async function getCurrentExchangeRate(v: number | string | null | undefined): Promise<void> {
  if (!v) return
  isFetching.value = true
  try {
    const res = await exchangeRateService.getRate(Number(v))
    formData.value.exchange_rate = res.exchangeRate
  } catch {
    // Silently fail
  } finally {
    isFetching.value = false
  }
}

onBeforeUnmount(() => {
  props.store.showExchangeRate = false
})
</script>
