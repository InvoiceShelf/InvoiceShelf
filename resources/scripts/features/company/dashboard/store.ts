import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { dashboardService } from '../../../api/services/dashboard.service'
import type { DashboardParams, DashboardResponse, ChartData, ReceivablesSummary, ResolvedPeriod } from '../../../api/services/dashboard.service'
import type { PeriodValue } from '../../../utils/period'
import type { Invoice } from '../../../types/domain/invoice'
import type { Estimate } from '../../../types/domain/estimate'
import { handleApiError } from '../../../utils/error-handling'

export interface DashboardStats {
  totalAmountDue: number
  totalCustomerCount: number
  totalInvoiceCount: number
  totalEstimateCount: number
}

export interface DashboardChartData {
  months: string[]
  invoiceTotals: number[]
  expenseTotals: number[]
  receiptTotals: number[]
  netIncomeTotals: number[]
}

export interface DueInvoice {
  id: number
  invoice_number: string
  due_amount: number
  formattedDueDate: string
  formatted_due_date: string
  customer: {
    id: number
    name: string
    currency?: {
      id: number
      code: string
      symbol: string
    }
  }
}

export interface RecentEstimate {
  id: number
  estimate_number: string
  total: number
  status: string
  formattedEstimateDate: string
  formatted_estimate_date: string
  customer: {
    id: number
    name: string
    currency?: {
      id: number
      code: string
      symbol: string
    }
  }
}

export const useDashboardStore = defineStore('dashboard', () => {
  // State
  const stats = ref<DashboardStats>({
    totalAmountDue: 0,
    totalCustomerCount: 0,
    totalInvoiceCount: 0,
    totalEstimateCount: 0,
  })

  const chartData = ref<DashboardChartData>({
    months: [],
    invoiceTotals: [],
    expenseTotals: [],
    receiptTotals: [],
    netIncomeTotals: [],
  })

  const receivables = ref<ReceivablesSummary>({
    outstanding: 0,
    outstanding_count: 0,
    overdue: 0,
    overdue_count: 0,
    due_soon: 0,
    due_later: 0,
  })

  const totalSales = ref<number>(0)
  const totalReceipts = ref<number>(0)
  const totalExpenses = ref<number>(0)
  const totalNetIncome = ref<number>(0)

  const recentDueInvoices = ref<DueInvoice[]>([])
  const recentEstimates = ref<RecentEstimate[]>([])

  const isDashboardDataLoaded = ref<boolean>(false)
  // The last load failed; the view offers to try again
  const loadError = ref<boolean>(false)
  // Only the latest request may fill the dashboard, when the period changes fast
  let latestRequest = 0

  // The period picked on the dashboard, kept while the app is open, and the
  // dates the server resolved it to
  const period = ref<PeriodValue>({ preset: 'this_year' })
  const resolvedPeriod = ref<ResolvedPeriod | null>(null)

  // Actions
  async function loadData(params?: DashboardParams): Promise<DashboardResponse | null> {
    const request = ++latestRequest
    loadError.value = false

    try {
      const response = await dashboardService.load(params)

      if (request !== latestRequest) {
        return null
      }

      // Stats
      stats.value.totalAmountDue = response.total_amount_due ?? 0
      stats.value.totalCustomerCount = response.total_customer_count ?? 0
      stats.value.totalInvoiceCount = response.total_invoice_count ?? 0
      stats.value.totalEstimateCount = response.total_estimate_count ?? 0

      if (response.receivables) {
        receivables.value = response.receivables
      }

      // Chart Data
      if (response.chart_data) {
        chartData.value.months = response.chart_data.months
        chartData.value.invoiceTotals = response.chart_data.invoice_totals
        chartData.value.expenseTotals = response.chart_data.expense_totals
        chartData.value.receiptTotals = response.chart_data.receipt_totals
        chartData.value.netIncomeTotals = response.chart_data.net_income_totals
      }

      resolvedPeriod.value = response.period ?? null

      // Chart Labels
      totalSales.value = Number(response.total_sales) || 0
      totalReceipts.value = Number(response.total_receipts) || 0
      totalExpenses.value = Number(response.total_expenses) || 0
      totalNetIncome.value = Number(response.total_net_income) || 0

      // Table Data
      recentDueInvoices.value = (response.recent_due_invoices ?? []) as unknown as DueInvoice[]
      recentEstimates.value = (response.recent_estimates ?? []) as unknown as RecentEstimate[]

      isDashboardDataLoaded.value = true

      return response
    } catch (err: unknown) {
      handleApiError(err)

      if (request === latestRequest) {
        loadError.value = true
      }

      return null
    }
  }

  return {
    stats,
    receivables,
    chartData,
    totalSales,
    totalReceipts,
    totalExpenses,
    totalNetIncome,
    recentDueInvoices,
    recentEstimates,
    isDashboardDataLoaded,
    loadError,
    period,
    resolvedPeriod,
    loadData,
  }
})
