import { client } from '../client'
import { API } from '../endpoints'
import type { User } from '../../types/domain/user'
import type { ApiResponse } from '../../types/api'

export interface UpdateProfilePayload {
  name: string
  email: string
  password?: string | null
  confirm_password?: string | null
}

export interface UserSettingsPayload {
  settings: Record<string, string | number | boolean | null>
}

export interface UserSettingsResponse {
  [key: string]: string | null
}

export const userService = {
  async getProfile(): Promise<ApiResponse<User>> {
    const { data } = await client.get(API.ME)
    return data
  },

  async updateProfile(payload: UpdateProfilePayload): Promise<ApiResponse<User>> {
    const { data } = await client.put(API.ME, payload)
    return data
  },

  async getSettings(settings?: string[]): Promise<UserSettingsResponse> {
    const { data } = await client.get(API.ME_SETTINGS, {
      params: { settings },
    })
    return data
  },

  async updateSettings(payload: UserSettingsPayload): Promise<{ success: boolean }> {
    const { data } = await client.put(API.ME_SETTINGS, payload)
    return data
  },

  async uploadAvatar(payload: FormData): Promise<ApiResponse<User>> {
    const { data } = await client.post(API.ME_UPLOAD_AVATAR, payload)
    return data
  },
}
