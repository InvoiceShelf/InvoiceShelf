<script setup>
import DashboardTable from '../dashboard/DashboardTable.vue'
import ActiveFilter from '@/scripts/components/dashboard/ActiveFilter.vue'
import DashboardStats from '../dashboard/DashboardStats.vue'
import LineChart from '@/scripts/admin/components/charts/LineChart.vue'
import ChartPlaceholder from './DashboardChartPlaceholder.vue'
import { useUserStore } from '@/scripts/admin/stores/user'
import { useDashboardStore } from '@/scripts/admin/stores/dashboard'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import abilities from '@/scripts/admin/stub/abilities'

const route = useRoute()
const userStore = useUserStore()
const dashboardStore = useDashboardStore()
const companyStore = useCompanyStore()
const router = useRouter()
const { t } = useI18n()

// Period options for the header selector
const periodOptions = ref([
  { label: 'Last 30 days', value: 'last_30_days' },
  { label: 'Last 90 days', value: 'last_90_days' },
  { label: 'This year', value: 'this_year' },
  { label: 'Last year', value: 'last_year' }
])

const selectedPeriod = ref('last_30_days')

// Year options for chart
const years = ref([
  { label: t('dateRange.this_year'), value: 'This year' },
  { label: t('dateRange.previous_year'), value: 'Previous year' }
])

const selectedYear = ref('This year')

onMounted(async () => {
  if (route.meta.ability && !userStore.hasAbilities(route.meta.ability)) {
    router.push({ name: 'account.settings' })
  } else if (route.meta.isOwner && !userStore.currentUser.is_owner) {
    router.push({ name: 'account.settings' })
  } else {
    // Initialize dashboard store with active filter state
    await dashboardStore.initialize()
    loadData()
  }
})

watch(
  selectedYear,
  (val) => {
    if (val === 'Previous year') {
      let params = { previous_year: true }
      loadData(params)
    } else {
      loadData()
    }
  }
)

watch(
  selectedPeriod,
  (val) => {
    // Handle period change if needed
    console.log('Period changed to:', val)
  }
)

async function loadData(params) {
  if (userStore.hasAbilities(abilities.DASHBOARD)) {
    await dashboardStore.loadData(params)
  }
}

/**
 * Handle active filter change
 * @param {boolean} enabled - New filter state
 */
const handleActiveFilterChange = async (enabled) => {
  await dashboardStore.setActiveFilter(enabled)
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <!-- Header -->
    <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
      <div class="px-6 py-4">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Invoice</h1>
          </div>
          <div class="flex items-center space-x-4">
            <!-- Active Filter -->
            <ActiveFilter
              :model-value="dashboardStore.isActiveFilterEnabled"
              :loading="dashboardStore.isLoading"
              @update:model-value="handleActiveFilterChange"
            />
            <!-- Date Range Selector -->
            <div class="w-40">
              <BaseMultiselect
                v-model="selectedPeriod"
                :options="periodOptions"
                :allow-empty="false"
                :show-labels="false"
                :placeholder="$t('dashboard.select_period')"
                :can-deselect="false"
                class="text-sm"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="p-6">
      <div class="space-y-6">
        <!-- Stats Cards Row - Full Width (GREEN) -->
        <DashboardStats />
           <!-- Cash Flow Chart and Financial Metrics Row -->
           <div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
          <!-- Cash Flow Chart - Takes 3/4 of the width -->
          <div class="xl:col-span-3">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
              <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                  <div class="flex items-center">
                    <BaseIcon name="ChartBarSquareIcon" class="w-5 h-5 text-gray-400 mr-2" />
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Cash Flow</h3>
                    <BaseIcon name="InformationCircleIcon" class="w-4 h-4 text-gray-400 ml-2" />
                  </div>
                  <div class="w-32">
                    <BaseMultiselect
                      v-model="selectedYear"
                      :options="years"
                      :allow-empty="false"
                      :show-labels="false"
                      :placeholder="$t('dashboard.select_year')"
                      :can-deselect="false"
                      class="text-sm"
                    />
                  </div>
                </div>
                
                <div v-if="dashboardStore.isDashboardDataLoaded" class="h-80">
                  <LineChart
                    :invoices="dashboardStore.chartData.invoiceTotals"
                    :expenses="dashboardStore.chartData.expenseTotals"
                    :receipts="dashboardStore.chartData.receiptTotals"
                    :income="dashboardStore.chartData.netIncomeTotals"
                    :labels="dashboardStore.chartData.months"
                    class="w-full h-full"
                  />
                </div>
                <ChartPlaceholder v-else />
              </div>
            </div>
          </div>
                <!-- Financial Metrics - Takes 1/4 of the width (RED) -->
                <div class="xl:col-span-1 h-full">
                  <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 h-full flex flex-col">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Financial Overview</h3>
              
              <div class="space-y-6 flex-1 flex flex-col justify-center">
                <!-- Sales -->
                <div class="flex items-center justify-between">
                  <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Sales</span>
                  <span class="text-lg font-semibold text-gray-900 dark:text-white">
                    <BaseFormatMoney
                      :amount="dashboardStore.totalSales"
                      :currency="companyStore.selectedCompanyCurrency"
                    />
                  </span>
                </div>

              <!-- Receipts -->
              <div class="flex items-center justify-between">
                  <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Receipts</span>
                  <span class="text-lg font-semibold text-green-600 dark:text-green-400">
                    <BaseFormatMoney
                      :amount="dashboardStore.totalReceipts"
                      :currency="companyStore.selectedCompanyCurrency"
                    />
                  </span>
                </div>
                  <!-- Expenses -->
                  <div class="flex items-center justify-between">
                  <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Expenses</span>
                  <span class="text-lg font-semibold text-red-600 dark:text-red-400">
                    <BaseFormatMoney
                      :amount="dashboardStore.totalExpenses"
                      :currency="companyStore.selectedCompanyCurrency"
                    />
                  </span>
                </div>
                <!-- Net Income -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-600">
                  <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Net Income</span>
                  <span class="text-lg font-semibold text-primary-600 dark:text-primary-400">
                    <BaseFormatMoney
                      :amount="dashboardStore.totalNetIncome"
                      :currency="companyStore.selectedCompanyCurrency"
                    />
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Invoices Table - Full Width (PINK) -->
        <DashboardTable />
      </div>
    </div>
  </div>
</template>
