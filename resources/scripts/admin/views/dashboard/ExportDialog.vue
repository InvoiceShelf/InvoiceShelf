<template>
  <!-- Dialog Overlay -->
  <div 
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
    @click="closeDialog"
  >
    <!-- Dialog Content -->
    <div 
      class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-auto"
      @click.stop
    >
      <!-- Dialog Header -->
      <div class="p-6 border-b border-gray-200">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-xl font-semibold text-gray-900">Export Options</h2>
            <p class="text-sm text-gray-600 mt-1">Choose one, several, or all sections to export from your dashboard</p>
          </div>
          <button 
            @click="$emit('close')"
            class="text-gray-400 hover:text-gray-600 transition-colors"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
      </div>

      <!-- Dialog Body with Selectable Cards -->
      <div class="p-6 space-y-6">
        <!-- Dashboard Snapshot Option (only for PDF) - MODERNIZED DESIGN -->
        <div 
          v-if="format === 'pdf'"
          @click="toggleSnapshotMode"
          class="group relative cursor-pointer transition-all duration-300 ease-out transform hover:scale-[1.01] hover:shadow-xl"
          :class="[
            'border rounded-xl overflow-hidden',
            isSnapshotMode 
              ? 'border-purple-200 bg-gradient-to-br from-purple-50 via-white to-purple-50 shadow-lg shadow-purple-100/50' 
              : 'border-gray-200 bg-white hover:border-purple-200 hover:shadow-md'
          ]"
        >
          <!-- Modern Header Section -->
          <div class="relative p-6 pb-4">
            <!-- Checkbox - Modern Style -->
            <div class="absolute top-6 right-6 z-10">
              <div 
                class="w-6 h-6 rounded-lg border-2 flex items-center justify-center transition-all duration-200 shadow-sm"
                :class="[
                  isSnapshotMode
                    ? 'border-purple-500 bg-purple-500 shadow-purple-200'
                    : 'border-gray-300 bg-white group-hover:border-purple-300'
                ]"
              >
                <svg 
                  v-if="isSnapshotMode"
                  class="w-4 h-4 text-white" 
                  fill="currentColor" 
                  viewBox="0 0 20 20"
                >
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
              </div>
            </div>

            <!-- Icon and Title Section -->
            <div class="flex items-start space-x-4 mb-4">
              <!-- Modern Camera Icon -->
              <div 
                class="flex-shrink-0 w-12 h-12 rounded-xl flex items-center justify-center transition-all duration-200"
                :class="[
                  isSnapshotMode 
                    ? 'bg-purple-100 text-purple-600' 
                    : 'bg-gray-100 text-gray-600 group-hover:bg-purple-50 group-hover:text-purple-500'
                ]"
              >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
              </div>

              <!-- Title and Description -->
              <div class="flex-1 min-w-0">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Dashboard Snapshot</h3>
                <p class="text-sm text-gray-600 leading-relaxed">Capture a visual snapshot with live charts and current data</p>
              </div>
            </div>

            <!-- Feature Tags -->
            <div class="flex flex-wrap gap-2 mb-4">
              <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm0 4a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1V8zm8 0a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V8z" clip-rule="evenodd"></path>
                </svg>
                Live Charts
              </span>
              <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">
                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                High Quality
              </span>
              <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-purple-50 text-purple-700 border border-purple-200">
                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"></path>
                </svg>
                Real-time Data
              </span>
            </div>
          </div>

          <!-- Info Banner -->
          <div 
            class="mx-6 mb-6 rounded-lg border transition-all duration-200"
            :class="[
              isSnapshotMode 
                ? 'bg-purple-50 border-purple-200' 
                : 'bg-blue-50 border-blue-200 group-hover:bg-purple-50 group-hover:border-purple-200'
            ]"
          >
            <div class="p-4">
              <div class="flex items-start space-x-3">
                <div 
                  class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center mt-0.5"
                  :class="[
                    isSnapshotMode 
                      ? 'bg-purple-100 text-purple-600' 
                      : 'bg-blue-100 text-blue-600 group-hover:bg-purple-100 group-hover:text-purple-600'
                  ]"
                >
                  <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                  </svg>
                </div>
                <div class="flex-1">
                  <p 
                    class="text-sm font-medium mb-1"
                    :class="[
                      isSnapshotMode 
                        ? 'text-purple-800' 
                        : 'text-blue-800 group-hover:text-purple-800'
                    ]"
                  >
                    Perfect for reports and presentations
                  </p>
                  <p 
                    class="text-xs leading-relaxed"
                    :class="[
                      isSnapshotMode 
                        ? 'text-purple-600' 
                        : 'text-blue-600 group-hover:text-purple-600'
                    ]"
                  >
                    Choose which sections to include in your visual snapshot
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Selection Indicator -->
          <div 
            v-if="isSnapshotMode"
            class="absolute inset-0 pointer-events-none"
          >
            <div class="absolute inset-0 bg-gradient-to-r from-purple-500/5 via-transparent to-purple-500/5 rounded-xl"></div>
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-purple-500 to-purple-600 rounded-t-xl"></div>
          </div>
        </div>

        <!-- Snapshot mode explanation -->
        <div v-if="format === 'pdf' && isSnapshotMode" class="bg-gradient-to-r from-purple-50 to-indigo-50 border border-purple-200 rounded-xl p-5">
          <div class="flex items-start space-x-4">
            <div class="flex-shrink-0 w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center">
              <svg class="w-5 h-5 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
              </svg>
            </div>
            <div class="flex-1">
              <p class="text-sm font-semibold text-purple-900 mb-2">Snapshot Mode Active</p>
              <p class="text-sm text-purple-700 leading-relaxed">Select the sections you want to include in your visual snapshot. Charts will be captured as high-quality images.</p>
            </div>
          </div>
        </div>

        <!-- Divider (only shown when snapshot option is available) -->
        <div v-if="format === 'pdf' && isSnapshotMode" class="relative">
          <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-gray-300"></div>
          </div>
          <div class="relative flex justify-center text-sm">
            <span class="px-2 bg-gray-50 text-gray-500">Choose sections for your snapshot</span>
          </div>
        </div>

        <!-- Dashboard Overview Card -->
        <div 
          @click="!isSnapshotMode ? toggleSelection('dashboard') : toggleSnapshotSection('dashboard')"
          class="relative cursor-pointer transition-all duration-200 transform hover:scale-[1.02] hover:shadow-lg"
          :class="[
            'border-2 rounded-lg p-4',
            selectedSections.includes('dashboard') 
              ? 'border-purple-300 bg-purple-50' 
              : 'border-gray-200 bg-white hover:border-gray-300',
            isSnapshotMode ? 'ring-2 ring-purple-200' : ''
          ]"
        >
          <!-- Checkbox -->
          <div class="absolute top-4 right-4 z-10">
            <div 
              class="w-5 h-5 rounded border-2 flex items-center justify-center transition-colors"
              :class="[
                selectedSections.includes('dashboard')
                  ? 'border-purple-500 bg-purple-500'
                  : 'border-gray-300 bg-white'
              ]"
            >
              <svg 
                v-if="selectedSections.includes('dashboard')"
                class="w-3 h-3 text-white" 
                fill="currentColor" 
                viewBox="0 0 20 20"
              >
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
              </svg>
            </div>
          </div>

          <!-- Section Title -->
          <div class="mb-3">
            <h3 class="text-lg font-medium text-gray-900">Dashboard Overview</h3>
            <p class="text-sm text-gray-600">Summary cards with key metrics and charts</p>
          </div>

          <!-- Skeleton Preview -->
          <div class="pointer-events-none">
            <DashboardSkeleton />
          </div>
        </div>

        <!-- Cash Flow Analysis Card -->
        <div 
          @click="!isSnapshotMode ? toggleSelection('cashflow') : toggleSnapshotSection('cashflow')"
          class="relative cursor-pointer transition-all duration-200 transform hover:scale-[1.02] hover:shadow-lg"
          :class="[
            'border-2 rounded-lg p-4',
            selectedSections.includes('cashflow') 
              ? 'border-purple-300 bg-purple-50' 
              : 'border-gray-200 bg-white hover:border-gray-300',
            isSnapshotMode ? 'ring-2 ring-purple-200' : ''
          ]"
        >
          <!-- Checkbox -->
          <div class="absolute top-4 right-4 z-10">
            <div 
              class="w-5 h-5 rounded border-2 flex items-center justify-center transition-colors"
              :class="[
                selectedSections.includes('cashflow')
                  ? 'border-purple-500 bg-purple-500'
                  : 'border-gray-300 bg-white'
              ]"
            >
              <svg 
                v-if="selectedSections.includes('cashflow')"
                class="w-3 h-3 text-white" 
                fill="currentColor" 
                viewBox="0 0 20 20"
              >
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
              </svg>
            </div>
          </div>

          <!-- Section Title -->
          <div class="mb-3">
            <h3 class="text-lg font-medium text-gray-900">Cash Flow Analysis</h3>
            <p class="text-sm text-gray-600">Predictive analysis with income and expense trends</p>
          </div>

          <!-- Skeleton Preview -->
          <div class="pointer-events-none">
            <CashFlowSkeleton />
          </div>
        </div>

        <!-- Recent Invoices Card -->
        <div 
          @click="!isSnapshotMode ? toggleSelection('invoices') : toggleSnapshotSection('invoices')"
          class="relative cursor-pointer transition-all duration-200 transform hover:scale-[1.02] hover:shadow-lg"
          :class="[
            'border-2 rounded-lg p-4',
            selectedSections.includes('invoices') 
              ? 'border-purple-300 bg-purple-50' 
              : 'border-gray-200 bg-white hover:border-gray-300',
            isSnapshotMode ? 'ring-2 ring-purple-200' : ''
          ]"
        >
          <!-- Checkbox -->
          <div class="absolute top-4 right-4 z-10">
            <div 
              class="w-5 h-5 rounded border-2 flex items-center justify-center transition-colors"
              :class="[
                selectedSections.includes('invoices')
                  ? 'border-purple-500 bg-purple-500'
                  : 'border-gray-300 bg-white'
              ]"
            >
              <svg 
                v-if="selectedSections.includes('invoices')"
                class="w-3 h-3 text-white" 
                fill="currentColor" 
                viewBox="0 0 20 20"
              >
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
              </svg>
            </div>
          </div>

          <!-- Section Title -->
          <div class="mb-3">
            <h3 class="text-lg font-medium text-gray-900">Recent Invoices</h3>
            <p class="text-sm text-gray-600">Detailed invoice table with customer information</p>
          </div>

          <!-- Skeleton Preview -->
          <div class="pointer-events-none">
            <InvoiceTableSkeleton />
          </div>
        </div>
      </div>

      <!-- Dialog Footer -->
      <div class="flex items-center justify-between p-6 border-t border-gray-200 bg-gray-50">
        <div class="flex items-center space-x-4">
          <button 
            @click="selectAll"
            class="text-sm text-purple-600 hover:text-purple-700 font-medium transition-colors"
          >
            Select All
          </button>
          <button 
            @click="clearSelection"
            class="text-sm text-gray-600 hover:text-gray-700 font-medium transition-colors"
          >
            Clear Selection
          </button>
        </div>

        <div class="flex items-center space-x-3">
          <span v-if="isSnapshotMode" class="text-sm text-gray-600">
            📸 Snapshot: {{ selectedSections.length }} section{{ selectedSections.length !== 1 ? 's' : '' }} selected
          </span>
          <span v-else class="text-sm text-gray-600">
            {{ selectedSections.length }} section{{ selectedSections.length !== 1 ? 's' : '' }} selected
          </span>
          <button 
            @click="$emit('close')"
            class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
          >
            Cancel
          </button>
          <button 
            @click="exportSelected"
            :disabled="!isSnapshotMode && selectedSections.length === 0"
            class="px-4 py-2 text-white rounded-lg transition-colors flex items-center space-x-2"
            :class="[
              isSnapshotMode || selectedSections.length > 0
                ? 'bg-purple-600 hover:bg-purple-700'
                : 'bg-gray-400 cursor-not-allowed'
            ]"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <span>Export Selected</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Export Notification -->

</template>

<script setup>
import { ref, defineProps, defineEmits } from 'vue'
import { useDashboardStore } from '@/scripts/admin/stores/dashboard'
import { useDateFilterStore } from '@/scripts/admin/stores/dateFilter'
import DashboardSkeleton from './DashboardSkeleton.vue'
import CashFlowSkeleton from './CashFlowSkeleton.vue'
import InvoiceTableSkeleton from './InvoiceTableSkeleton.vue'


const selectedSections = ref([])
const dashboardStore = useDashboardStore()
const dateFilterStore = useDateFilterStore()
const isSnapshotMode = ref(false)
const showNotification = ref(false)

const props = defineProps({
  format: {
    type: String,
    required: true,
  },
})

const emit = defineEmits(['close', 'export-snapshot'])

const closeDialog = (event) => {
  if (event.target === event.currentTarget) {
    emit('close')
  }
}

const toggleSelection = (section) => {
  const index = selectedSections.value.indexOf(section)
  if (index > -1) {
    selectedSections.value.splice(index, 1)
  } else {
    selectedSections.value.push(section)
  }
}

const toggleSnapshotMode = () => {
  isSnapshotMode.value = !isSnapshotMode.value
  if (isSnapshotMode.value) {
    // Auto-select all sections when entering snapshot mode
    selectedSections.value = ['dashboard', 'cashflow', 'invoices']
  } else {
    // Clear selections when exiting snapshot mode
    selectedSections.value = []
  }
}

const toggleSnapshotSection = (section) => {
  const index = selectedSections.value.indexOf(section)
  if (index > -1) {
    selectedSections.value.splice(index, 1)
  } else {
    selectedSections.value.push(section)
  }
}

const selectAll = () => {
  selectedSections.value = ['dashboard', 'cashflow', 'invoices']
}

const clearSelection = () => {
  selectedSections.value = []
}

const exportSelected = () => {
  if (isSnapshotMode.value && props.format === 'pdf') {
    // Use the new dashboard snapshot export with selected sections
    emit('export-snapshot', selectedSections.value)
    emit('close')
  } else if (selectedSections.value.length > 0) {
    const params = {
      format: props.format,
      sections: selectedSections.value,
      type: 'clients', // Default type for top outstanding invoices
    };

    // Include unified date filter parameters if they are set
    const dateRange = dateFilterStore.dateRange
    if (dateRange && dateRange.start && dateRange.end) {
      params.start_date = dateRange.start
      params.end_date = dateRange.end
    }

    dashboardStore.exportDashboard(params)
    emit('close')
  }
}

const handleDownload = () => {
  console.log('Download initiated for sections:', selectedSections.value)
}
</script>
