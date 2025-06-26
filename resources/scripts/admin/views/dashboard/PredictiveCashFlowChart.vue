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
          Real vs projected income with net cash flow forecast
        </p>
      </div>
      <div class="flex space-x-3">
        <div class="w-32">
          <BaseMultiselect
            v-model="selectedPeriod"
            :options="periodOptions"
            :allow-empty="false"
            :show-labels="false"
            placeholder="Select Period"
            :can-deselect="false"
            class="text-sm"
          />
        </div>
        <div class="w-36">
          <BaseMultiselect
            v-model="selectedCustomer"
            :options="customerOptions"
            :allow-empty="false"
            :show-labels="false"
            placeholder="All Customers"
            :can-deselect="true"
            class="text-sm"
          />
        </div>
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
        :class="{ 'opacity-50': hiddenDatasets.includes('Expenses'), 'cursor-not-allowed': selectedCustomer }"
        :disabled="selectedCustomer"
      >
        <div class="w-4 h-3 bg-red-200 dark:bg-red-900/50"></div>
        <span class="text-sm text-gray-600 dark:text-gray-400">Expenses</span>
      </button>
      <button 
        @click="toggleDataset('Net Cash Flow')"
        class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200"
        :class="{ 'opacity-50': hiddenDatasets.includes('Net Cash Flow'), 'cursor-not-allowed': selectedCustomer }"
        :disabled="selectedCustomer"
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

Chart.register(annotationPlugin)

const loading = ref(true)
const selectedPeriod = ref('6 Months')
const selectedCustomer = ref(null)
const hiddenDatasets = ref([])
const chartApiData = ref([])

const periodOptions = ref([
  { label: '3 Months', value: '3 Months' },
  { label: '6 Months', value: '6 Months' },
  { label: '12 Months', value: '12 Months' }
])

const customerOptions = ref([])

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

const periodMonths = computed(() => {
  const periods = { '3 Months': 6, '6 Months': 9, '12 Months': 15 }
  return periods[selectedPeriod.value] || 9
})

async function fetchCustomers() {
  try {
    const response = await axios.get('/api/v1/customers')
    customerOptions.value = response.data.data.map(c => ({ label: c.name, value: c.id }))
  } catch (error) {
    handleError(error)
  }
}

async function fetchData() {
  loading.value = true
  try {
    const response = await axios.get('/api/v1/dashboard/cash-flow', {
      params: {
        period_months: periodMonths.value,
        customer_id: selectedCustomer.value,
      }
    })
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
          grid: { display: true, color: '#F3F4F6', borderDash: [2, 2] }
        },
        y: {
          beginAtZero: true,
          ticks: { 
            callback: (v) => {
              const amountInDollars = v / 100;
              if (Math.abs(amountInDollars) >= 1000) {
                return `$${(amountInDollars / 1000).toFixed(0)}K`;
              }
              return `$${amountInDollars.toFixed(0)}`;
            }
          },
          title: { display: true, text: 'Amount ($)' }
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

onMounted(async () => {
  createChart()
  await Promise.all([
    fetchCustomers(),
    fetchData()
  ])
  refreshChart()
})

onUnmounted(() => {
  if (chartInstance) {
    chartInstance.destroy()
  }
})

watch([selectedPeriod, selectedCustomer], async () => {
  await fetchData()
  refreshChart()
})

</script>
