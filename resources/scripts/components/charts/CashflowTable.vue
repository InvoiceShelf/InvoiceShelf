<template>
  <!-- The chart's numbers as a table, for anyone who cannot read the chart -->
  <div class="flex justify-end px-5 pb-4 md:px-7">
    <button
      type="button"
      class="inline-flex items-center px-1 -mx-1 text-sm font-medium rounded-md min-h-6 text-muted hover:text-heading focus-visible:outline-2"
      :aria-expanded="open"
      :aria-controls="tableId"
      @click="open = !open"
    >
      {{ open ? $t('dashboard.cashflow.hide_table') : $t('dashboard.cashflow.show_table') }}
    </button>
  </div>

  <div
    v-if="open"
    :id="tableId"
    class="overflow-x-auto border-t border-line-light"
  >
    <table class="min-w-full text-sm">
      <caption class="sr-only">{{ caption }}</caption>
      <thead class="bg-surface-secondary">
        <tr>
          <th scope="col" class="px-5 py-3 text-sm font-medium text-left md:px-7 text-muted">
            {{ granularity === 'day' ? $t('dashboard.cashflow.day') : $t('dashboard.cashflow.month') }}
          </th>
          <th
            v-for="label in seriesLabels"
            :key="label"
            scope="col"
            class="px-5 py-3 text-sm font-medium text-right md:px-7 text-muted"
          >
            {{ label }}
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-line-light">
        <tr v-for="(label, index) in labels" :key="label + index">
          <th scope="row" class="px-5 py-2.5 font-normal text-left md:px-7 text-body">{{ label }}</th>
          <td
            v-for="(values, series) in [sales, receipts, expenses]"
            :key="series"
            class="px-5 py-2.5 text-right md:px-7 text-heading"
          >
            <BaseFormatMoney :amount="values[index] ?? 0" :currency="currency" />
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup lang="ts">
import { ref, useId } from 'vue'
import type { CurrencyConfig } from '@/scripts/utils/format-money'

interface Props {
  labels: string[]
  sales: number[]
  receipts: number[]
  expenses: number[]
  seriesLabels: [string, string, string]
  caption: string
  granularity?: string | null
  currency?: CurrencyConfig | null
}

withDefaults(defineProps<Props>(), {
  granularity: 'month',
  currency: null,
})

const open = ref<boolean>(false)

const tableId = `cashflow-table-${useId()}`
</script>
