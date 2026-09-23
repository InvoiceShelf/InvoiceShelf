<template>
  <div class="relative" :style="{ height: `${height}px` }">
    <canvas ref="canvas" role="img" :aria-label="ariaLabel" />
  </div>
</template>

<script setup lang="ts">
import { Chart } from 'chart.js/auto'
import type { ChartConfiguration, ChartDataset, Plugin, ScriptableContext, TooltipItem } from 'chart.js/auto'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useMutationObserver } from '@vueuse/core'
import { formatMoney } from '@/scripts/utils/format-money'
import { prefersReducedMotion } from '@/scripts/utils/motion'
import { isRtl } from '@/scripts/utils/direction'
import type { CurrencyConfig } from '@/scripts/utils/format-money'

/**
 * Money in and out by month on one axis: invoiced, collected and spent.
 *
 * - `area`: each series as a 2px line over a soft wash of its own colour,
 *   with a hairline that follows the pointer to the nearest month.
 * - `bars`: the three series as grouped columns.
 *
 * Colours come from the --color-chart-* tokens and are read again whenever
 * the theme on <html> changes.
 */
export type CashflowChartType = 'area' | 'bars'

interface Props {
  labels: string[]
  sales: number[]
  receipts: number[]
  expenses: number[]
  seriesLabels: [string, string, string]
  type?: CashflowChartType
  currency?: CurrencyConfig | null
  height?: number
  ariaLabel?: string
}

const props = withDefaults(defineProps<Props>(), {
  type: 'area',
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

let colorProbe: CanvasRenderingContext2D | null = null

// Canvas colour stops take rgba() reliably; let the canvas itself parse the
// token into #rrggbb first, then add the alpha.
function withAlpha(color: string, alpha: number): string {
  colorProbe ??= document.createElement('canvas').getContext('2d')

  if (!colorProbe) {
    return color
  }

  colorProbe.fillStyle = '#000000'
  colorProbe.fillStyle = color
  const hex = String(colorProbe.fillStyle)
  const match = /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex)

  if (!match) {
    return color
  }

  const [r, g, b] = match.slice(1).map((part) => parseInt(part, 16))

  return `rgba(${r}, ${g}, ${b}, ${alpha})`
}

function compact(amountInCents: number): string {
  return new Intl.NumberFormat(undefined, {
    notation: 'compact',
    maximumFractionDigits: 1,
  }).format(amountInCents / 100)
}

// A vertical hairline at the hovered month, so the reader aims at a date
// rather than at a 2px line.
const crosshair: Plugin<'line' | 'bar'> = {
  id: 'cashflowCrosshair',
  afterDatasetsDraw(instance) {
    const active = instance.tooltip?.getActiveElements?.() ?? []

    if (props.type !== 'area' || active.length === 0) {
      return
    }

    const { ctx, chartArea } = instance
    const x = active[0].element.x

    ctx.save()
    ctx.beginPath()
    ctx.moveTo(x, chartArea.top)
    ctx.lineTo(x, chartArea.bottom)
    ctx.lineWidth = 1
    ctx.strokeStyle = token('--color-line-strong')
    ctx.stroke()
    ctx.restore()
  },
}

// A wash that fades from the line down to the axis
function areaFill(color: string) {
  return (context: ScriptableContext<'line'>): CanvasGradient | string => {
    const { ctx, chartArea } = context.chart

    if (!chartArea) {
      return withAlpha(color, 0.12)
    }

    const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom)
    gradient.addColorStop(0, withAlpha(color, 0.28))
    gradient.addColorStop(1, withAlpha(color, 0))

    return gradient
  }
}

function datasets(colors: [string, string, string], surface: string): ChartDataset<'line' | 'bar'>[] {
  const series: Array<[string, number[], string]> = [
    [props.seriesLabels[0], props.sales, colors[0]],
    [props.seriesLabels[1], props.receipts, colors[1]],
    [props.seriesLabels[2], props.expenses, colors[2]],
  ]

  if (props.type === 'bars') {
    return series.map(([label, data, color]) => ({
      type: 'bar' as const,
      label,
      data,
      backgroundColor: color,
      hoverBackgroundColor: color,
      borderRadius: { topLeft: 4, topRight: 4 },
      borderSkipped: 'start' as const,
      maxBarThickness: 14,
      categoryPercentage: 0.66,
      barPercentage: 0.86,
    }))
  }

  return series.map(([label, data, color]) => ({
    type: 'line' as const,
    label,
    data,
    borderColor: color,
    backgroundColor: areaFill(color),
    fill: 'origin',
    borderWidth: 2,
    tension: 0.35,
    pointRadius: 0,
    pointHoverRadius: 5,
    pointHoverBackgroundColor: color,
    pointHoverBorderColor: surface,
    pointHoverBorderWidth: 2,
    borderCapStyle: 'round' as const,
    borderJoinStyle: 'round' as const,
  }))
}

function buildConfig(): ChartConfiguration<'line' | 'bar'> {
  const surface = token('--color-surface')
  const grid = token('--color-line-light')
  const axis = token('--color-muted')
  const heading = token('--color-heading')
  const body = token('--color-body')
  const border = token('--color-line-default')
  const font = { family: token('--font-base') || 'sans-serif', size: 12 }
  const colors: [string, string, string] = [
    token('--color-chart-1'),
    token('--color-chart-2'),
    token('--color-chart-3'),
  ]

  return {
    type: props.type === 'bars' ? 'bar' : 'line',
    data: {
      labels: props.labels,
      datasets: datasets(colors, surface),
    },
    plugins: [crosshair],
    options: {
      responsive: true,
      maintainAspectRatio: false,
      // No animated draw-in for people who asked their system for less motion
      animation: prefersReducedMotion() ? false : { duration: 300 },
      interaction: { mode: 'index', intersect: false },
      // A reversed axis ends at the left edge, where nothing pads its last label
      layout: { padding: isRtl.value ? { left: 12 } : 0 },
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
          boxHeight: props.type === 'bars' ? 10 : 2,
          boxPadding: 6,
          caretSize: 0,
          usePointStyle: false,
          rtl: isRtl.value,
          textDirection: isRtl.value ? 'rtl' : 'ltr',
          callbacks: {
            // The value leads; the series name follows
            label: (item: TooltipItem<'line' | 'bar'>) =>
              `${formatMoney(Number(item.raw) || 0, currencyConfig.value)}  ${item.dataset.label ?? ''}`,
            labelColor: (item: TooltipItem<'line' | 'bar'>) => ({
              borderColor: colors[item.datasetIndex],
              backgroundColor: colors[item.datasetIndex],
              borderWidth: 0,
            }),
          },
        },
      },
      scales: {
        // Right to left, months run from the right and the values sit there
        x: {
          reverse: isRtl.value,
          grid: { display: false },
          border: { color: grid },
          ticks: { color: axis, font, maxRotation: 0, autoSkipPadding: 8 },
        },
        y: {
          position: isRtl.value ? 'right' : 'left',
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
  () => [props.labels, props.sales, props.receipts, props.expenses, props.seriesLabels, props.currency, props.type],
  render,
  { deep: true },
)

// Theme and direction switches repaint with the new tokens and axes
useMutationObserver(
  document.documentElement,
  () => render(),
  { attributes: true, attributeFilter: ['data-theme', 'dir'] },
)

onBeforeUnmount(() => {
  chart?.destroy()
  chart = null
})
</script>
