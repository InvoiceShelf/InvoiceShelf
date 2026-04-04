<template>
  <BaseContentPlaceholders v-if="contentLoading">
    <BaseContentPlaceholdersBox
      :rounded="true"
      class="w-full"
      style="height: 38px"
    />
  </BaseContentPlaceholders>
  <money3
    v-else
    v-model="money"
    v-bind="currencyBindings"
    :class="[inputClass, invalidClass]"
    :disabled="disabled"
  />
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Money3Component } from 'v-money3'

import { useCompanyStore } from '@v2/stores/company.store'

const money3 = Money3Component

interface Currency {
  decimal_separator: string
  thousand_separator: string
  symbol: string
  precision: number
}

interface CurrencyBindings {
  decimal: string
  thousands: string
  prefix: string
  precision: number
  masked: boolean
}

interface Props {
  contentLoading?: boolean
  modelValue: string | number
  invalid?: boolean
  inputClass?: string
  disabled?: boolean
  percent?: boolean
  currency?: Currency | null
}

const props = withDefaults(defineProps<Props>(), {
  contentLoading: false,
  invalid: false,
  inputClass:
    'font-base block w-full sm:text-sm border-line-default rounded-md text-heading',
  disabled: false,
  percent: false,
  currency: null,
})

interface Emits {
  (e: 'update:modelValue', value: string | number): void
}

const emit = defineEmits<Emits>()
const companyStore = useCompanyStore()
let hasInitialValueSet = false

const money = computed<string | number>({
  get: () => props.modelValue,
  set: (value: string | number) => {
    if (!hasInitialValueSet) {
      hasInitialValueSet = true
      return
    }

    emit('update:modelValue', value)
  },
})

const currencyBindings = computed<CurrencyBindings>(() => {
  const currency: Currency = props.currency
    ? props.currency
    : companyStore.selectedCompanyCurrency

  return {
    decimal: currency.decimal_separator,
    thousands: currency.thousand_separator,
    prefix: currency.symbol + ' ',
    precision: currency.precision,
    masked: false,
  }
})

const invalidClass = computed<string>(() => {
  if (props.invalid) {
    return 'border-red-500 ring-red-500 focus:ring-red-500 focus:border-red-500'
  }
  return 'focus:ring-primary-400 focus:border-primary-400'
})
</script>
