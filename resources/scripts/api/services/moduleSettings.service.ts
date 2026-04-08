import { client } from '../client'

export interface ModuleSettingsField {
  key: string
  type: 'text' | 'password' | 'textarea' | 'switch' | 'number' | 'select' | 'multiselect'
  label: string
  rules: string[]
  default: unknown
  options?: Record<string, string>
}

export interface ModuleSettingsSection {
  title: string
  fields: ModuleSettingsField[]
}

export interface ModuleSettingsSchema {
  sections: ModuleSettingsSection[]
}

export interface ModuleSettingsResponse {
  schema: ModuleSettingsSchema
  values: Record<string, unknown>
}

export const moduleSettingsService = {
  async fetch(slug: string): Promise<ModuleSettingsResponse> {
    const { data } = await client.get(`/api/v1/modules/${slug}/settings`)
    return data
  },

  async update(slug: string, values: Record<string, unknown>): Promise<{ success: boolean }> {
    const { data } = await client.put(`/api/v1/modules/${slug}/settings`, values)
    return data
  },
}
