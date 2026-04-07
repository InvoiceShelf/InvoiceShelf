<template>
  <div class="graph-container h-[300px]">
    <canvas id="graph" ref="graph" />
  </div>
</template>

<script setup lang="ts">
import { Chart } from 'chart.js/auto'
import type { ChartConfiguration, ChartDataset } from 'chart.js/auto'
import { ref, computed, onMounted, watchEffect, inject } from 'vue'
import { useCompanyStore } from '@/scripts/stores/company.store'

interface FormatUtils {
  formatMoney: (amount: number, currency: CurrencyInfo) => string
}

interface CurrencyInfo {
  [key: string]: unknown
}

interface Props {
  labels?: string[]
  values?: number[]
  invoices?: number[]
  expenses?: number[]
  receipts?: number[]
  income?: number[]
}

const utils = inject<FormatUtils>('utils')

const props = withDefaults(defineProps<Props>(), {
  labels: () => [],
  values: () => [],
  invoices: () => [],
  expenses: () => [],
  receipts: () => [],
  income: () => [],
})

let myLineChart: Chart | null = null
const graph = ref<HTMLCanvasElement | null>(null)
const companyStore = useCompanyStore()
const defaultCurrency = computed<CurrencyInfo>(() => {
  return companyStore.selectedCompanyCurrency
})

watchEffect(() => {
  if (props.labels) {
    if (myLineChart) {
      myLineChart.reset()
      update()
    }
  }
})

onMounted(() => {
  if (!graph.value) return
  const context = graph.value.getContext('2d')
  if (!context) return

  const style = getComputedStyle(document.documentElement)
  const gridColor =
    style.getPropertyValue('--color-line-light').trim() || 'rgba(0,0,0,0.1)'
  const tickColor =
    style.getPropertyValue('--color-muted').trim() || '#6b7280'
  const surfaceColor =
    style.getPropertyValue('--color-surface').trim() || '#fff'

  const datasets: ChartDataset<'line', number[]>[] = [
    {
      label: 'Sales',
      fill: false,
      tension: 0.3,
      backgroundColor: 'rgba(230, 254, 249)',
      borderColor: tickColor,
      borderCapStyle: 'butt',
      borderDash: [],
      borderDashOffset: 0.0,
      borderJoinStyle: 'miter',
      pointBorderColor: tickColor,
      pointBackgroundColor: surfaceColor,
      pointBorderWidth: 1,
      pointHoverRadius: 5,
      pointHoverBackgroundColor: tickColor,
      pointHoverBorderColor: 'rgba(220,220,220,1)',
      pointHoverBorderWidth: 2,
      pointRadius: 4,
      pointHitRadius: 10,
      data: props.invoices.map((invoice) => invoice / 100),
    },
    {
      label: 'Receipts',
      fill: false,
      tension: 0.3,
      backgroundColor: 'rgba(230, 254, 249)',
      borderColor: 'rgb(2, 201, 156)',
      borderCapStyle: 'butt',
      borderDash: [],
      borderDashOffset: 0.0,
      borderJoinStyle: 'miter',
      pointBorderColor: 'rgb(2, 201, 156)',
      pointBackgroundColor: surfaceColor,
      pointBorderWidth: 1,
      pointHoverRadius: 5,
      pointHoverBackgroundColor: 'rgb(2, 201, 156)',
      pointHoverBorderColor: 'rgba(220,220,220,1)',
      pointHoverBorderWidth: 2,
      pointRadius: 4,
      pointHitRadius: 10,
      data: props.receipts.map((receipt) => receipt / 100),
    },
    {
      label: 'Expenses',
      fill: false,
      tension: 0.3,
      backgroundColor: 'rgba(245, 235, 242)',
      borderColor: 'rgb(255,0,0)',
      borderCapStyle: 'butt',
      borderDash: [],
      borderDashOffset: 0.0,
      borderJoinStyle: 'miter',
      pointBorderColor: 'rgb(255,0,0)',
      pointBackgroundColor: surfaceColor,
      pointBorderWidth: 1,
      pointHoverRadius: 5,
      pointHoverBackgroundColor: 'rgb(255,0,0)',
      pointHoverBorderColor: 'rgba(220,220,220,1)',
      pointHoverBorderWidth: 2,
      pointRadius: 4,
      pointHitRadius: 10,
      data: props.expenses.map((expense) => expense / 100),
    },
    {
      label: 'Net Income',
      fill: false,
      tension: 0.3,
      backgroundColor: 'rgba(236, 235, 249)',
      borderColor: 'rgba(88, 81, 216, 1)',
      borderCapStyle: 'butt',
      borderDash: [],
      borderDashOffset: 0.0,
      borderJoinStyle: 'miter',
      pointBorderColor: 'rgba(88, 81, 216, 1)',
      pointBackgroundColor: surfaceColor,
      pointBorderWidth: 1,
      pointHoverRadius: 5,
      pointHoverBackgroundColor: 'rgba(88, 81, 216, 1)',
      pointHoverBorderColor: 'rgba(220,220,220,1)',
      pointHoverBorderWidth: 2,
      pointRadius: 4,
      pointHitRadius: 10,
      data: props.income.map((i) => i / 100),
    },
  ]

  const config: ChartConfiguration<'line', number[], string> = {
    type: 'line',
    data: {
      labels: props.labels,
      datasets,
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        x: {
          grid: { color: gridColor },
          ticks: { color: tickColor },
        },
        y: {
          grid: { color: gridColor },
          ticks: { color: tickColor },
        },
      },
      plugins: {
        tooltip: {
          enabled: true,
          callbacks: {
            label: function (tooltipContext) {
              if (!utils) return ''
              return utils.formatMoney(
                Math.round(tooltipContext.parsed.y * 100),
                defaultCurrency.value
              )
            },
          },
        },
        legend: {
          display: false,
        },
      },
    },
  }

  myLineChart = new Chart(context, config)
})

function update(): void {
  if (!myLineChart) return
  myLineChart.data.labels = props.labels
  myLineChart.data.datasets[0].data = props.invoices.map(
    (invoice) => invoice / 100
  )
  myLineChart.data.datasets[1].data = props.receipts.map(
    (receipt) => receipt / 100
  )
  myLineChart.data.datasets[2].data = props.expenses.map(
    (expense) => expense / 100
  )
  myLineChart.data.datasets[3].data = props.income.map((i) => i / 100)
  myLineChart.update('none')
}
</script>
