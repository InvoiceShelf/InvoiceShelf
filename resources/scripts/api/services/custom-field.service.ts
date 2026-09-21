import { client } from '../client'
import { API } from '../endpoints'
import type { CustomField } from '@/scripts/types/domain/custom-field'
import type { ApiResponse, ListParams } from '@/scripts/types/api'

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
  options?: string[]
  order?: number | null
  default_answer?: string | null
}

/** One model a custom field can be attached to, as the catalogue serves it. */
export interface CustomFieldModelOption {
  value: string
  label: string
}

export const customFieldService = {
  /**
   * The models a field can be attached to. Assembled on the server from the
   * built-in list plus anything a module registered, so the editor does not
   * hardcode it and cannot drift from what validation accepts.
   */
  async modelTypes(): Promise<CustomFieldModelOption[]> {
    const { data } = await client.get(API.CONFIG, {
      params: { key: 'custom_field_models' },
    })

    return data.custom_field_models ?? []
  },

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
