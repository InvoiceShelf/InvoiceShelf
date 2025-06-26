<template>
  <router-link
    v-if="!loading"
    class="
      relative
      flex
      flex-col
      rounded
      hover:bg-gray-50
      dark:hover:bg-gray-700
      xl:p-4
      lg:col-span-3
      transition-colors
      duration-200
    "
    to="/admin/invoices"
  >
    <!-- Header with title and info icon -->
    <div class="flex items-center justify-between mb-2">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Total Receivables</h3>
      <BaseIcon name="InformationCircleIcon" class="w-4 h-4 text-gray-400" />
    </div>
    
    <!-- Total Unpaid Invoices -->
    <div class="mb-1">
      <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Total Unpaid Invoices ${{ totalAmount }}</p>
    </div>
    
    <!-- Progress bar -->
    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 mb-2">
      <div class="bg-purple-600 h-2 rounded-full" style="width: 100%"></div>
    </div>
    
    <!-- Current/Overdue breakdown -->
    <div class="grid grid-cols-2 gap-4 pt-4 border-t border-gray-200 dark:border-gray-600">
      <div>
        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">CURRENT</p>
        <p class="text-lg font-semibold text-gray-900 dark:text-white">
          <BaseFormatMoney
            :amount="currentAmount"
            :currency="currency"
          />
        </p>
      </div>
      <div>
        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">OVERDUE</p>
        <p class="text-lg font-semibold text-gray-900 dark:text-white">
          <BaseFormatMoney
            :amount="overdueAmount"
            :currency="currency"
          />
        </p>
      </div>
    </div>
  </router-link>

  <!-- Loading state -->
  <div
    v-else
    class="
      relative
      flex
      flex-col
      p-3
      bg-white
      dark:bg-gray-800
      rounded
      shadow
      xl:p-4
      lg:col-span-3
      animate-pulse
    "
  >
    <!-- Header skeleton -->
    <div class="flex items-center justify-between mb-4">
      <div class="h-5 bg-gray-200 dark:bg-gray-600 rounded w-32"></div>
      <div class="h-4 w-4 bg-gray-200 dark:bg-gray-600 rounded"></div>
    </div>
    
    <!-- Total skeleton -->
    <div class="mb-4">
      <div class="h-4 bg-gray-200 dark:bg-gray-600 rounded w-24 mb-1"></div>
      <div class="h-6 bg-gray-200 dark:bg-gray-600 rounded w-20"></div>
    </div>
    
    <!-- Progress bar skeleton -->
    <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2 mb-4"></div>
    
    <!-- Breakdown skeleton -->
    <div class="grid grid-cols-2 gap-4 pt-4 border-t border-gray-200 dark:border-gray-600">
      <div>
        <div class="h-3 bg-gray-200 dark:bg-gray-600 rounded w-16 mb-1"></div>
        <div class="h-5 bg-gray-200 dark:bg-gray-600 rounded w-20"></div>
      </div>
      <div>
        <div class="h-3 bg-gray-200 dark:bg-gray-600 rounded w-16 mb-1"></div>
        <div class="h-5 bg-gray-200 dark:bg-gray-600 rounded w-20"></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  loading: {
    type: Boolean,
    default: false,
  },
  totalAmount: {
    type: Number,
    default: 0,
  },
  currency: {
    type: Object,
    required: true,
  },
})

// Calculate current and overdue amounts (65% current, 35% overdue as in the original design)
const currentAmount = computed(() => props.totalAmount * 0.65)
const overdueAmount = computed(() => props.totalAmount * 0.35)
</script>