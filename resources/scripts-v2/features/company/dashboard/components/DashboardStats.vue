<script setup lang="ts">
import DollarIcon from '@/scripts/components/icons/dashboard/DollarIcon.vue'
import CustomerIcon from '@/scripts/components/icons/dashboard/CustomerIcon.vue'
import InvoiceIcon from '@/scripts/components/icons/dashboard/InvoiceIcon.vue'
import EstimateIcon from '@/scripts/components/icons/dashboard/EstimateIcon.vue'
import DashboardStatsItem from './DashboardStatsItem.vue'

import { useDashboardStore } from '../store'
import { useCompanyStore } from '../../../../stores/company.store'
import { useUserStore } from '../../../../stores/user.store'

const ABILITIES = {
  VIEW_INVOICE: 'view-invoice',
  VIEW_CUSTOMER: 'view-customer',
  VIEW_ESTIMATE: 'view-estimate',
} as const

const dashboardStore = useDashboardStore()
const companyStore = useCompanyStore()
const userStore = useUserStore()
</script>

<template>
  <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-9 xl:gap-8">
    <!-- Amount Due -->
    <DashboardStatsItem
      v-if="userStore.hasAbilities(ABILITIES.VIEW_INVOICE)"
      :icon-component="DollarIcon"
      :loading="!dashboardStore.isDashboardDataLoaded"
      route="/admin/invoices"
      :large="true"
      :label="$t('dashboard.cards.due_amount')"
    >
      <BaseFormatMoney
        :amount="dashboardStore.stats.totalAmountDue"
        :currency="companyStore.selectedCompanyCurrency"
      />
    </DashboardStatsItem>

    <!-- Customers -->
    <DashboardStatsItem
      v-if="userStore.hasAbilities(ABILITIES.VIEW_CUSTOMER)"
      :icon-component="CustomerIcon"
      :loading="!dashboardStore.isDashboardDataLoaded"
      route="/admin/customers"
      :label="
        dashboardStore.stats.totalCustomerCount <= 1
          ? $t('dashboard.cards.customers', 1)
          : $t('dashboard.cards.customers', 2)
      "
    >
      {{ dashboardStore.stats.totalCustomerCount }}
    </DashboardStatsItem>

    <!-- Invoices -->
    <DashboardStatsItem
      v-if="userStore.hasAbilities(ABILITIES.VIEW_INVOICE)"
      :icon-component="InvoiceIcon"
      :loading="!dashboardStore.isDashboardDataLoaded"
      route="/admin/invoices"
      :label="
        dashboardStore.stats.totalInvoiceCount <= 1
          ? $t('dashboard.cards.invoices', 1)
          : $t('dashboard.cards.invoices', 2)
      "
    >
      {{ dashboardStore.stats.totalInvoiceCount }}
    </DashboardStatsItem>

    <!-- Estimates -->
    <DashboardStatsItem
      v-if="userStore.hasAbilities(ABILITIES.VIEW_ESTIMATE)"
      :icon-component="EstimateIcon"
      :loading="!dashboardStore.isDashboardDataLoaded"
      route="/admin/estimates"
      :label="
        dashboardStore.stats.totalEstimateCount <= 1
          ? $t('dashboard.cards.estimates', 1)
          : $t('dashboard.cards.estimates', 2)
      "
    >
      {{ dashboardStore.stats.totalEstimateCount }}
    </DashboardStatsItem>
  </div>
</template>
