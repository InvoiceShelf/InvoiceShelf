<script setup>
import DashboardTable from '../dashboard/DashboardTable.vue'
import ActiveFilter from '@/scripts/components/dashboard/ActiveFilter.vue'
import DashboardSummaryCard from '../dashboard/DashboardSummaryCard.vue'
import OutstandingInvoicesChart from '../dashboard/OutstandingInvoicesChart.vue'
import StatusDistributionChart from '../dashboard/StatusDistributionChart.vue'
import PredictiveCashFlowChart from '../dashboard/PredictiveCashFlowChart.vue'
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

// Navigation tabs state
const activeTab = ref('all')
const tabs = ref([
  { id: 'all', label: 'All', active: true },
  { id: 'overdue', label: 'Overdue', active: false },
  { id: 'paid', label: 'Paid', active: false },
  { id: 'unpaid', label: 'Unpaid', active: false }
])

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
  activeTab,
  (newTab) => {
    // Update tab states
    tabs.value.forEach(tab => {
      tab.active = tab.id === newTab
    })
    // Load data based on selected tab
    loadDataByTab(newTab)
  }
)

async function loadData(params) {
  if (userStore.hasAbilities(abilities.DASHBOARD)) {
    await dashboardStore.loadData(params)
  }
}

async function loadDataByTab(tab) {
  let params = {}
  
  switch (tab) {
    case 'overdue':
      params.filter_by = 'overdue'
      break
    case 'paid':
      params.filter_by = 'paid'
      break
    case 'unpaid':
      params.filter_by = 'unpaid'
      break
    default:
      params = {}
  }
  
  await loadData(params)
}

/**
 * Handle active filter change
 * @param {boolean} enabled - New filter state
 */
const handleActiveFilterChange = async (enabled) => {
  await dashboardStore.setActiveFilter(enabled)
}

function handleExport() {
  // TODO: Implement export functionality
  console.log('Export clicked')
}

function setActiveTab(tabId) {
  activeTab.value = tabId
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <!-- Navigation Tabs and Export Button -->
    <div class="px-6 pt-6 pb-4">
      <div class="flex items-center justify-between">
        <!-- Navigation Tabs -->
        <div class="flex space-x-0 rounded-lg p-1">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="setActiveTab(tab.id)"
            :class="[
              'px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 min-w-[80px]',
              tab.active
                ? 'bg-white text-purple-800 dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm'
                : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white hover:bg-white/50 dark:hover:bg-gray-700/50'
            ]"
          >
            {{ tab.label }}
          </button>
        </div>

        <!-- Export Button -->
        <BaseButton
          variant="primary-outline"
          size="sm"
          @click="handleExport"
        >
          <template #left="slotProps">
            <BaseIcon name="ArrowDownTrayIcon" :class="slotProps.class" />
          </template>
          Export
        </BaseButton>
      </div>
    </div>

    <!-- Main Content -->
    <div class="p-6">
      <div class="space-y-6">
        <!-- Active Filter -->
      <!--   <ActiveFilter
          :model-value="dashboardStore.isActiveFilterEnabled"
          :loading="dashboardStore.isLoading"
          @update:model-value="handleActiveFilterChange"
        /> -->

        <!-- Summary, Outstanding Invoices, and Status Distribution Row -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
          <!-- Summary Card -->
          <div class="lg:col-span-3">
            <DashboardSummaryCard />
          </div>

          <!-- Outstanding Invoices Chart -->
          <div class="lg:col-span-5">
            <OutstandingInvoicesChart />
          </div>

          <!-- Status Distribution Chart -->
          <div class="lg:col-span-4">
            <StatusDistributionChart />
          </div>
        </div>

        <!-- Paid Invoices Chart - Full Width -->
        <PredictiveCashFlowChart />

        <!-- Recent Invoices Table - Full Width -->
        <DashboardTable />
      </div>
    </div>
  </div>
</template>
