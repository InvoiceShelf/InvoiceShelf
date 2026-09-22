<template>
  <section
    class="border bg-surface border-line-light rounded-xl shadow-card"
    aria-labelledby="dashboard-cashflow"
  >
    <template v-if="dashboardStore.isDashboardDataLoaded">
      <div class="px-5 pt-5 md:px-7 md:pt-6">
        <h2 id="dashboard-cashflow" class="font-semibold text-section text-heading">
          {{ $t('dashboard.cashflow.title') }}
        </h2>

        <!-- Period totals, doubling as the chart's legend -->
        <dl class="grid grid-cols-2 mt-4 gap-x-6 gap-y-4 lg:grid-cols-4">
          <div v-for="kpi in kpis" :key="kpi.key">
            <dt class="flex items-center gap-2 text-sm text-muted">
              <span
                v-if="kpi.mark"
                :class="kpi.mark"
                class="shrink-0"
                aria-hidden="true"
              />
              {{ kpi.label }}
            </dt>
            <dd class="mt-1 text-lg font-semibold md:text-xl text-heading">
              <BaseFormatMoney
                :amount="kpi.amount"
                :currency="companyStore.selectedCompanyCurrency"
                proportional
              />
            </dd>
          </div>
        </dl>
      </div>

      <div class="px-2 pt-5 pb-1 md:px-5">
        <CashflowChart
          :labels="dashboardStore.chartData.months"
          :sales="dashboardStore.chartData.invoiceTotals"
          :receipts="dashboardStore.chartData.receiptTotals"
          :expenses="dashboardStore.chartData.expenseTotals"
          :series-labels="seriesLabels"
          :currency="companyStore.selectedCompanyCurrency"
          :height="isPhone ? 220 : 280"
          :aria-label="$t('dashboard.cashflow.title')"
        />
      </div>

      <div class="flex justify-end px-5 pb-4 md:px-7">
        <button
          type="button"
          class="text-sm font-medium rounded-md text-muted hover:text-heading focus-visible:outline-2"
          :aria-expanded="showTable"
          aria-controls="dashboard-cashflow-table"
          @click="showTable = !showTable"
        >
          {{ showTable ? $t('dashboard.cashflow.hide_table') : $t('dashboard.cashflow.show_table') }}
        </button>
      </div>

      <div
        v-if="showTable"
        id="dashboard-cashflow-table"
        class="overflow-x-auto border-t border-line-light"
      >
        <table class="min-w-full text-sm">
          <thead class="bg-surface-secondary">
            <tr>
              <th class="px-5 py-2.5 text-xs font-medium text-left md:px-7 text-muted">
                {{ $t('dashboard.cashflow.month') }}
              </th>
              <th
                v-for="label in seriesLabels"
                :key="label"
                class="px-5 py-2.5 text-xs font-medium text-right md:px-7 text-muted"
              >
                {{ label }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line-light">
            <tr v-for="(month, index) in dashboardStore.chartData.months" :key="month + index">
              <td class="px-5 py-2.5 md:px-7 text-body">{{ month }}</td>
              <td
                v-for="series in tableSeries"
                :key="series.key"
                class="px-5 py-2.5 text-right md:px-7 text-heading"
              >
                <BaseFormatMoney
                  :amount="series.values[index] ?? 0"
                  :currency="companyStore.selectedCompanyCurrency"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <BaseContentPlaceholders v-else :rounded="true" class="p-5 md:p-7">
      <BaseContentPlaceholdersText class="w-48 h-5" :lines="1" />
      <div class="grid grid-cols-2 gap-6 mt-5 lg:grid-cols-4">
        <BaseContentPlaceholdersText v-for="n in 4" :key="n" class="h-10" :lines="1" />
      </div>
      <BaseContentPlaceholdersBox class="w-full mt-6 h-60" />
    </BaseContentPlaceholders>
  </section>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDashboardStore } from '../store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'
import CashflowChart from '@/scripts/components/charts/CashflowChart.vue'

interface Kpi {
  key: string
  label: string
  amount: number
  mark: string | null
}

const dashboardStore = useDashboardStore()
const companyStore = useCompanyStore()
const { t } = useI18n()
const { isPhone } = useBreakpoints()

const showTable = ref<boolean>(false)

const seriesLabels = computed<[string, string, string]>(() => [
  t('dashboard.chart_info.total_sales'),
  t('dashboard.chart_info.total_receipts'),
  t('dashboard.chart_info.total_expense'),
])

// Each key mirrors its mark: squares for the columns, a stroke for the line
const kpis = computed<Kpi[]>(() => [
  {
    key: 'sales',
    label: seriesLabels.value[0],
    amount: dashboardStore.totalSales,
    mark: 'w-2.5 h-2.5 rounded-[3px] bg-chart-1',
  },
  {
    key: 'receipts',
    label: seriesLabels.value[1],
    amount: dashboardStore.totalReceipts,
    mark: 'w-2.5 h-2.5 rounded-[3px] bg-chart-2',
  },
  {
    key: 'expenses',
    label: seriesLabels.value[2],
    amount: dashboardStore.totalExpenses,
    mark: 'w-3 h-0.5 rounded-full bg-chart-3',
  },
  {
    key: 'net',
    label: t('dashboard.chart_info.net_income'),
    amount: dashboardStore.totalNetIncome,
    mark: null,
  },
])

const tableSeries = computed(() => [
  { key: 'sales', values: dashboardStore.chartData.invoiceTotals },
  { key: 'receipts', values: dashboardStore.chartData.receiptTotals },
  { key: 'expenses', values: dashboardStore.chartData.expenseTotals },
])
</script>
