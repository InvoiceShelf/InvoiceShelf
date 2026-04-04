import { client } from '../client'
import { API } from '../endpoints'
import type { CustomField } from '../../types/domain/custom-field'
import type { ApiResponse, ListParams } from '../../types/api'

export interface CustomFieldListParams extends ListParams {
  model_type?: string
  type?: string
}

export interface CreateCustomFieldPayload {
  name: string
  label: string
  model_type: string
  type: string
  placeholder?: string | null
  is_required?: boolean
  options?: Array<{ name: string }> | string[] | null
  order?: number | null
}

export const customFieldService = {
  async list(params?: CustomFieldListParams): Promise<ApiResponse<CustomField[]>> {
    const { data } = await client.get(API.CUSTOM_FIELDS, { params })
    return data
  },

  async get(id: number): Promise<ApiResponse<CustomField>> {
    const { data } = await client.get(`${API.CUSTOM_FIELDS}/${id}`)
    return data
  },

  async create(payload: CreateCustomFieldPayload): Promise<ApiResponse<CustomField>> {
    const { data } = await client.post(API.CUSTOM_FIELDS, payload)
    return data
  },

  async update(id: number, payload: Partial<CreateCustomFieldPayload>): Promise<ApiResponse<CustomField>> {
    const { data } = await client.put(`${API.CUSTOM_FIELDS}/${id}`, payload)
    return data
  },

  async delete(id: number): Promise<{ success: boolean; error?: string }> {
    const { data } = await client.delete(`${API.CUSTOM_FIELDS}/${id}`)
    return data
  },
}
