import { client } from '../client'
import { API } from '../endpoints'
import { mapProposalFields, type ProposalKind } from './proposal-fields'
import type {
  Estimate,
  CreateEstimatePayload,
} from '@/scripts/types/domain/estimate'
import type { Invoice } from '@/scripts/types/domain/invoice'
import type {
  ApiResponse,
  ListParams,
  DateRangeParams,
  NextNumberResponse,
  DeletePayload,
} from '@/scripts/types/api'

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

export function createProposalService(kind: ProposalKind) {
  const base = `/api/v1/${kind}s`
  const wire = <T>(value: T): T => mapProposalFields(value, 'estimate', kind)
  const read = <T>(value: T): T => mapProposalFields(value, kind, 'estimate')
  return {
    async list(params?: EstimateListParams): Promise<EstimateListResponse> {
      const { data } = await client.get(base, { params: wire(params) })
      return read(data)
    },

    async get(id: number): Promise<ApiResponse<Estimate>> {
      const { data } = await client.get(`${base}/${id}`)
      return read(data)
    },

    async create(
      payload: CreateEstimatePayload,
    ): Promise<ApiResponse<Estimate>> {
      const { data } = await client.post(base, wire(payload))
      return read(data)
    },

    async update(
      id: number,
      payload: Partial<CreateEstimatePayload>,
    ): Promise<ApiResponse<Estimate>> {
      const { data } = await client.put(`${base}/${id}`, wire(payload))
      return read(data)
    },

    async delete(payload: DeletePayload): Promise<{ success: boolean }> {
      const { data } = await client.post(`${base}/delete`, wire(payload))
      return read(data)
    },

    async send(payload: SendEstimatePayload): Promise<ApiResponse<Estimate>> {
      const { data } = await client.post(
        `${base}/${payload.id}/send`,
        wire(payload),
      )
      return read(data)
    },

    async sendPreview(
      id: number,
      params?: Record<string, unknown>,
    ): Promise<ApiResponse<string>> {
      const { data } = await client.get(`${base}/${id}/send/preview`, {
        params: wire(params),
      })
      return read(data)
    },

    async clone(id: number): Promise<ApiResponse<Estimate>> {
      const { data } = await client.post(`${base}/${id}/clone`)
      return read(data)
    },

    async changeStatus(
      payload: EstimateStatusPayload,
    ): Promise<ApiResponse<Estimate>> {
      const { data } = await client.post(
        `${base}/${payload.id}/status`,
        wire(payload),
      )
      return read(data)
    },

    async convertToInvoice(id: number): Promise<ApiResponse<Invoice>> {
      const { data } = await client.post(`${base}/${id}/convert-to-invoice`)
      return read(data)
    },

    async getNextNumber(params?: {
      key?: string
    }): Promise<NextNumberResponse> {
      const { data } = await client.get(API.NEXT_NUMBER, {
        params: { ...params, key: kind },
      })
      return read(data)
    },

    async getTemplates(): Promise<EstimateTemplatesResponse> {
      const { data } = await client.get(`${base}/templates`)
      return read(data)
    },
  }
}

export const estimateService = createProposalService('estimate')
export const quoteService = createProposalService('quote')
