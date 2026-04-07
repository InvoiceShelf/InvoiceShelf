import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { dashboardService } from '../../../api/services/dashboard.service'
import type { DashboardParams, DashboardResponse, ChartData } from '../../../api/services/dashboard.service'
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

  const totalSales = ref<number>(0)
  const totalReceipts = ref<number>(0)
  const totalExpenses = ref<number>(0)
  const totalNetIncome = ref<number>(0)

  const recentDueInvoices = ref<DueInvoice[]>([])
  const recentEstimates = ref<RecentEstimate[]>([])

  const isDashboardDataLoaded = ref<boolean>(false)

  // Actions
  async function loadData(params?: DashboardParams): Promise<DashboardResponse> {
    try {
      const response = await dashboardService.load(params)

      // Stats
      stats.value.totalAmountDue = response.total_amount_due
      stats.value.totalCustomerCount = response.total_customer_count
      stats.value.totalInvoiceCount = response.total_invoice_count
      stats.value.totalEstimateCount = response.total_estimate_count

      // Chart Data
      if (response.chart_data) {
        chartData.value.months = response.chart_data.months
        chartData.value.invoiceTotals = response.chart_data.invoice_totals
        chartData.value.expenseTotals = response.chart_data.expense_totals
        chartData.value.receiptTotals = response.chart_data.receipt_totals
        chartData.value.netIncomeTotals = response.chart_data.net_income_totals
      }

      // Chart Labels
      totalSales.value = Number(response.total_sales) || 0
      totalReceipts.value = Number(response.total_receipts) || 0
      totalExpenses.value = Number(response.total_expenses) || 0
      totalNetIncome.value = Number(response.total_net_income) || 0

      // Table Data
      recentDueInvoices.value = response.recent_due_invoices as unknown as DueInvoice[]
      recentEstimates.value = response.recent_estimates as unknown as RecentEstimate[]

      isDashboardDataLoaded.value = true

      return response
    } catch (err: unknown) {
      handleApiError(err)
      throw err
    }
  }

  return {
    stats,
    chartData,
    totalSales,
    totalReceipts,
    totalExpenses,
    totalNetIncome,
    recentDueInvoices,
    recentEstimates,
    isDashboardDataLoaded,
    loadData,
  }
})
