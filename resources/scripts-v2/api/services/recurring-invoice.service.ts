import { client } from '../client'
import { API } from '../endpoints'
import type { RecurringInvoice, CreateRecurringInvoicePayload } from '../../types/domain/recurring-invoice'
import type {
  ApiResponse,
  ListParams,
  DeletePayload,
} from '../../types/api'

export interface RecurringInvoiceListParams extends ListParams {
  status?: string
  customer_id?: number
}

export interface RecurringInvoiceListMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  recurring_invoice_total_count: number
}

export interface RecurringInvoiceListResponse {
  data: RecurringInvoice[]
  meta: RecurringInvoiceListMeta
}

export interface FrequencyDateParams {
  frequency: string
  starts_at?: string
}

export interface FrequencyDateResponse {
  next_invoice_at: string
}

export const recurringInvoiceService = {
  async list(params?: RecurringInvoiceListParams): Promise<RecurringInvoiceListResponse> {
    const { data } = await client.get(API.RECURRING_INVOICES, { params })
    return data
  },

  async get(id: number): Promise<ApiResponse<RecurringInvoice>> {
    const { data } = await client.get(`${API.RECURRING_INVOICES}/${id}`)
    return data
  },

  async create(payload: CreateRecurringInvoicePayload): Promise<ApiResponse<RecurringInvoice>> {
    const { data } = await client.post(API.RECURRING_INVOICES, payload)
    return data
  },

  async update(
    id: number,
    payload: Partial<CreateRecurringInvoicePayload>,
  ): Promise<ApiResponse<RecurringInvoice>> {
    const { data } = await client.put(`${API.RECURRING_INVOICES}/${id}`, payload)
    return data
  },

  async delete(payload: DeletePayload): Promise<{ success: boolean }> {
    const { data } = await client.post(API.RECURRING_INVOICES_DELETE, payload)
    return data
  },

  async getFrequencyDate(params: FrequencyDateParams): Promise<FrequencyDateResponse> {
    const { data } = await client.get(API.RECURRING_INVOICE_FREQUENCY, { params })
    return data
  },
}
