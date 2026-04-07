import { client } from '../client'
import { API } from '../endpoints'
import type { Role, Ability } from '@/scripts/types/domain/role'
import type { ApiResponse, ListParams } from '@/scripts/types/api'

export interface CreateRolePayload {
  name: string
  abilities: Array<{
    ability: string
    model?: string | null
  }>
}

export interface AbilitiesResponse {
  abilities: Ability[]
}

export const roleService = {
  async list(params?: ListParams): Promise<ApiResponse<Role[]>> {
    const { data } = await client.get(API.ROLES, { params })
    return data
  },

  async get(id: number): Promise<ApiResponse<Role>> {
    const { data } = await client.get(`${API.ROLES}/${id}`)
    return data
  },

  async create(payload: CreateRolePayload): Promise<{ role: Role }> {
    const { data } = await client.post(API.ROLES, payload)
    return data
  },

  async update(id: number, payload: Partial<CreateRolePayload>): Promise<ApiResponse<Role>> {
    const { data } = await client.put(`${API.ROLES}/${id}`, payload)
    return data
  },

  async delete(id: number): Promise<{ success: boolean }> {
    const { data } = await client.delete(`${API.ROLES}/${id}`)
    return data
  },

  async getAbilities(params?: ListParams): Promise<AbilitiesResponse> {
    const { data } = await client.get(API.ABILITIES, { params })
    return data
  },
}
