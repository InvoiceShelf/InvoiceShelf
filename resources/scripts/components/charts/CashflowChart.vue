<template>
  <div>
    <div class="relative" :style="{ height: `${height}px` }">
      <canvas ref="canvas" role="img" :aria-label="ariaLabel" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { Chart } from 'chart.js/auto'
import type { ChartConfiguration, TooltipItem } from 'chart.js/auto'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useMutationObserver } from '@vueuse/core'
import { formatMoney } from '@/scripts/utils/format-money'
import type { CurrencyConfig } from '@/scripts/utils/format-money'

/**
 * Money in and out by month on one axis: what was invoiced and what was
 * collected as paired columns, what was spent as a line over them. Colours
 * come from the --color-chart-* tokens and are read again whenever the theme
 * or accent on <html> changes.
 */
interface Props {
  labels: string[]
  sales: number[]
  receipts: number[]
  expenses: number[]
  seriesLabels: [string, string, string]
  currency?: CurrencyConfig | null
  height?: number
  ariaLabel?: string
}

const props = withDefaults(defineProps<Props>(), {
  currency: null,
  height: 280,
  ariaLabel: '',
})

const canvas = ref<HTMLCanvasElement | null>(null)
let chart: Chart | null = null

const currencyConfig = computed<CurrencyConfig | undefined>(() => props.currency ?? undefined)

function token(name: string): string {
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim()
}

function compact(amountInCents: number): string {
  return new Intl.NumberFormat(undefined, {
    notation: 'compact',
    maximumFractionDigits: 1,
  }).format(amountInCents / 100)
}

function buildConfig(): ChartConfiguration<'bar' | 'line'> {
  const surface = token('--color-surface')
  const grid = token('--color-line-light')
  const axis = token('--color-muted')
  const heading = token('--color-heading')
  const body = token('--color-body')
  const border = token('--color-line-default')
  const font = { family: token('--font-base') || 'sans-serif', size: 12 }

  const bar = {
    type: 'bar' as const,
    borderRadius: { topLeft: 4, topRight: 4 },
    borderSkipped: 'start' as const,
    maxBarThickness: 16,
    categoryPercentage: 0.62,
    barPercentage: 0.88,
  }

  return {
    type: 'bar',
    data: {
      labels: props.labels,
      datasets: [
        {
          ...bar,
          label: props.seriesLabels[0],
          data: props.sales,
          backgroundColor: token('--color-chart-1'),
          order: 2,
        },
        {
          ...bar,
          label: props.seriesLabels[1],
          data: props.receipts,
          backgroundColor: token('--color-chart-2'),
          order: 2,
        },
        {
          type: 'line' as const,
          label: props.seriesLabels[2],
          data: props.expenses,
          borderColor: token('--color-chart-3'),
          backgroundColor: token('--color-chart-3'),
          borderWidth: 2,
          tension: 0.3,
          pointRadius: 0,
          pointHoverRadius: 5,
          pointHoverBorderWidth: 2,
          pointHoverBorderColor: surface,
          borderCapStyle: 'round' as const,
          borderJoinStyle: 'round' as const,
          order: 1,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 250 },
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: surface,
          borderColor: border,
          borderWidth: 1,
          titleColor: heading,
          bodyColor: body,
          titleFont: { ...font, weight: 600 },
          bodyFont: font,
          padding: 10,
          cornerRadius: 10,
          boxWidth: 10,
          boxHeight: 2,
          boxPadding: 6,
          caretSize: 0,
          callbacks: {
            // The value leads; the series name follows
            label: (item: TooltipItem<'bar' | 'line'>) =>
              `${formatMoney(Number(item.raw) || 0, currencyConfig.value)}  ${item.dataset.label ?? ''}`,
          },
        },
      },
      scales: {
        x: {
          grid: { display: false },
          border: { color: grid },
          ticks: { color: axis, font, maxRotation: 0, autoSkipPadding: 8 },
        },
        y: {
          beginAtZero: true,
          grid: { color: grid, drawTicks: false },
          border: { display: false },
          ticks: {
            color: axis,
            font,
            padding: 8,
            maxTicksLimit: 5,
            callback: (value: string | number) => compact(Number(value)),
          },
        },
      },
    },
  }
}

function render(): void {
  if (!canvas.value) {
    return
  }

  chart?.destroy()
  chart = new Chart(canvas.value, buildConfig() as ChartConfiguration)
}

onMounted(render)

watch(
  () => [props.labels, props.sales, props.receipts, props.expenses, props.seriesLabels, props.currency],
  render,
  { deep: true },
)

// Theme and accent switches repaint with the new token values
useMutationObserver(
  document.documentElement,
  () => render(),
  { attributes: true, attributeFilter: ['data-theme', 'data-accent'] },
)

onBeforeUnmount(() => {
  chart?.destroy()
  chart = null
})
</script>
