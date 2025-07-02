<!-- CashFlowPredictiveChart.vue -->
<template>
  <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <div>
        <h3 class="text-2xl font-semibold text-gray-900 dark:text-white mb-2">
          Predictive Cash Flow Analysis
        </h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Real vs projected income with net cash flow forecast for {{ currentDateRangeLabel }}
        </p>
      </div>
      <div class="text-sm text-gray-500 dark:text-gray-400">
        Filtered by: {{ currentDateRangeLabel }}
      </div>
    </div>

    <!-- Chart -->
    <div class="relative h-96">
      <canvas ref="canvasRef" class="w-full h-full"></canvas>
    </div>
    
    <!-- Today Indicator - Outside chart area -->
    <div class="flex justify-end mt-2 mb-4">
      <div class="bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-200 px-3 py-1 rounded-full text-xs font-medium shadow-sm border border-yellow-200 dark:border-yellow-700">
        Today: {{ today }}
      </div>
    </div>

    <!-- Interactive Legend -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
      <button 
        @click="toggleDataset('Real Income')"
        class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200"
        :class="{ 'opacity-50': hiddenDatasets.includes('Real Income') }"
      >
        <div class="w-4 h-0.5 bg-green-500"></div>
        <span class="text-sm text-gray-600 dark:text-gray-400">Real Income</span>
      </button>
      <button 
        @click="toggleDataset('Projected Income')"
        class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200"
        :class="{ 'opacity-50': hiddenDatasets.includes('Projected Income') }"
      >
        <div class="w-4 h-0.5 bg-blue-500 border-dashed border-t-2 border-blue-500"></div>
        <span class="text-sm text-gray-600 dark:text-gray-400">Projected Income</span>
      </button>
      <button 
        @click="toggleDataset('Expenses')"
        class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200"
        :class="{ 'opacity-50': hiddenDatasets.includes('Expenses') }"
      >
        <div class="w-4 h-3 bg-red-200 dark:bg-red-900/50"></div>
        <span class="text-sm text-gray-600 dark:text-gray-400">Expenses</span>
      </button>
      <button 
        @click="toggleDataset('Net Cash Flow')"
        class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200"
        :class="{ 'opacity-50': hiddenDatasets.includes('Net Cash Flow') }"
      >
        <div class="w-4 h-0.5 bg-purple-500"></div>
        <span class="text-sm text-gray-600 dark:text-gray-400">Net Cash Flow</span>
      </button>
    </div>

    <!-- Loading Overlay -->
    <div
      v-if="loading"
      class="absolute inset-0 bg-white dark:bg-gray-800/75 rounded-2xl flex items-center justify-center"
    >
      <div class="animate-spin rounded-full h-10 w-10 border-b-4 border-primary-500"></div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import Chart from 'chart.js/auto'
import 'chartjs-adapter-date-fns'
import annotationPlugin from 'chartjs-plugin-annotation'
import axios from 'axios'
import { handleError } from '@/scripts/helpers/error-handling'
import { useDateFilterStore } from '@/scripts/admin/stores/dateFilter'
import { useThemeStore } from '@/scripts/stores/theme'

Chart.register(annotationPlugin)

const dateFilterStore = useDateFilterStore()
const themeStore = useThemeStore()

const loading = ref(true)
const hiddenDatasets = ref([])
const chartApiData = ref([])
const currentDateRange = ref({ start: '', end: '' })
const currentDateRangeLabel = ref('Last 30 days')

// Initialize current date range from unified filter
function initializeDateRange() {
  const range = dateFilterStore.dateRange
  currentDateRange.value = range
  currentDateRangeLabel.value = dateFilterStore.displayLabel
}

const today = computed(() => {
  return new Date().toLocaleDateString('en-US', { 
    month: 'short', 
    day: 'numeric' 
  })
})

const canvasRef = ref(null)
let chartInstance = null

function formatMoney(amount) {
  const amountInDollars = (amount || 0) / 100;
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amountInDollars)
}

async function fetchData() {
  loading.value = true
  
  try {
    const params = {
      period_months: 12, // Default to 12 months for "All time"
    }
    
    // Only add date parameters if we have a date range (not "All time")
    if (currentDateRange.value.start && currentDateRange.value.end) {
      // Calculate period months based on date range
      const startDate = new Date(currentDateRange.value.start)
      const endDate = new Date(currentDateRange.value.end)
      const diffTime = Math.abs(endDate - startDate)
      const diffMonths = Math.ceil(diffTime / (1000 * 60 * 60 * 24 * 30))
      const periodMonths = Math.max(6, Math.min(15, diffMonths + 3)) // Add buffer for predictions
      
      params.period_months = periodMonths
      params.start_date = currentDateRange.value.start
      params.end_date = currentDateRange.value.end
    }
    
    const response = await axios.get('/api/v1/dashboard/cash-flow', { params })
    chartApiData.value = response.data
  } catch (error) {
    handleError(error)
    chartApiData.value = []
  } finally {
    loading.value = false
  }
}

function buildDatasets() {
  const data = chartApiData.value
  const ctx = canvasRef.value.getContext('2d')

  return [
    {
      label: 'Expenses',
      data: data.map(d => ({ x: d.date, y: d.expenses })),
      borderColor: 'rgba(239, 68, 68, 0.8)',
      backgroundColor: 'rgba(239, 68, 68, 0.1)',
      borderWidth: 2,
      fill: true,
      tension: 0.4,
      pointRadius: 3,
      order: 4,
      spanGaps: false
    },
    {
      label: 'Real Income',
      data: data.map(d => ({ x: d.date, y: d.realIncome })),
      borderColor: '#22C55E',
      borderWidth: 3,
      fill: false,
      tension: 0.3,
      order: 1,
      spanGaps: false
    },
    {
      label: 'Projected Income',
      data: data.map(d => ({ x: d.date, y: d.projectedIncome })),
      borderColor: '#3B82F6',
      borderWidth: 3,
      borderDash: [8, 5],
      fill: false,
      tension: 0.3,
      order: 2,
      spanGaps: false
    },
    {
      label: 'Net Cash Flow',
      data: data.map(d => ({ x: d.date, y: d.netCashFlow })),
      borderColor: '#8B5CF6',
      borderWidth: 2,
      fill: false,
      tension: 0.4,
      order: 3,
      spanGaps: false
    }
  ]
}

function createChart() {
  if (!canvasRef.value) return
  const ctx = canvasRef.value.getContext('2d')
  const today = new Date()
  
  const isDark = themeStore.isDarkMode
  const gridColor = isDark ? 'rgba(255, 255, 255, 0.1)' : '#F3F4F6'
  const tickColor = isDark ? '#9CA3AF' : '#6B7280'
  const titleColor = isDark ? '#D1D5DB' : '#374151'
  
  chartInstance = new Chart(ctx, {
    type: 'line',
    data: { datasets: [] },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      animation: { duration: 1500, easing: 'easeOutCubic' },
      scales: {
        x: {
          type: 'time',
          time: { unit: 'month', tooltipFormat: 'MMM yyyy', displayFormats: { month: 'MMM' }},
          grid: { display: true, color: gridColor, borderDash: [2, 2] },
          ticks: { color: tickColor }
        },
        y: {
          beginAtZero: true,
          ticks: { 
            color: tickColor,
            callback: (v) => {
              const amountInDollars = v / 100;
              if (Math.abs(amountInDollars) >= 1000) {
                return `$${(amountInDollars / 1000).toFixed(0)}K`;
              }
              return `$${amountInDollars.toFixed(0)}`;
            }
          },
          title: { display: true, text: 'Amount ($)', color: titleColor }
        },
      },
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            title: (context) => new Date(context[0].parsed.x).toLocaleDateString('en-US', { month: 'long', year: 'numeric' }),
            label: (context) => `${context.dataset.label}: $${formatMoney(context.parsed.y)}`
          }
        },
        annotation: {
          annotations: {
            todayLine: {
              type: 'line',
              xMin: today,
              xMax: today,
              borderColor: '#F59E0B',
              borderWidth: 2,
              borderDash: [5, 5],
            }
          }
        }
      }
    }
  })
}

function toggleDataset(datasetLabel) {
  const index = hiddenDatasets.value.indexOf(datasetLabel)
  if (index > -1) {
    hiddenDatasets.value.splice(index, 1)
  } else {
    hiddenDatasets.value.push(datasetLabel)
  }
  
  if (chartInstance) {
    chartInstance.data.datasets.forEach(dataset => {
      dataset.hidden = hiddenDatasets.value.includes(dataset.label)
    })
    chartInstance.update('active')
  }
}

function refreshChart() {
  if (!chartInstance) return
  
  chartInstance.data.datasets = buildDatasets()
  
  chartInstance.data.datasets.forEach(dataset => {
    dataset.hidden = hiddenDatasets.value.includes(dataset.label)
  })
  
  chartInstance.update('active')
}

// Method to refresh chart with new date range from unified filter
function refreshWithDateRange(newDateRange) {
  currentDateRange.value = newDateRange
  currentDateRangeLabel.value = dateFilterStore.displayLabel
  fetchData().then(() => refreshChart())
}

// Export method for PDF snapshot
function getChartAsBase64Image() {
  if (!chartInstance || !chartInstance.canvas) {
    return null
  }
  return chartInstance.toBase64Image('image/png', 1)
}

// Watch for theme changes to update chart colors
watch(() => themeStore.isDarkMode, (isDark) => {
  if (chartInstance) {
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.1)' : '#F3F4F6'
    const tickColor = isDark ? '#9CA3AF' : '#6B7280'
    const titleColor = isDark ? '#D1D5DB' : '#374151'

    chartInstance.options.scales.x.grid.color = gridColor
    chartInstance.options.scales.x.ticks.color = tickColor
    chartInstance.options.scales.y.ticks.color = tickColor
    chartInstance.options.scales.y.title.color = titleColor
    chartInstance.update()
  }
})

// Expose methods to parent component
defineExpose({
  getChartAsBase64Image,
  refreshWithDateRange
})

onMounted(async () => {
  initializeDateRange()
  createChart()
  await fetchData()
  refreshChart()
})

onUnmounted(() => {
  if (chartInstance) {
    chartInstance.destroy()
  }
})

</script>
