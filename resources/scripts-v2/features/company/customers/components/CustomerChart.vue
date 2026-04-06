<script setup lang="ts">
import { ref, computed, watch, reactive } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useCustomerStore } from '../store'
import { useCompanyStore } from '@v2/stores/company.store'
import LineChart from '@v2/components/charts/LineChart.vue'
import ChartPlaceholder from './CustomerChartPlaceholder.vue'
import CustomerInfo from './CustomerInfo.vue'
import type { CustomerStatsChartData } from '@v2/api/services/customer.service'

interface YearOption {
  label: string
  value: string
}

const companyStore = useCompanyStore()
const customerStore = useCustomerStore()
const { t } = useI18n()
const route = useRoute()

const isLoading = ref<boolean>(false)
const chartData = reactive<Partial<CustomerStatsChartData>>({})
const years = reactive<YearOption[]>([
  { label: t('dateRange.this_year'), value: 'This year' },
  { label: t('dateRange.previous_year'), value: 'Previous year' },
])
const selectedYear = ref<string>('This year')

const getChartExpenses = computed<number[]>(() => chartData.expenseTotals ?? [])
const getNetProfits = computed<number[]>(() => chartData.netProfits ?? [])
const getChartMonths = computed<string[]>(() => chartData.months ?? [])
const getReceiptTotals = computed<number[]>(() => chartData.receiptTotals ?? [])
const getChartInvoices = computed<number[]>(() => chartData.invoiceTotals ?? [])

watch(
  route,
  () => {
    if (route.params.id) {
      loadCustomer()
    }
    selectedYear.value = 'This year'
  },
  { immediate: true }
)

async function loadCustomer(): Promise<void> {
  isLoading.value = false
  const response = await customerStore.fetchViewCustomer({
    id: Number(route.params.id),
  })

  if (response.meta.chartData) {
    Object.assign(chartData, response.meta.chartData)
  }

  isLoading.value = true
}

async function onChangeYear(data: string): Promise<boolean> {
  const params: {
    id: number
    previous_year?: boolean
    this_year?: boolean
  } = {
    id: Number(route.params.id),
  }

  if (data === 'Previous year') {
    params.previous_year = true
  } else {
    params.this_year = true
  }

  const response = await customerStore.fetchViewCustomer(params)

  if (response.meta.chartData) {
    Object.assign(chartData, response.meta.chartData)
  }

  return true
}
</script>

<template>
  <BaseCard class="flex flex-col mt-6">
    <ChartPlaceholder v-if="!isLoading" />

    <div v-else class="grid grid-cols-12">
      <div class="col-span-12 xl:col-span-9 xxl:col-span-10">
        <div class="flex justify-between mt-1 mb-6">
          <h6 class="flex items-center">
            <BaseIcon name="ChartBarSquareIcon" class="h-5 text-primary-400" />
            {{ $t('dashboard.monthly_chart.title') }}
          </h6>

          <div class="w-40 h-10">
            <BaseMultiselect
              v-model="selectedYear"
              :options="years"
              :allow-empty="false"
              :show-labels="false"
              :placeholder="$t('dashboard.select_year')"
              :can-deselect="false"
              @select="onChangeYear"
            />
          </div>
        </div>

        <LineChart
          v-if="isLoading"
          :invoices="getChartInvoices"
          :expenses="getChartExpenses"
          :receipts="getReceiptTotals"
          :income="getNetProfits"
          :labels="getChartMonths"
          class="sm:w-full"
        />
      </div>

      <div
        class="grid col-span-12 mt-6 text-center xl:mt-0 sm:grid-cols-4 xl:text-right xl:col-span-3 xl:grid-cols-1 xxl:col-span-2"
      >
        <div class="px-6 py-2">
          <span class="text-xs leading-5 lg:text-sm">
            {{ $t('dashboard.chart_info.total_sales') }}
          </span>
          <br />
          <span
            v-if="isLoading"
            class="block mt-1 text-xl font-semibold leading-8"
          >
            <BaseFormatMoney
              :amount="chartData.salesTotal"
              :currency="companyStore.selectedCompanyCurrency"
            />
          </span>
        </div>

        <div class="px-6 py-2">
          <span class="text-xs leading-5 lg:text-sm">
            {{ $t('dashboard.chart_info.total_receipts') }}
          </span>
          <br />
          <span
            v-if="isLoading"
            class="block mt-1 text-xl font-semibold leading-8"
            style="color: #00c99c"
          >
            <BaseFormatMoney
              :amount="chartData.totalReceipts"
              :currency="companyStore.selectedCompanyCurrency"
            />
          </span>
        </div>

        <div class="px-6 py-2">
          <span class="text-xs leading-5 lg:text-sm">
            {{ $t('dashboard.chart_info.total_expense') }}
          </span>
          <br />
          <span
            v-if="isLoading"
            class="block mt-1 text-xl font-semibold leading-8"
            style="color: #fb7178"
          >
            <BaseFormatMoney
              :amount="chartData.totalExpenses"
              :currency="companyStore.selectedCompanyCurrency"
            />
          </span>
        </div>

        <div class="px-6 py-2">
          <span class="text-xs leading-5 lg:text-sm">
            {{ $t('dashboard.chart_info.net_income') }}
          </span>
          <br />
          <span
            v-if="isLoading"
            class="block mt-1 text-xl font-semibold leading-8"
            style="color: #5851d8"
          >
            <BaseFormatMoney
              :amount="chartData.netProfit"
              :currency="companyStore.selectedCompanyCurrency"
            />
          </span>
        </div>
      </div>
    </div>

    <CustomerInfo />
  </BaseCard>
</template>
