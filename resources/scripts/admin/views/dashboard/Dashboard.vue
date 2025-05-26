<script setup>
import DashboardStats from '../dashboard/DashboardStats.vue'
import DashboardChart from '../dashboard/DashboardChart.vue'
import DashboardTable from '../dashboard/DashboardTable.vue'
import ActiveFilter from '@/scripts/components/dashboard/ActiveFilter.vue'
import { useUserStore } from '@/scripts/admin/stores/user'
import { useDashboardStore } from '@/scripts/admin/stores/dashboard'
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const userStore = useUserStore()
const dashboardStore = useDashboardStore()
const router = useRouter()

onMounted(async () => {
  if (route.meta.ability && !userStore.hasAbilities(route.meta.ability)) {
    router.push({ name: 'account.settings' })
  } else if (route.meta.isOwner && !userStore.currentUser.is_owner) {
    router.push({ name: 'account.settings' })
  } else {
    // Initialize dashboard store with active filter state
    await dashboardStore.initialize()
  }
})

/**
 * Handle active filter change
 * @param {boolean} enabled - New filter state
 */
const handleActiveFilterChange = async (enabled) => {
  await dashboardStore.setActiveFilter(enabled)
}
</script>

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
    <DashboardChart />
    <DashboardTable />
  </BasePage>
</template>
