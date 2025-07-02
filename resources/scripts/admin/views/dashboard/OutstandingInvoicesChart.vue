<!-- Top5BarChart.vue -->
<template>
  <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 h-full flex flex-col">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
      Top 5 Outstanding Invoices
    </h3>
    <!-- Filters -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 space-y-4 md:space-y-0">
      <!-- Tipo de Top 5 -->
      <div class="w-40">
        <BaseMultiselect
          v-model="selectedType"
          :options="typeOptions"
          :allow-empty="false"
          :show-labels="false"
          placeholder="Top 5 by"
          :can-deselect="false"
          class="text-sm"
        />
      </div>

      <!-- Date Range Indicator -->
      <div class="text-sm text-gray-500 dark:text-gray-400">
        Filtered by: {{ currentDateRangeLabel }}
      </div>
    </div>

    <!-- Chart -->
    <div class="relative flex-1">
      <canvas ref="canvasRef" class="w-full h-full"></canvas>
       <!-- Loading Overlay -->
      <div v-if="loading" class="absolute inset-0 bg-white/70 dark:bg-gray-800/70 rounded-lg flex items-center justify-center">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted, computed } from 'vue'
import Chart from 'chart.js/auto'
import axios from 'axios'
import { handleError } from '@/scripts/helpers/error-handling'
import { useDateFilterStore } from '@/scripts/admin/stores/dateFilter'
import { useThemeStore } from '@/scripts/stores/theme'

const dateFilterStore = useDateFilterStore()
const themeStore = useThemeStore()

const typeOptions = [
  { label: 'Clients', value: 'clients' },
  { label: 'Products', value: 'products' }
]

const selectedType = ref('clients')
const currentDateRange = ref({ start: '', end: '' })
const currentDateRangeLabel = ref('Last 30 days')

const canvasRef = ref(null)
let chartInstance = null
const loading = ref(true)
const chartApiData = ref([])

const barColor = '#7675FF'
const barHoverColor = '#4A3DFF'

function formatDate(date) {
  if (!date) return ''
  const d = new Date(date)
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

// Initialize current date range from unified filter
function initializeDateRange() {
  const range = dateFilterStore.dateRange
  currentDateRange.value = range
  currentDateRangeLabel.value = dateFilterStore.displayLabel
}

const chartData = computed(() => {
  const labels = chartApiData.value.map(d => d.label)
  const values = chartApiData.value.map(d => parseFloat(d.value) || 0)
  const total = values.reduce((sum, v) => sum + v, 0)
  return { labels, values, total }
})

async function fetchData() {
  loading.value = true
  
  try {
    const params = {
      type: selectedType.value,
    }
    
    // Only add date parameters if we have a date range (not "All time")
    if (currentDateRange.value.start && currentDateRange.value.end) {
      params.start_date = currentDateRange.value.start
      params.end_date = currentDateRange.value.end
    }
    
    const response = await axios.get('/api/v1/dashboard/top-outstanding', { params })
    chartApiData.value = response.data
  } catch (error) {
    handleError(error)
    chartApiData.value = []
  } finally {
    loading.value = false
  }
}

function createChart() {
  if (!canvasRef.value) return
  const ctx = canvasRef.value.getContext('2d')
  const { labels, values, total } = chartData.value

  const tickColor = themeStore.isDarkMode ? '#9CA3AF' : '#374151'

  chartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        data: values,
        backgroundColor: Array(labels.length).fill(barColor),
        hoverBackgroundColor: Array(labels.length).fill(barHoverColor),
        barThickness: 24,
        borderRadius: 6,
        borderSkipped: false
      }]
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 800, easing: 'easeOutQuart' },
      scales: {
        x: {
          ticks: { 
            callback: v => `$${parseFloat(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`, 
            color: tickColor, 
            font: { size: 12 } 
          },
          grid: { display: false }
        },
        y: {
          ticks: {
            color: tickColor,
            font: { size: 12 },
            callback: function(value) {
                const label = this.getLabelForValue(value);
                if (label.length > 15) {
                    return label.substring(0, 15) + '...';
                }
                return label;
            }
          },
          grid: { display: false }
        }
      },
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label(ctx) {
              const val = ctx.parsed.x || 0;
              const { total } = chartData.value;
              const formattedVal = parseFloat(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
              if (total === 0) return `$${formattedVal}`;
              const pct = ((val / total) * 100).toFixed(1);
              return `$${formattedVal} (${pct}%)`;
            }
          }
        }
      },
    }
  })
}

function updateChart() {
  if (!chartInstance) {
    createChart()
    return
  }
  const { labels, values } = chartData.value
  chartInstance.data.labels = labels
  chartInstance.data.datasets[0].data = values
  chartInstance.data.datasets[0].backgroundColor = Array(labels.length).fill(barColor)
  chartInstance.data.datasets[0].hoverBackgroundColor = Array(labels.length).fill(barHoverColor)
  chartInstance.update()
}

// Method to refresh chart with new date range from unified filter
function refreshWithDateRange(newDateRange) {
  currentDateRange.value = newDateRange
  currentDateRangeLabel.value = dateFilterStore.displayLabel
  fetchData()
}

// Export method for PDF snapshot
function getChartAsBase64Image() {
  if (!chartInstance || !chartInstance.canvas) {
    return null
  }
  return chartInstance.toBase64Image('image/png', 1)
}

// Expose methods to parent component
defineExpose({
  getChartAsBase64Image,
  refreshWithDateRange
})

onMounted(() => {
  initializeDateRange()
  fetchData()
})

onUnmounted(() => {
    if (chartInstance) chartInstance.destroy()
})

watch(selectedType, fetchData)
watch(chartData, updateChart)

watch(() => themeStore.isDarkMode, (isDark) => {
  if (chartInstance) {
    const newTickColor = isDark ? '#9CA3AF' : '#374151'
    chartInstance.options.scales.x.ticks.color = newTickColor
    chartInstance.options.scales.y.ticks.color = newTickColor
    chartInstance.update()
  }
})

</script>
