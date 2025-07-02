<template>
  <BasePage>
    <!-- Active Filter -->
    <div class="mb-6">
      <ActiveFilter
        :model-value="dashboardStore.isActiveFilterEnabled"
        :loading="dashboardStore.isLoading"
        @update:model-value="handleActiveFilterChange"
      />
    </div>

    <DashboardStats />
    <DashboardTable />
  </BasePage>
</template>

<script setup>
import DashboardStats from '@/scripts/customer/views/dashboard/DashboardStats.vue'
import DashboardTable from '@/scripts/customer/views/dashboard/DashboardTable.vue'
import { useDashboardStore } from '@/scripts/customer/stores/dashboard'
import { onMounted } from 'vue'

const dashboardStore = useDashboardStore()

onMounted(async () => {
  // Initialize dashboard store with active filter state
  await dashboardStore.initialize()
})
</script>
