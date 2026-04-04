import { client } from '../client'
import { API } from '../endpoints'
import type { Item, Unit } from '@v2/types/domain/item'
import type {
  ApiResponse,
  ListParams,
  DeletePayload,
} from '@v2/types/api'

export interface ItemListParams extends ListParams {
  filter?: Record<string, unknown>
}

export interface ItemListMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  item_total_count: number
}

export interface ItemListResponse {
  data: Item[]
  meta: ItemListMeta
}

export interface CreateItemPayload {
  name: string
  description?: string | null
  price: number
  unit_id?: number | null
  taxes?: Array<{ tax_type_id: number }>
}

export interface CreateUnitPayload {
  name: string
}

export const itemService = {
  async list(params?: ItemListParams): Promise<ItemListResponse> {
    const { data } = await client.get(API.ITEMS, { params })
    return data
  },

  async get(id: number): Promise<ApiResponse<Item>> {
    const { data } = await client.get(`${API.ITEMS}/${id}`)
    return data
  },

  async create(payload: CreateItemPayload): Promise<ApiResponse<Item>> {
    const { data } = await client.post(API.ITEMS, payload)
    return data
  },

  async update(id: number, payload: Partial<CreateItemPayload>): Promise<ApiResponse<Item>> {
    const { data } = await client.put(`${API.ITEMS}/${id}`, payload)
    return data
  },

  async delete(payload: DeletePayload): Promise<{ success: boolean }> {
    const { data } = await client.post(API.ITEMS_DELETE, payload)
    return data
  },

  // Units
  async listUnits(params?: ListParams): Promise<ApiResponse<Unit[]>> {
    const { data } = await client.get(API.UNITS, { params })
    return data
  },

  async getUnit(id: number): Promise<ApiResponse<Unit>> {
    const { data } = await client.get(`${API.UNITS}/${id}`)
    return data
  },

  async createUnit(payload: CreateUnitPayload): Promise<ApiResponse<Unit>> {
    const { data } = await client.post(API.UNITS, payload)
    return data
  },

  async updateUnit(id: number, payload: CreateUnitPayload): Promise<ApiResponse<Unit>> {
    const { data } = await client.put(`${API.UNITS}/${id}`, payload)
    return data
  },

  async deleteUnit(id: number): Promise<{ success: boolean }> {
    const { data } = await client.delete(`${API.UNITS}/${id}`)
    return data
  },
}
