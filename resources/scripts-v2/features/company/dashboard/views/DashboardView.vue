<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '../../../../stores/user.store'
import DashboardStats from '../components/DashboardStats.vue'
import DashboardChart from '../components/DashboardChart.vue'
import DashboardTable from '../components/DashboardTable.vue'
import SendInvoiceModal from '@v2/features/company/invoices/components/SendInvoiceModal.vue'
import SendEstimateModal from '@v2/features/company/estimates/components/SendEstimateModal.vue'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()

onMounted(() => {
  const meta = route.meta as { ability?: string; isOwner?: boolean }

  if (meta.ability && !userStore.hasAbilities(meta.ability)) {
    router.push({ name: 'settings.account' })
  } else if (meta.isOwner && !userStore.isOwner) {
    router.push({ name: 'settings.account' })
  }
})
</script>

<template>
  <BasePage>
    <DashboardStats />
    <DashboardChart />
    <DashboardTable />
  </BasePage>

  <SendInvoiceModal />
  <SendEstimateModal />
</template>
