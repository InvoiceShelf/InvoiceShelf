<script setup lang="ts">
import { computed } from 'vue'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { formatMoney } from '../../utils/format-money'
import type { CurrencyConfig } from '../../utils/format-money'

interface Props {
  amount: number | string
  currency?: CurrencyConfig | null
}

const props = withDefaults(defineProps<Props>(), {
  currency: null,
})

const companyStore = useCompanyStore()

const formattedAmount = computed<string>(() => {
  const amountNum = typeof props.amount === 'string' ? Number(props.amount) : props.amount
  const currencyConfig = props.currency ?? companyStore.selectedCompanyCurrency
  return formatMoney(amountNum, currencyConfig ?? undefined)
})
</script>

<template>
  <span style="font-family: sans-serif">{{ formattedAmount }}</span>
</template>
