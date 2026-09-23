<template>
  <section
    class="border glass rounded-xl"
    aria-labelledby="dashboard-cashflow"
  >
    <template v-if="dashboardStore.isDashboardDataLoaded">
      <div class="px-5 pt-5 md:px-7 md:pt-6">
        <div class="flex items-center justify-between gap-3">
          <h2 id="dashboard-cashflow" class="flex items-center gap-2.5 font-semibold text-section text-heading">
            <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-primary-50 text-primary-600" aria-hidden="true">
              <BaseIcon name="PresentationChartLineIcon" class="w-4.5 h-4.5" />
            </span>
            {{ $t('dashboard.cashflow.title') }}
          </h2>

          <div
            class="inline-flex p-0.5 border rounded-lg shrink-0 bg-surface border-line-default"
            role="radiogroup"
            :aria-label="$t('dashboard.cashflow.chart_style')"
          >
            <button
              v-for="option in chartTypes"
              :key="option.value"
              v-tooltip="{ content: option.label }"
              type="button"
              role="radio"
              :aria-checked="chartType === option.value"
              :aria-label="option.label"
              :class="[
                'flex items-center justify-center w-9 h-8 md:w-8 md:h-7 rounded-md transition-colors',
                chartType === option.value
                  ? 'bg-surface-muted text-heading'
                  : 'text-muted hover:text-heading',
              ]"
              @click="chartType = option.value"
            >
              <BaseIcon :name="option.icon" class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Period totals, doubling as the chart's legend: each chip wears its series colour -->
        <ul role="list" class="grid grid-cols-1 min-[360px]:grid-cols-2 m-0 p-0 list-none mt-5 gap-x-6 gap-y-5 lg:grid-cols-4">
          <li v-for="kpi in kpis" :key="kpi.key" class="flex items-center min-w-0 gap-3">
            <span
              :class="kpi.chip"
              class="flex items-center justify-center w-10 h-10 rounded-xl shrink-0"
              aria-hidden="true"
            >
              <BaseIcon :name="kpi.icon" class="w-5 h-5" />
            </span>
            <div class="min-w-0">
              <p class="text-sm truncate text-muted">{{ kpi.label }}</p>
              <p class="text-base font-semibold md:text-lg text-heading">
                <BaseFormatMoney
                  :amount="kpi.amount"
                  :currency="companyStore.selectedCompanyCurrency"
                  proportional
                />
              </p>
            </div>
          </li>
        </ul>
      </div>

      <div class="px-2 pt-5 pb-1 md:px-5">
        <CashflowChart
          :type="chartType"
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

      <CashflowTable
        :labels="dashboardStore.chartData.months"
        :sales="dashboardStore.chartData.invoiceTotals"
        :receipts="dashboardStore.chartData.receiptTotals"
        :expenses="dashboardStore.chartData.expenseTotals"
        :series-labels="seriesLabels"
        :caption="$t('dashboard.cashflow.title')"
        :granularity="dashboardStore.resolvedPeriod?.granularity"
        :currency="companyStore.selectedCompanyCurrency"
      />
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
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDashboardStore } from '../store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'
import CashflowChart from '@/scripts/components/charts/CashflowChart.vue'
import CashflowTable from '@/scripts/components/charts/CashflowTable.vue'
import type { CashflowChartType } from '@/scripts/components/charts/CashflowChart.vue'
import { useUserStore } from '@/scripts/stores/user.store'
import { userService } from '@/scripts/api/services/user.service'

interface Kpi {
  key: string
  label: string
  amount: number
  icon: string
  chip: string
}

const dashboardStore = useDashboardStore()
const companyStore = useCompanyStore()
const { t } = useI18n()
const { isPhone } = useBreakpoints()

const userStore = useUserStore()


// Saved with the user's settings, so the choice follows them to other devices
const chartType = computed<CashflowChartType>({
  get: () => (userStore.currentUserSettings.dashboard_chart === 'bars' ? 'bars' : 'area'),
  set: (value) => {
    userStore.currentUserSettings.dashboard_chart = value
    userService.updateSettings({ settings: { dashboard_chart: value } }).catch(() => {
      // A preference that fails to save still applies for this session
    })
  },
})

const chartTypes = computed(() => [
  { value: 'area' as CashflowChartType, label: t('dashboard.cashflow.area'), icon: 'PresentationChartLineIcon' },
  { value: 'bars' as CashflowChartType, label: t('dashboard.cashflow.bars'), icon: 'ChartBarIcon' },
])

const seriesLabels = computed<[string, string, string]>(() => [
  t('dashboard.chart_info.total_sales'),
  t('dashboard.chart_info.total_receipts'),
  t('dashboard.chart_info.total_expense'),
])

const kpis = computed<Kpi[]>(() => [
  {
    key: 'sales',
    label: seriesLabels.value[0],
    amount: dashboardStore.totalSales,
    icon: 'DocumentTextIcon',
    chip: 'bg-chart-1/12 text-chart-1',
  },
  {
    key: 'receipts',
    label: seriesLabels.value[1],
    amount: dashboardStore.totalReceipts,
    icon: 'BanknotesIcon',
    chip: 'bg-chart-2/12 text-chart-2',
  },
  {
    key: 'expenses',
    label: seriesLabels.value[2],
    amount: dashboardStore.totalExpenses,
    icon: 'CreditCardIcon',
    chip: 'bg-chart-3/12 text-chart-3',
  },
  {
    key: 'net',
    label: t('dashboard.chart_info.net_income'),
    amount: dashboardStore.totalNetIncome,
    icon: 'ScaleIcon',
    chip: 'bg-primary-50 text-primary-600',
  },
])
</script>
