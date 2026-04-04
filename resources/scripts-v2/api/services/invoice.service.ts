import { client } from '../client'
import { API } from '../endpoints'
import type { Invoice, CreateInvoicePayload } from '@v2/types/domain/invoice'
import type {
  ApiResponse,
  PaginatedResponse,
  ListParams,
  DateRangeParams,
  NextNumberResponse,
  DeletePayload,
} from '@v2/types/api'

export interface InvoiceListParams extends ListParams, DateRangeParams {
  status?: string
  customer_id?: number
}

export interface InvoiceListMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  invoice_total_count: number
}

export interface InvoiceListResponse {
  data: Invoice[]
  meta: InvoiceListMeta
}

export interface SendInvoicePayload {
  id: number
  subject?: string
  body?: string
  from?: string
  to?: string
}

export interface InvoiceStatusPayload {
  id: number
  status: string
}

export interface SendPreviewParams {
  id: number
}

export interface InvoiceTemplate {
  name: string
  path: string
}

export interface InvoiceTemplatesResponse {
  invoiceTemplates: InvoiceTemplate[]
}

export const invoiceService = {
  async list(params?: InvoiceListParams): Promise<InvoiceListResponse> {
    const { data } = await client.get(API.INVOICES, { params })
    return data
  },

  async get(id: number): Promise<ApiResponse<Invoice>> {
    const { data } = await client.get(`${API.INVOICES}/${id}`)
    return data
  },

  async create(payload: CreateInvoicePayload): Promise<ApiResponse<Invoice>> {
    const { data } = await client.post(API.INVOICES, payload)
    return data
  },

  async update(id: number, payload: Partial<CreateInvoicePayload>): Promise<ApiResponse<Invoice>> {
    const { data } = await client.put(`${API.INVOICES}/${id}`, payload)
    return data
  },

  async delete(payload: DeletePayload): Promise<{ success: boolean }> {
    const { data } = await client.post(API.INVOICES_DELETE, payload)
    return data
  },

  async send(payload: SendInvoicePayload): Promise<ApiResponse<Invoice>> {
    const { data } = await client.post(`${API.INVOICES}/${payload.id}/send`, payload)
    return data
  },

  async sendPreview(params: SendPreviewParams): Promise<ApiResponse<string>> {
    const { data } = await client.get(`${API.INVOICES}/${params.id}/send/preview`, { params })
    return data
  },

  async clone(id: number): Promise<ApiResponse<Invoice>> {
    const { data } = await client.post(`${API.INVOICES}/${id}/clone`)
    return data
  },

  async changeStatus(payload: InvoiceStatusPayload): Promise<ApiResponse<Invoice>> {
    const { data } = await client.post(`${API.INVOICES}/${payload.id}/status`, payload)
    return data
  },

  async getNextNumber(params?: { key?: string }): Promise<NextNumberResponse> {
    const { data } = await client.get(API.NEXT_NUMBER, { params: { key: 'invoice', ...params } })
    return data
  },

  async getTemplates(): Promise<InvoiceTemplatesResponse> {
    const { data } = await client.get(API.INVOICE_TEMPLATES)
    return data
  },
}
