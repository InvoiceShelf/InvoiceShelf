<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDashboardStore } from '../store'
import { useCompanyStore } from '../../../../stores/company.store'
import { useUserStore } from '../../../../stores/user.store'
import LineChart from '@v2/components/charts/LineChart.vue'
import type { DashboardParams } from '../../../../api/services/dashboard.service'

interface YearOption {
  label: string
  value: string
}

const ABILITY_DASHBOARD = 'dashboard'

const dashboardStore = useDashboardStore()
const companyStore = useCompanyStore()
const userStore = useUserStore()
const { t } = useI18n()

const years = ref<YearOption[]>([
  { label: t('dateRange.this_year'), value: 'This year' },
  { label: t('dateRange.previous_year'), value: 'Previous year' },
])

const selectedYear = ref<string>('This year')

watch(
  selectedYear,
  (val: string) => {
    if (val === 'Previous year') {
      loadData({ previous_year: 1 })
    } else {
      loadData()
    }
  },
  { immediate: true }
)

async function loadData(params?: DashboardParams): Promise<void> {
  if (userStore.hasAbilities(ABILITY_DASHBOARD)) {
    await dashboardStore.loadData(params)
  }
}
</script>

<template>
  <div>
    <div
      v-if="dashboardStore.isDashboardDataLoaded"
      class="grid grid-cols-10 mt-8 bg-surface rounded-xl shadow border border-line-light"
    >
      <!-- Chart -->
      <div
        class="
          grid grid-cols-1
          col-span-10
          px-4
          py-5
          lg:col-span-7
          xl:col-span-8
          sm:p-6
        "
      >
        <div class="flex justify-between mt-1 mb-4 flex-col md:flex-row">
          <h6 class="flex items-center sw-section-title h-10">
            <BaseIcon name="ChartBarSquareIcon" class="text-primary-400 mr-1" />
            {{ $t('dashboard.monthly_chart.title') }}
          </h6>

          <div class="w-full my-2 md:m-0 md:w-40 h-10">
            <BaseMultiselect
              v-model="selectedYear"
              :options="years"
              :allow-empty="false"
              :show-labels="false"
              :placeholder="$t('dashboard.select_year')"
              :can-deselect="false"
            />
          </div>
        </div>

        <LineChart
          :invoices="dashboardStore.chartData.invoiceTotals"
          :expenses="dashboardStore.chartData.expenseTotals"
          :receipts="dashboardStore.chartData.receiptTotals"
          :income="dashboardStore.chartData.netIncomeTotals"
          :labels="dashboardStore.chartData.months"
          class="sm:w-full"
        />
      </div>

      <!-- Chart Labels -->
      <div
        class="
          grid grid-cols-3
          col-span-10
          text-center
          border-t border-l border-line-default border-solid
          lg:border-t-0 lg:text-right lg:col-span-3
          xl:col-span-2
          lg:grid-cols-1
        "
      >
        <div class="p-6">
          <span class="text-xs leading-5 lg:text-sm">
            {{ $t('dashboard.chart_info.total_sales') }}
          </span>
          <br />
          <span class="block mt-1 text-xl font-semibold leading-8 lg:text-2xl">
            <BaseFormatMoney
              :amount="dashboardStore.totalSales"
              :currency="companyStore.selectedCompanyCurrency"
            />
          </span>
        </div>
        <div class="p-6">
          <span class="text-xs leading-5 lg:text-sm">
            {{ $t('dashboard.chart_info.total_receipts') }}
          </span>
          <br />
          <span
            class="
              block
              mt-1
              text-xl
              font-semibold
              leading-8
              lg:text-2xl
              text-green-400
            "
          >
            <BaseFormatMoney
              :amount="dashboardStore.totalReceipts"
              :currency="companyStore.selectedCompanyCurrency"
            />
          </span>
        </div>
        <div class="p-6">
          <span class="text-xs leading-5 lg:text-sm">
            {{ $t('dashboard.chart_info.total_expense') }}
          </span>
          <br />
          <span
            class="
              block
              mt-1
              text-xl
              font-semibold
              leading-8
              lg:text-2xl
              text-red-400
            "
          >
            <BaseFormatMoney
              :amount="dashboardStore.totalExpenses"
              :currency="companyStore.selectedCompanyCurrency"
            />
          </span>
        </div>
        <div
          class="
            col-span-3
            p-6
            border-t border-line-default border-solid
            lg:col-span-1
          "
        >
          <span class="text-xs leading-5 lg:text-sm">
            {{ $t('dashboard.chart_info.net_income') }}
          </span>
          <br />
          <span
            class="
              block
              mt-1
              text-xl
              font-semibold
              leading-8
              lg:text-2xl
              text-primary-500
            "
          >
            <BaseFormatMoney
              :amount="dashboardStore.totalNetIncome"
              :currency="companyStore.selectedCompanyCurrency"
            />
          </span>
        </div>
      </div>
    </div>

    <!-- Chart Placeholder -->
    <BaseContentPlaceholders
      v-else
      class="grid grid-cols-10 mt-8 bg-surface rounded shadow"
    >
      <div
        class="
          grid grid-cols-1
          col-span-10
          px-4
          py-5
          lg:col-span-7
          xl:col-span-8
          sm:p-8
        "
      >
        <div class="flex items-center justify-between mb-2 xl:mb-4">
          <BaseContentPlaceholdersText class="h-10 w-36" :lines="1" />
          <BaseContentPlaceholdersText class="h-10 w-36 !mt-0" :lines="1" />
        </div>
        <BaseContentPlaceholdersBox class="h-80 xl:h-72 sm:w-full" />
      </div>

      <div
        class="
          grid grid-cols-3
          col-span-10
          text-center
          border-t border-l border-line-default border-solid
          lg:border-t-0 lg:text-right lg:col-span-3
          xl:col-span-2
          lg:grid-cols-1
        "
      >
        <div
          class="
            flex flex-col
            items-center
            justify-center
            p-6
            lg:justify-end lg:items-end
          "
        >
          <BaseContentPlaceholdersText class="h-3 w-14 xl:h-4" :lines="1" />
          <BaseContentPlaceholdersText class="w-20 h-5 xl:h-6" :lines="1" />
        </div>
        <div
          class="
            flex flex-col
            items-center
            justify-center
            p-6
            lg:justify-end lg:items-end
          "
        >
          <BaseContentPlaceholdersText class="h-3 w-14 xl:h-4" :lines="1" />
          <BaseContentPlaceholdersText class="w-20 h-5 xl:h-6" :lines="1" />
        </div>
        <div
          class="
            flex flex-col
            items-center
            justify-center
            p-6
            lg:justify-end lg:items-end
          "
        >
          <BaseContentPlaceholdersText class="h-3 w-14 xl:h-4" :lines="1" />
          <BaseContentPlaceholdersText class="w-20 h-5 xl:h-6" :lines="1" />
        </div>
        <div
          class="
            flex flex-col
            items-center
            justify-center
            col-span-3
            p-6
            border-t border-line-default border-solid
            lg:justify-end lg:items-end lg:col-span-1
          "
        >
          <BaseContentPlaceholdersText class="h-3 w-14 xl:h-4" :lines="1" />
          <BaseContentPlaceholdersText class="w-20 h-5 xl:h-6" :lines="1" />
        </div>
      </div>
    </BaseContentPlaceholders>
  </div>
</template>
