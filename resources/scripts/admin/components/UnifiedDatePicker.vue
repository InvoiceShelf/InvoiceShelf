<template>
  <div class="relative">
    <BaseDropdown 
      class="relative date-picker-dropdown"
      width-class="w-72"
      container-class="max-w-[90vw]"
    >
      <template #activator>
        <BaseButton
          variant="primary-outline"
          size="sm"
          class="min-w-[140px] max-w-[200px]"
        >
          <template #left="slotProps">
            <BaseIcon name="CalendarDaysIcon" :class="slotProps.class" />
          </template>
          <span class="truncate">{{ dateFilterStore.displayLabel }}</span>
          <template #right="slotProps">
            <BaseIcon name="ChevronDownIcon" :class="slotProps.class" />
          </template>
        </BaseButton>
      </template>

      <!-- Dropdown Content -->
      <div class="p-4 space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between pb-2 border-b border-gray-200 dark:border-gray-600">
          <h3 class="text-sm font-medium text-gray-900 dark:text-white">
            Date Range Filter
          </h3>
          <button
            v-if="dateFilterStore.selectedDateRange !== 'last_30_days'"
            @click="resetToDefault"
            class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
          >
            Reset
          </button>
        </div>

        <!-- Date Range Options -->
        <div class="space-y-1">
          <div
            v-for="option in dateFilterStore.dateRangeOptions"
            :key="option.value"
            class="relative"
          >
            <button
              @click="selectDateRange(option.value)"
              :class="[
                'w-full text-left px-3 py-2 text-sm rounded-md transition-colors duration-200',
                dateFilterStore.selectedDateRange === option.value
                  ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300 border border-primary-200 dark:border-primary-700'
                  : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 border border-transparent'
              ]"
            >
              <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                  <div class="font-medium">{{ option.label }}</div>
                  <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                    {{ option.description }}
                  </div>
                </div>
                <BaseIcon
                  v-if="dateFilterStore.selectedDateRange === option.value"
                  name="CheckIcon"
                  class="w-4 h-4 text-primary-600 dark:text-primary-400 ml-2 flex-shrink-0"
                />
              </div>
            </button>
          </div>
        </div>

        <!-- Custom Date Range Inputs -->
        <div v-if="dateFilterStore.isCustomRange" class="pt-3 border-t border-gray-200 dark:border-gray-600">
          <div class="space-y-2">
            <div>
              <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                Start Date
              </label>
              <input
                v-model="localStartDate"
                type="date"
                class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                @change="updateCustomRange"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                End Date
              </label>
              <input
                v-model="localEndDate"
                type="date"
                :min="localStartDate"
                class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                @change="updateCustomRange"
              />
            </div>
            
            <!-- Custom Range Validation -->
            <div v-if="!dateFilterStore.hasValidCustomRange" class="text-xs text-red-600 dark:text-red-400">
              Please select a valid date range
            </div>
          </div>
        </div>

        <!-- Apply/Cancel Actions for Custom Range -->
        <div v-if="dateFilterStore.isCustomRange" class="flex items-center justify-end space-x-2 pt-3 border-t border-gray-200 dark:border-gray-600">
          <BaseButton
            variant="secondary"
            size="xs"
            @click="cancelCustomRange"
          >
            Cancel
          </BaseButton>
          <BaseButton
            variant="primary"
            size="xs"
            :disabled="!dateFilterStore.hasValidCustomRange"
            @click="applyCustomRange"
          >
            Apply
          </BaseButton>
        </div>

        <!-- Quick Info -->
        <div class="text-xs text-gray-500 dark:text-gray-400 pt-2 border-t border-gray-200 dark:border-gray-600">
          <div class="flex items-center justify-between">
            <span>Affects all dashboard data</span>
            <div class="flex items-center space-x-1">
              <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
              <span>Live</span>
            </div>
          </div>
        </div>
      </div>
    </BaseDropdown>
  </div>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue'
import { useDateFilterStore } from '@/scripts/admin/stores/dateFilter'
import { useDashboardStore } from '@/scripts/admin/stores/dashboard'

const dateFilterStore = useDateFilterStore()
const dashboardStore = useDashboardStore()

// Local state for custom date inputs
const localStartDate = ref('')
const localEndDate = ref('')
const previousDateRange = ref('')

// Initialize component
function initializeComponent() {
  dateFilterStore.loadFromLocalStorage()
  if (dateFilterStore.isCustomRange) {
    localStartDate.value = dateFilterStore.customStartDate
    localEndDate.value = dateFilterStore.customEndDate
  }
  emitDateRangeChange()
}

// Methods
function selectDateRange(value) {
  if (value === 'custom') {
    previousDateRange.value = dateFilterStore.selectedDateRange
    localStartDate.value = dateFilterStore.customStartDate
    localEndDate.value = dateFilterStore.customEndDate
  }
  
  dateFilterStore.setDateRange(value)
  
  if (value !== 'custom') {
    applyDateFilter()
  }
}

function updateCustomRange() {
  dateFilterStore.setCustomRange(localStartDate.value, localEndDate.value)
}

function applyCustomRange() {
  if (dateFilterStore.hasValidCustomRange) {
    applyDateFilter()
  }
}

function cancelCustomRange() {
  dateFilterStore.setDateRange(previousDateRange.value || 'last_30_days')
  localStartDate.value = ''
  localEndDate.value = ''
  applyDateFilter()
}

function resetToDefault() {
  dateFilterStore.reset()
  localStartDate.value = ''
  localEndDate.value = ''
  applyDateFilter()
}

async function applyDateFilter() {
  dateFilterStore.saveToLocalStorage()
  emitDateRangeChange()
  
  // Trigger dashboard data reload
  if (dashboardStore.isDashboardDataLoaded) {
    await dashboardStore.loadData(dateFilterStore.getApiParams())
  }
}

// Emit event for parent components to listen
const emit = defineEmits(['dateRangeChanged'])

function emitDateRangeChange() {
  emit('dateRangeChanged', {
    dateRange: dateFilterStore.dateRange,
    selectedRange: dateFilterStore.selectedDateRange,
    apiParams: dateFilterStore.getApiParams()
  })
}

// Watch for external changes
watch(
  () => dateFilterStore.selectedDateRange,
  (newRange) => {
    if (newRange === 'custom') {
      nextTick(() => {
        localStartDate.value = dateFilterStore.customStartDate
        localEndDate.value = dateFilterStore.customEndDate
      })
    }
  }
)

// Initialize on mount
initializeComponent()
</script>

<style scoped>
/* Force dropdown to align to the right and prevent overflow */
:deep(.date-picker-dropdown [data-headlessui-state]) {
  right: 0 !important;
  left: auto !important;
  transform: translateX(0) !important;
}

/* Ensure dropdown doesn't overflow viewport */
:deep(.date-picker-dropdown .w-72) {
  max-width: min(288px, 90vw);
  right: 0;
  left: auto;
}

/* Responsive adjustments */
@media (max-width: 640px) {
  :deep(.date-picker-dropdown .w-72) {
    max-width: 95vw;
    width: 95vw;
  }
}
</style> 