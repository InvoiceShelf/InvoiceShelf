import type { ResolvedPeriod } from './dashboard.service'
import { client } from '../client'
import { API } from '../endpoints'
import type { Customer, CreateCustomerPayload } from '@/scripts/types/domain/customer'
import type {
  ApiResponse,
  ListParams,
  DeletePayload,
} from '@/scripts/types/api'
import type { Currency } from '@/scripts/types/domain/currency'

export type CustomerStatementType = 'activity' | 'outstanding'

export interface CustomerStatementEntry {
  id: string | number
  date: string
  entry_type: 'invoice' | 'credit_note' | 'payment'
  reference: string
  description?: string | null
  debit_amount: number
  credit_amount: number
  balance?: number
}

export interface CustomerStatementOutstandingInvoice {
  id: number
  invoice_number: string
  invoice_date: string
  due_date: string | null
  original_amount: number
  applied_amount: number
  remaining_amount: number
}

export interface CustomerStatementCredit {
  id: number
  payment_number: string
  payment_date: string
  amount: number
  allocated_amount: number
  available_amount: number
}

export interface CustomerStatement {
  type: CustomerStatementType
  customer: Customer
  currency?: Currency
  opening_balance?: number
  closing_balance?: number
  invoice_due_amount?: number
  available_credit?: number
  account_balance?: number
  entries?: CustomerStatementEntry[]
  meta?: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  invoices?: CustomerStatementOutstandingInvoice[]
  credits?: CustomerStatementCredit[]
}

export interface CustomerStatementParams {
  type: CustomerStatementType
  from_date?: string
  to_date?: string
  as_of?: string
  page?: number
}

export interface SendCustomerStatementPayload {
  type: CustomerStatementType
  from_date?: string
  to_date?: string
  as_of?: string
  to?: string
  cc?: string
  bcc?: string
  subject?: string
  body?: string
}

export interface CustomerListParams extends ListParams {
  display_name?: string
}

export interface CustomerListMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  customer_total_count: number
}

export interface CustomerListResponse {
  data: Customer[]
  meta: CustomerListMeta
}

export interface CustomerStatsChartData {
  salesTotal: number
  totalReceipts: number
  totalExpenses: number
  netProfit: number
  expenseTotals: number[]
  netProfits: number[]
  months: string[]
  receiptTotals: number[]
  invoiceTotals: number[]
  period?: ResolvedPeriod
}

export interface CustomerStatsParams {
  previous_year?: boolean | number
  from_date?: string
  to_date?: string
}

export interface CustomerStatsResponse {
  data: Customer
  meta: {
    chartData: CustomerStatsChartData
  }
}

export const customerService = {
  async list(params?: CustomerListParams): Promise<CustomerListResponse> {
    const { data } = await client.get(API.CUSTOMERS, { params })
    return data
  },

  async get(id: number): Promise<ApiResponse<Customer>> {
    const { data } = await client.get(`${API.CUSTOMERS}/${id}`)
    return data
  },

  async create(payload: CreateCustomerPayload): Promise<ApiResponse<Customer>> {
    const { data } = await client.post(API.CUSTOMERS, payload)
    return data
  },

  async update(id: number, payload: Partial<CreateCustomerPayload>): Promise<ApiResponse<Customer>> {
    const { data } = await client.put(`${API.CUSTOMERS}/${id}`, payload)
    return data
  },

  async delete(payload: DeletePayload): Promise<{ success: boolean }> {
    const { data } = await client.post(API.CUSTOMERS_DELETE, payload)
    return data
  },

  async getStats(
    id: number,
    params?: CustomerStatsParams
  ): Promise<CustomerStatsResponse> {
    const { data } = await client.get(`${API.CUSTOMER_STATS}/${id}/stats`, { params })
    return data
  },

  async getStatement(
    id: number,
    params: CustomerStatementParams,
  ): Promise<ApiResponse<CustomerStatement>> {
    const { data } = await client.get(`${API.CUSTOMER_STATEMENT}/${id}/statement`, { params })
    return data
  },

  statementPdfUrl(id: number, params: CustomerStatementParams): string {
    const query = new URLSearchParams(
      Object.entries(params).reduce<Record<string, string>>((result, [key, value]) => {
        if (value !== undefined && value !== null) result[key] = String(value)
        return result
      }, {}),
    )
    query.set('download', '1')

    return `/reports/customers/${id}/statement?${query.toString()}`
  },

  async sendStatement(
    id: number,
    payload: SendCustomerStatementPayload,
  ): Promise<{ success: boolean }> {
    const { data } = await client.post(`${API.CUSTOMER_STATEMENT}/${id}/statement/send`, payload)
    return data
  },

  async allocateCredit(
    id: number,
    allocations: Array<{ payment_id: number; invoice_id: number; amount: number }>,
  ): Promise<{ success: boolean }> {
    const { data } = await client.post(`${API.CUSTOMER_STATEMENT}/${id}/credit-allocations`, { allocations })
    return data
  },
}
