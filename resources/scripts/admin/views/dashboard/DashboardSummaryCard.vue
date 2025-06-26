<template>
  <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 pl-6 h-full flex flex-col">
    <!-- Header with Live indicator -->
    <div class="flex items-center justify-between mb-5">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Summary</h3>
      <div class="px-3 py-1 bg-gray-100 dark:bg-gray-700 rounded-full">
        <span class="text-sm text-gray-600 dark:text-gray-300 font-medium">Live</span>
      </div>
    </div>

    <!-- Metrics -->
    <div class="space-y-2">
      <!-- Total Invoices -->
      <div class="pb-2">
        <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">Total invoices</div>
        <div class="flex items-baseline space-x-3">
          <span class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ dashboardStore.stats.totalInvoiceCount }}
          </span>
          <!-- <span class="text-sm text-red-500 font-medium">-28.01%</span> -->
        </div>
      </div>

      <!-- Divider -->
      <div class="border-t border-dashed border-gray-200 dark:border-gray-600"></div>

      <!-- Sales -->
      <div class="py-2">
        <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">Sales</div>
        <div class="flex items-baseline space-x-3">
          <span class="text-lg font-semibold text-gray-900 dark:text-white">
            <BaseFormatMoney
              :amount="dashboardStore.totalSales"
              :currency="companyStore.selectedCompanyCurrency"
            />
          </span>
          <!-- <span class="text-sm text-red-500 font-medium">-28.01%</span> -->
        </div>
      </div>

      <!-- Divider -->
      <div class="border-t border-dashed border-gray-200 dark:border-gray-600"></div>

      <!-- Receipts -->
      <div class="py-2">
        <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">Receipts</div>
        <div class="flex items-baseline space-x-3">
          <span class="text-lg font-semibold text-gray-900 dark:text-white">
            <BaseFormatMoney
              :amount="dashboardStore.totalReceipts"
              :currency="companyStore.selectedCompanyCurrency"
            />
          </span>
          <!-- <span class="text-sm text-red-500 font-medium">-28.01%</span> -->
        </div>
      </div>

      <!-- Divider -->
      <div class="border-t border-dashed border-gray-200 dark:border-gray-600"></div>

      <!-- Expenses -->
      <div class="py-2">
        <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">Expenses</div>
        <div class="flex items-baseline space-x-3">
          <span class="text-lg font-semibold text-gray-900 dark:text-white">
            <BaseFormatMoney
              :amount="dashboardStore.totalExpenses"
              :currency="companyStore.selectedCompanyCurrency"
            />
          </span>
          <!-- <span class="text-sm text-red-500 font-medium">-28.01%</span> -->
        </div>
      </div>

      <!-- Divider -->
      <div class="border-t border-dashed border-gray-200 dark:border-gray-600"></div>

      <!-- Total Income (highlighted) -->
      <div class="pt-2">
        <div class="text-sm text-gray-700 dark:text-gray-300 font-medium mb-2">Total income</div>
        <div class="text-xl font-bold text-gray-900 dark:text-white">
          <BaseFormatMoney
            :amount="dashboardStore.totalNetIncome"
            :currency="companyStore.selectedCompanyCurrency"
          />
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="!dashboardStore.isDashboardDataLoaded" class="absolute inset-0 bg-white dark:bg-gray-800 rounded-lg flex items-center justify-center">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
    </div>
  </div>
</template>

<script setup>
import { useDashboardStore } from '@/scripts/admin/stores/dashboard'
import { useCompanyStore } from '@/scripts/admin/stores/company'

const dashboardStore = useDashboardStore()
const companyStore = useCompanyStore()
</script> 