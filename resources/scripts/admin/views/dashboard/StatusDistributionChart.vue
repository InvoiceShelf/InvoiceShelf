<!-- StatusDistribution.vue -->
<template>
  <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 relative h-full flex flex-col">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
        Status Distribution
      </h3>
      <div class="w-32">
        <BaseMultiselect
          v-model="selectedYear"
          :options="yearOptions"
          @update:model-value="onYearChange"
          :allow-empty="false"
          :show-labels="false"
          :placeholder="$t('dashboard.select_year')"
          :can-deselect="false"
          class="text-sm"
        />
      </div>
    </div>

    <!-- Chart + Tooltip -->
    <div class="relative flex-1 flex items-center justify-center">
      <canvas ref="canvasRef" class="w-full h-full"></canvas>

      <!-- Tooltip -->
      <div
        v-if="tooltip.show"
        :style="{ top: tooltip.y + 'px', left: tooltip.x + 'px' }"
        class="absolute transform -translate-x-1/2 -translate-y-full bg-white dark:bg-gray-800 p-3 rounded-lg shadow-lg pointer-events-none"
      >
        <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">
          {{ tooltip.label }} Invoice
        </div>
        <div class="text-2xl font-bold text-gray-900 dark:text-white">
          {{ tooltip.value }}
        </div>
      </div>
    </div>

    <!-- Legend (clickable) -->
    <div class="mt-6 flex divide-x divide-gray-200 dark:divide-gray-600">
      <div
        v-for="(status, idx) in statusData"
        :key="status.label"
        @click="toggleSegment(idx)"
        :class="[
          'flex-1 text-center px-4 first:pl-0 last:pr-0 cursor-pointer',
          !isSegmentVisible(idx) ? 'opacity-50' : ''
        ]"
      >
        <div class="flex items-center justify-center space-x-2 mb-2">
          <div :class="`w-3 h-3 rounded-full ${status.color}`"></div>
          <span class="text-sm text-gray-600 dark:text-gray-400">
            {{ status.label }}
          </span>
        </div>
        <div class="flex items-center justify-center space-x-2">
          <span class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ status.count }}
          </span>
        </div>
      </div>
    </div>

    <!-- Loading Overlay -->
    <div
      v-if="dashboardStore.isLoading"
      class="absolute inset-0 bg-white dark:bg-gray-800 rounded-lg flex items-center justify-center"
    >
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500"></div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import Chart from 'chart.js/auto'
import { useI18n } from 'vue-i18n'
import { useDashboardStore } from '@/scripts/admin/stores/dashboard'

const { t } = useI18n()
const dashboardStore = useDashboardStore()

// --- Legend data ---
const statusData = computed(() => [
  { label: 'Paid', count: dashboardStore.statusDistribution.paid, color: 'bg-purple-500' },
  { label: 'Pending', count: dashboardStore.statusDistribution.pending, color: 'bg-purple-300' },
  { label: 'Overdue', count: dashboardStore.statusDistribution.overdue, color: 'bg-gray-200' }
])

// --- Year selector ---
const selectedYear = ref({ label: 'This Year', value: 'this_year' })
const yearOptions = ref([
  { label: 'This Year', value: 'this_year' },
  { label: 'Previous Year', value: 'previous_year' }
])

function onYearChange(option) {
  const params = option.value === 'previous_year' ? { previous_year: true } : {}
  dashboardStore.loadData(params)
}

// --- Chart.js setup ---
const canvasRef = ref(null)
let chartInstance = null

const chartData = computed(() => ({
  labels: ['Paid', 'Pending', 'Overdue'],
  datasets: [
    {
      data: [
        dashboardStore.statusDistribution.paid,
        dashboardStore.statusDistribution.pending,
        dashboardStore.statusDistribution.overdue
      ],
      backgroundColor: ['#7675FF', '#A7AAFF', '#E5E7EB'],
      borderWidth: 0,
      borderRadius: 8,
      hoverOffset: 12,
      cutout: '70%',
      spacing: 4
    }
  ]
}))

const tooltip = ref({
  show: false,
  x: 0,
  y: 0,
  label: '',
  value: ''
})

// helper to format values
function formatValue(val) {
  return val.toLocaleString()
}

// options with onHover using tooltipPosition()
const options = {
  responsive: true,
  maintainAspectRatio: false,
  rotation: -90 * (Math.PI / 180),
  plugins: {
    legend: { display: false },
    tooltip: { enabled: false }
  },
  onHover(event, elements) {
    if (elements.length) {
      const arc = elements[0].element
      const { x, y } = arc.tooltipPosition()
      tooltip.value.x = x
      tooltip.value.y = y
      tooltip.value.show = true

      const idx = elements[0].index
      tooltip.value.label = chartData.value.labels[idx]
      tooltip.value.value = formatValue(chartData.value.datasets[0].data[idx])
      chartInstance.canvas.style.cursor = 'pointer'
    } else {
      tooltip.value.show = false
      chartInstance.canvas.style.cursor = 'default'
    }
  }
}

// toggle visibility of a segment
function toggleSegment(idx) {
  if (!chartInstance) return
  chartInstance.toggleDataVisibility(idx)
  chartInstance.update()
}

// check if a segment is visible
function isSegmentVisible(idx) {
  return chartInstance
    ? chartInstance.getDataVisibility(idx)
    : true
}

watch(chartData, (newChartData) => {
  if (chartInstance) {
    chartInstance.data = newChartData
    chartInstance.update()
  }
})

onMounted(() => {
  chartInstance = new Chart(
    canvasRef.value.getContext('2d'),
    {
      type: 'doughnut',
      data: chartData.value,
      options
    }
  )
})

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

onUnmounted(() => {
  if(chartInstance) {
    chartInstance.destroy()
  }
})
</script>
