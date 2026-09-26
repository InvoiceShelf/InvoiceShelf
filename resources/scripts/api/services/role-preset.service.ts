import { client } from '../client'
import { API } from '../endpoints'
import type { RolePreset } from '../../types/domain/role'
import type { AbilityDefinition } from '../../features/shared/roles/abilities'

export interface RolePresetPayload {
  title: string
  abilities: string[]
}

/**
 * The super administrator's role presets, and the ability catalogue they are
 * built from. Every change reaches every company.
 */
export const rolePresetService = {
  async list(): Promise<RolePreset[]> {
    const { data } = await client.get<{ data: RolePreset[] }>(API.SUPER_ADMIN_ROLE_PRESETS)
    return data.data
  },

  async create(payload: RolePresetPayload): Promise<RolePreset> {
    const { data } = await client.post<{ data: RolePreset }>(API.SUPER_ADMIN_ROLE_PRESETS, payload)
    return data.data
  },

  async update(id: number, payload: RolePresetPayload): Promise<RolePreset> {
    const { data } = await client.put<{ data: RolePreset }>(`${API.SUPER_ADMIN_ROLE_PRESETS}/${id}`, payload)
    return data.data
  },

  async remove(id: number): Promise<void> {
    await client.delete(`${API.SUPER_ADMIN_ROLE_PRESETS}/${id}`)
  },

  async abilities(): Promise<AbilityDefinition[]> {
    const { data } = await client.get<{ abilities: AbilityDefinition[] }>(API.SUPER_ADMIN_ABILITIES)
    return data.abilities
  },
}
