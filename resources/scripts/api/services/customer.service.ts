import { client } from '../client'
import { API } from '../endpoints'
import type { Customer, CreateCustomerPayload } from '@/scripts/types/domain/customer'
import type {
  ApiResponse,
  ListParams,
  DeletePayload,
} from '@/scripts/types/api'

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
}

export interface CustomerStatsParams {
  previous_year?: boolean
  this_year?: boolean
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
}
