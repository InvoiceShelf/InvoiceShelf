import { client } from '../client'
import { API } from '../endpoints'
import type { Estimate, CreateEstimatePayload } from '../../types/domain/estimate'
import type { Invoice } from '../../types/domain/invoice'
import type {
  ApiResponse,
  ListParams,
  DateRangeParams,
  NextNumberResponse,
  DeletePayload,
} from '../../types/api'

export interface EstimateListParams extends ListParams, DateRangeParams {
  status?: string
  customer_id?: number
}

export interface EstimateListMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  estimate_total_count: number
}

export interface EstimateListResponse {
  data: Estimate[]
  meta: EstimateListMeta
}

export interface SendEstimatePayload {
  id: number
  subject?: string
  body?: string
  from?: string
  to?: string
  is_preview?: boolean
}

export interface EstimateStatusPayload {
  id: number
  status: string
}

export interface EstimateTemplate {
  name: string
  path: string
}

export interface EstimateTemplatesResponse {
  estimateTemplates: EstimateTemplate[]
}

export const estimateService = {
  async list(params?: EstimateListParams): Promise<EstimateListResponse> {
    const { data } = await client.get(API.ESTIMATES, { params })
    return data
  },

  async get(id: number): Promise<ApiResponse<Estimate>> {
    const { data } = await client.get(`${API.ESTIMATES}/${id}`)
    return data
  },

  async create(payload: CreateEstimatePayload): Promise<ApiResponse<Estimate>> {
    const { data } = await client.post(API.ESTIMATES, payload)
    return data
  },

  async update(id: number, payload: Partial<CreateEstimatePayload>): Promise<ApiResponse<Estimate>> {
    const { data } = await client.put(`${API.ESTIMATES}/${id}`, payload)
    return data
  },

  async delete(payload: DeletePayload): Promise<{ success: boolean }> {
    const { data } = await client.post(API.ESTIMATES_DELETE, payload)
    return data
  },

  async send(payload: SendEstimatePayload): Promise<ApiResponse<Estimate>> {
    const { data } = await client.post(`${API.ESTIMATES}/${payload.id}/send`, payload)
    return data
  },

  async sendPreview(id: number, params?: Record<string, unknown>): Promise<ApiResponse<string>> {
    const { data } = await client.get(`${API.ESTIMATES}/${id}/send/preview`, { params })
    return data
  },

  async clone(id: number): Promise<ApiResponse<Estimate>> {
    const { data } = await client.post(`${API.ESTIMATES}/${id}/clone`)
    return data
  },

  async changeStatus(payload: EstimateStatusPayload): Promise<ApiResponse<Estimate>> {
    const { data } = await client.post(`${API.ESTIMATES}/${payload.id}/status`, payload)
    return data
  },

  async convertToInvoice(id: number): Promise<ApiResponse<Invoice>> {
    const { data } = await client.post(`${API.ESTIMATES}/${id}/convert-to-invoice`)
    return data
  },

  async getNextNumber(params?: { key?: string }): Promise<NextNumberResponse> {
    const { data } = await client.get(API.NEXT_NUMBER, { params: { key: 'estimate', ...params } })
    return data
  },

  async getTemplates(): Promise<EstimateTemplatesResponse> {
    const { data } = await client.get(API.ESTIMATE_TEMPLATES)
    return data
  },
}
