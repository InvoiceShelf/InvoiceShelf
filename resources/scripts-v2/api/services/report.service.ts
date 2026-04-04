import { client } from '../client'
import { API } from '../endpoints'
import type { DateRangeParams } from '@v2/types/api'

export interface ReportParams extends DateRangeParams {
  report_type?: string
}

export interface SalesReportResponse {
  data: Array<{
    date: string
    total: number
    count: number
  }>
  total: number
  from_date: string
  to_date: string
}

export interface ProfitLossReportResponse {
  data: {
    income: Array<{
      label: string
      amount: number
    }>
    expenses: Array<{
      label: string
      amount: number
    }>
    net_profit: number
  }
  from_date: string
  to_date: string
}

export interface ExpenseReportResponse {
  data: Array<{
    category: string
    total: number
    count: number
  }>
  total: number
  from_date: string
  to_date: string
}

export interface TaxReportResponse {
  data: Array<{
    tax_name: string
    tax_amount: number
    invoice_count: number
  }>
  total: number
  from_date: string
  to_date: string
}

export const reportService = {
  async getSalesReport(params: ReportParams): Promise<SalesReportResponse> {
    const { data } = await client.get(API.DASHBOARD, {
      params: { ...params, report_type: 'sales' },
    })
    return data
  },

  async getProfitLossReport(params: ReportParams): Promise<ProfitLossReportResponse> {
    const { data } = await client.get(API.DASHBOARD, {
      params: { ...params, report_type: 'profit_loss' },
    })
    return data
  },

  async getExpenseReport(params: ReportParams): Promise<ExpenseReportResponse> {
    const { data } = await client.get(API.DASHBOARD, {
      params: { ...params, report_type: 'expenses' },
    })
    return data
  },

  async getTaxReport(params: ReportParams): Promise<TaxReportResponse> {
    const { data } = await client.get(API.DASHBOARD, {
      params: { ...params, report_type: 'tax' },
    })
    return data
  },
}
