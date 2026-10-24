import { client } from '../client'
import { API } from '../endpoints'
import type {
  TaxCategoryCode,
  TaxType,
  TaxTypeTransactionType,
} from '@/scripts/types/domain/tax'
import type {
  ApiResponse,
  ListParams,
  PaginationMeta,
} from '@/scripts/types/api'

export interface TaxTypeListParams extends ListParams {
  transaction_type?: TaxTypeTransactionType
}

export interface TaxTypeListResponse {
  data: TaxType[]
  meta: PaginationMeta
}

export interface CreateTaxTypePayload {
  name: string
  percent: number
  fixed_amount?: number
  calculation_type?: string | null
  transaction_type: TaxTypeTransactionType
  compound_tax?: boolean
  collective_tax?: number | null
  tax_category_code?: TaxCategoryCode
  tax_exemption_reason?: string | null
  description?: string | null
}

export const taxTypeService = {
  async list(params?: TaxTypeListParams): Promise<TaxTypeListResponse> {
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
