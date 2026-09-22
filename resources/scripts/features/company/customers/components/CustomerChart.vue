<script setup lang="ts">
import { ref, computed, watch, reactive } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useCustomerStore } from '../store'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { useUserStore } from '@/scripts/stores/user.store'
import { useBreakpoints } from '@/scripts/composables/use-breakpoints'
import CashflowChart from '@/scripts/components/charts/CashflowChart.vue'
import type { CashflowChartType } from '@/scripts/components/charts/CashflowChart.vue'
import CustomerInfo from './CustomerInfo.vue'
import type { CustomerStatsChartData } from '@/scripts/api/services/customer.service'

type Period = 'This year' | 'Previous year'

interface Kpi {
  key: string
  label: string
  amount: number
  icon: string
  chip: string
}

const companyStore = useCompanyStore()
const customerStore = useCustomerStore()
const userStore = useUserStore()
const { t } = useI18n()
const route = useRoute()
const { isPhone } = useBreakpoints()

const isLoaded = ref<boolean>(false)
const chartData = reactive<Partial<CustomerStatsChartData>>({})
const period = ref<Period>('This year')

const periods = computed(() => [
  { value: 'This year' as Period, label: t('dateRange.this_year') },
  { value: 'Previous year' as Period, label: t('dateRange.previous_year') },
])

// The same style the user picked for the dashboard chart
const chartType = computed<CashflowChartType>(() => (
  userStore.currentUserSettings.dashboard_chart === 'bars' ? 'bars' : 'area'
))

const seriesLabels = computed<[string, string, string]>(() => [
  t('dashboard.chart_info.total_sales'),
  t('dashboard.chart_info.total_receipts'),
  t('dashboard.chart_info.total_expense'),
])

const kpis = computed<Kpi[]>(() => [
  {
    key: 'sales',
    label: seriesLabels.value[0],
    amount: chartData.salesTotal ?? 0,
    icon: 'DocumentTextIcon',
    chip: 'bg-chart-1/12 text-chart-1',
  },
  {
    key: 'receipts',
    label: seriesLabels.value[1],
    amount: chartData.totalReceipts ?? 0,
    icon: 'BanknotesIcon',
    chip: 'bg-chart-2/12 text-chart-2',
  },
  {
    key: 'expenses',
    label: seriesLabels.value[2],
    amount: chartData.totalExpenses ?? 0,
    icon: 'CreditCardIcon',
    chip: 'bg-chart-3/12 text-chart-3',
  },
  {
    key: 'net',
    label: t('dashboard.chart_info.net_income'),
    amount: chartData.netProfit ?? 0,
    icon: 'ScaleIcon',
    chip: 'bg-primary-50 text-primary-600',
  },
])

watch(
  () => route.params.id,
  (id) => {
    period.value = 'This year'

    if (id) {
      isLoaded.value = false
      void load()
    }
  },
  { immediate: true },
)

async function load(): Promise<void> {
  const response = await customerStore.fetchViewCustomer({
    id: Number(route.params.id),
    ...(period.value === 'Previous year' ? { previous_year: true } : { this_year: true }),
  })

  if (response.meta.chartData) {
    Object.assign(chartData, response.meta.chartData)
  }

  isLoaded.value = true
}

function selectPeriod(value: Period): void {
  if (period.value === value) {
    return
  }

  period.value = value
  void load()
}
</script>

<template>
  <div class="flex flex-col gap-5">
    <section class="border glass rounded-xl" aria-labelledby="customer-cashflow">
      <template v-if="isLoaded">
        <div class="px-5 pt-5 md:px-7 md:pt-6">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="customer-cashflow" class="flex items-center gap-2.5 font-semibold text-section text-heading">
              <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-primary-50 text-primary-600" aria-hidden="true">
                <BaseIcon name="PresentationChartLineIcon" class="w-4.5 h-4.5" />
              </span>
              {{ $t('dashboard.cashflow.title') }}
            </h2>

            <div
              class="inline-flex p-0.5 border rounded-lg shrink-0 bg-surface border-line-default"
              role="radiogroup"
              :aria-label="$t('dashboard.select_year')"
            >
              <button
                v-for="option in periods"
                :key="option.value"
                type="button"
                role="radio"
                :aria-checked="period === option.value"
                :class="[
                  'px-3 h-8 md:h-7 text-sm rounded-md transition-colors',
                  period === option.value
                    ? 'bg-surface-muted text-heading font-medium'
                    : 'text-muted hover:text-heading',
                ]"
                @click="selectPeriod(option.value)"
              >
                {{ option.label }}
              </button>
            </div>
          </div>

          <!-- Period totals, doubling as the chart's legend -->
          <dl class="grid grid-cols-2 mt-5 gap-x-6 gap-y-5 lg:grid-cols-4">
            <div v-for="kpi in kpis" :key="kpi.key" class="flex items-center min-w-0 gap-3">
              <span
                :class="kpi.chip"
                class="flex items-center justify-center w-10 h-10 rounded-xl shrink-0"
                aria-hidden="true"
              >
                <BaseIcon :name="kpi.icon" class="w-5 h-5" />
              </span>
              <div class="min-w-0">
                <dt class="text-sm truncate text-muted">{{ kpi.label }}</dt>
                <dd class="text-base font-semibold md:text-lg text-heading">
                  <BaseFormatMoney
                    :amount="kpi.amount"
                    :currency="companyStore.selectedCompanyCurrency"
                    proportional
                  />
                </dd>
              </div>
            </div>
          </dl>
        </div>

        <div class="px-2 pt-5 pb-3 md:px-5 md:pb-5">
          <CashflowChart
            :type="chartType"
            :labels="chartData.months ?? []"
            :sales="chartData.invoiceTotals ?? []"
            :receipts="chartData.receiptTotals ?? []"
            :expenses="chartData.expenseTotals ?? []"
            :series-labels="seriesLabels"
            :currency="companyStore.selectedCompanyCurrency"
            :height="isPhone ? 200 : 260"
            :aria-label="$t('dashboard.cashflow.title')"
          />
        </div>
      </template>

      <BaseContentPlaceholders v-else :rounded="true" class="p-5 md:p-7">
        <BaseContentPlaceholdersText class="w-40 h-5" :lines="1" />
        <div class="grid grid-cols-2 gap-6 mt-5 lg:grid-cols-4">
          <BaseContentPlaceholdersText v-for="n in 4" :key="n" class="h-10" :lines="1" />
        </div>
        <BaseContentPlaceholdersBox class="w-full mt-6 h-52" />
      </BaseContentPlaceholders>
    </section>

    <CustomerInfo />
  </div>
</template>
