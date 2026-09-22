import { client } from '../client'
import { API } from '../endpoints'

export interface DashboardParams {
  previous_year?: number
  from_date?: string
  to_date?: string
}

/** The dates the money chart covers, and whether it counts by day or month */
export interface ResolvedPeriod {
  from: string
  to: string
  granularity: 'day' | 'month'
}

export interface ChartData {
  months: string[]
  invoice_totals: number[]
  expense_totals: number[]
  receipt_totals: number[]
  net_income_totals: number[]
}

export interface ReceivablesSummary {
  outstanding: number
  outstanding_count: number
  overdue: number
  overdue_count: number
  due_soon: number
  due_later: number
}

export interface DashboardResponse {
  total_amount_due: number
  receivables?: ReceivablesSummary
  total_customer_count: number
  total_invoice_count: number
  total_estimate_count: number
  chart_data: ChartData
  total_sales: string
  total_receipts: string
  total_expenses: string
  total_net_income: string
  period?: ResolvedPeriod
  recent_due_invoices: Array<{
    id: number
    invoice_number: string
    due_amount: number
    formatted_due_date: string
    customer?: {
      id: number
      name: string
    }
  }>
  recent_estimates: Array<{
    id: number
    estimate_number: string
    total: number
    status: string
    customer?: {
      id: number
      name: string
    }
  }>
}

export const dashboardService = {
  async load(params?: DashboardParams): Promise<DashboardResponse> {
    const { data } = await client.get(API.DASHBOARD, { params })
    return data
  },
}
