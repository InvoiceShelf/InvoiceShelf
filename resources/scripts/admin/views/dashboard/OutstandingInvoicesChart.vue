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
          placeholder="Top 5 por"
          :can-deselect="false"
          class="text-sm"
        />
      </div>

      <!-- Selector de Fecha -->
      <div class="flex items-center space-x-4">
        <div class="w-40">
          <BaseMultiselect
            v-model="selectedDatePreset"
            :options="dateOptions"
            :allow-empty="false"
            :show-labels="false"
            placeholder="Período"
            :can-deselect="false"
            class="text-sm"
            @update:model-value="onDatePresetChange"
          />
        </div>
        <div v-if="selectedDatePreset === 'custom'" class="flex space-x-2">
          <input
            type="date"
            v-model="customRange.start"
            class="border rounded px-2 py-1 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
          />
          <input
            type="date"
            v-model="customRange.end"
            class="border rounded px-2 py-1 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
          />
        </div>
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

const typeOptions = [
  { label: 'Clientes', value: 'clients' },
  { label: 'Productos', value: 'products' }
]
const dateOptions = [
  { label: 'Este mes', value: 'this_month' },
  { label: 'Último trimestre', value: 'last_quarter' },
  { label: 'Año en curso', value: 'this_year' },
  { label: 'Personalizado', value: 'custom' }
]

const selectedType = ref('clients')
const selectedDatePreset = ref('this_month')
const customRange = ref({ start: '', end: '' })

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

const dateRange = computed(() => {
  const now = new Date()
  let start, end = now
  switch (selectedDatePreset.value) {
    case 'this_month':
      start = new Date(now.getFullYear(), now.getMonth(), 1)
      break
    case 'last_quarter':
      start = new Date(now.getFullYear(), now.getMonth() - 3, 1)
      break
    case 'this_year':
      start = new Date(now.getFullYear(), 0, 1)
      break
    case 'custom':
      return {
        start: customRange.value.start,
        end: customRange.value.end,
      }
  }
  return { start: formatDate(start), end: formatDate(end) }
})

const chartData = computed(() => {
  const labels = chartApiData.value.map(d => d.label)
  const values = chartApiData.value.map(d => d.value)
  const total = values.reduce((sum, v) => sum + v, 0)
  return { labels, values, total }
})

async function fetchData() {
  if (selectedDatePreset.value === 'custom' && (!dateRange.value.start || !dateRange.value.end)) {
    chartApiData.value = []
    return
  }

  loading.value = true
  try {
    const response = await axios.get('/api/v1/dashboard/top-outstanding', {
      params: {
        type: selectedType.value,
        start_date: dateRange.value.start,
        end_date: dateRange.value.end,
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

function createChart() {
  if (!canvasRef.value) return
  const ctx = canvasRef.value.getContext('2d')
  const { labels, values, total } = chartData.value

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
          ticks: { callback: v => `$${v.toLocaleString()}`, color: '#374151', font: { size: 12 } },
          grid: { display: false }
        },
        y: {
          ticks: {
            color: '#374151',
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
              if (total === 0) return `$${val.toLocaleString()}`;
              const pct = ((val / total) * 100).toFixed(1);
              return `$${val.toLocaleString()} (${pct}%)`;
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

function onDatePresetChange(preset) {
  if (preset !== 'custom') {
    customRange.value = { start: '', end: '' }
  }
}

// Export method for PDF snapshot
function getChartAsBase64Image() {
  if (!chartInstance || !chartInstance.canvas) {
    return null
  }
  return chartInstance.toBase64Image('image/png', 1)
}

// Expose method to parent component
defineExpose({
  getChartAsBase64Image
})

onMounted(fetchData)

onUnmounted(() => {
    if (chartInstance) chartInstance.destroy()
})

watch([selectedType, dateRange], fetchData, { deep: true })
watch(chartData, updateChart)

</script>
