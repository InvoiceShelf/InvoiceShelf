import { client } from '../client'
import { API } from '../endpoints'
import type { TaxType } from '@v2/types/domain/tax'
import type { ApiResponse, ListParams } from '@v2/types/api'

export interface CreateTaxTypePayload {
  name: string
  percent: number
  fixed_amount?: number
  calculation_type?: string | null
  compound_tax?: boolean
  collective_tax?: number | null
  description?: string | null
}

export const taxTypeService = {
  async list(params?: ListParams): Promise<ApiResponse<TaxType[]>> {
    const { data } = await client.get(API.TAX_TYPES, { params })
    return data
  },

  async get(id: number): Promise<ApiResponse<TaxType>> {
    const { data } = await client.get(`${API.TAX_TYPES}/${id}`)
    return data
  },

  async create(payload: CreateTaxTypePayload): Promise<ApiResponse<TaxType>> {
    const { data } = await client.post(API.TAX_TYPES, payload)
    return data
  },

  async update(id: number, payload: Partial<CreateTaxTypePayload>): Promise<ApiResponse<TaxType>> {
    const { data } = await client.put(`${API.TAX_TYPES}/${id}`, payload)
    return data
  },

  async delete(id: number): Promise<{ success: boolean }> {
    const { data } = await client.delete(`${API.TAX_TYPES}/${id}`)
    return data
  },
}
