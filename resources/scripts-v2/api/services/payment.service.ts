import { client } from '../client'
import { API } from '../endpoints'
import type { Payment, PaymentMethod, CreatePaymentPayload } from '../../types/domain/payment'
import type {
  ApiResponse,
  ListParams,
  NextNumberResponse,
  DeletePayload,
} from '../../types/api'

export interface PaymentListParams extends ListParams {
  customer_id?: number
  from_date?: string
  to_date?: string
}

export interface PaymentListMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  payment_total_count: number
}

export interface PaymentListResponse {
  data: Payment[]
  meta: PaymentListMeta
}

export interface SendPaymentPayload {
  id: number
  subject?: string
  body?: string
  from?: string
  to?: string
}

export interface CreatePaymentMethodPayload {
  name: string
}

export const paymentService = {
  async list(params?: PaymentListParams): Promise<PaymentListResponse> {
    const { data } = await client.get(API.PAYMENTS, { params })
    return data
  },

  async get(id: number): Promise<ApiResponse<Payment>> {
    const { data } = await client.get(`${API.PAYMENTS}/${id}`)
    return data
  },

  async create(payload: CreatePaymentPayload): Promise<ApiResponse<Payment>> {
    const { data } = await client.post(API.PAYMENTS, payload)
    return data
  },

  async update(id: number, payload: Partial<CreatePaymentPayload>): Promise<ApiResponse<Payment>> {
    const { data } = await client.put(`${API.PAYMENTS}/${id}`, payload)
    return data
  },

  async delete(payload: DeletePayload): Promise<{ success: boolean }> {
    const { data } = await client.post(API.PAYMENTS_DELETE, payload)
    return data
  },

  async send(payload: SendPaymentPayload): Promise<ApiResponse<Payment>> {
    const { data } = await client.post(`${API.PAYMENTS}/${payload.id}/send`, payload)
    return data
  },

  async sendPreview(id: number, params?: Record<string, unknown>): Promise<ApiResponse<string>> {
    const { data } = await client.get(`${API.PAYMENTS}/${id}/send/preview`, { params })
    return data
  },

  async getNextNumber(params?: { key?: string }): Promise<NextNumberResponse> {
    const { data } = await client.get(API.NEXT_NUMBER, { params: { key: 'payment', ...params } })
    return data
  },

  // Payment Methods
  async listMethods(params?: ListParams): Promise<ApiResponse<PaymentMethod[]>> {
    const { data } = await client.get(API.PAYMENT_METHODS, { params })
    return data
  },

  async getMethod(id: number): Promise<ApiResponse<PaymentMethod>> {
    const { data } = await client.get(`${API.PAYMENT_METHODS}/${id}`)
    return data
  },

  async createMethod(payload: CreatePaymentMethodPayload): Promise<ApiResponse<PaymentMethod>> {
    const { data } = await client.post(API.PAYMENT_METHODS, payload)
    return data
  },

  async updateMethod(id: number, payload: CreatePaymentMethodPayload): Promise<ApiResponse<PaymentMethod>> {
    const { data } = await client.put(`${API.PAYMENT_METHODS}/${id}`, payload)
    return data
  },

  async deleteMethod(id: number): Promise<{ success: boolean }> {
    const { data } = await client.delete(`${API.PAYMENT_METHODS}/${id}`)
    return data
  },
}
