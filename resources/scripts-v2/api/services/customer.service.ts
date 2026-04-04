import { client } from '../client'
import { API } from '../endpoints'
import type { Customer, CreateCustomerPayload } from '../../types/domain/customer'
import type {
  ApiResponse,
  ListParams,
  DeletePayload,
} from '../../types/api'

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

export interface CustomerStatsData {
  id: number
  name: string
  email: string | null
  total_invoices: number
  total_estimates: number
  total_payments: number
  total_expenses: number
  total_amount_due: number
  total_paid: number
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

  async getStats(id: number, params?: Record<string, unknown>): Promise<ApiResponse<CustomerStatsData>> {
    const { data } = await client.get(`${API.CUSTOMER_STATS}/${id}/stats`, { params })
    return data
  },
}
